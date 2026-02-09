<?php

namespace Webkul\Core\Console\Commands;

use Illuminate\Console\Command;
use Webkul\User\Repositories\UserRepository;
use Webkul\Core\Services\BigQueryService;
use Webkul\Core\Services\ChatwootService;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;

class SyncChatwootWallet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatwoot:sync-wallet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync CRM Wallet (from BigQuery) to Chatwoot Contacts attributes';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        protected UserRepository $userRepository,
        protected BigQueryService $bigQueryService,
        protected ChatwootService $chatwootService,
        protected OrganizationRepository $organizationRepository,
        protected PersonRepository $personRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->chatwootService->isConfigured()) {
            $this->error('Chatwoot is not configured in .env file (CHATWOOT_API_URL, CHATWOOT_API_KEY, CHATWOOT_ACCOUNT_ID).');
            return;
        }

        $this->info('Starting Chatwoot Wallet Sync...');

        // Get all active users
        $users = $this->userRepository->findWhere(['status' => 1]);

        foreach ($users as $user) {
            $this->info("Processing User: {$user->name} ({$user->email})");

            try {
                // 1. Get Wallet CNPJs from BigQuery
                $activeCnpjs = $this->bigQueryService->getActiveCnpjs($user->email);
            } catch (\Exception $e) {
                $this->error(" - Error fetching wallet: " . $e->getMessage());
                continue;
            }

            if (empty($activeCnpjs)) {
                $this->warn(" - No active wallet found in BigQuery.");
                continue;
            }

            $this->info(" - Active Wallet Size: " . count($activeCnpjs) . " CNPJs.");

            // 2. Find Organizations in CRM matching these CNPJs
            // Chunking to handle large wallets
            $chunkSize = 200;
            $cnpjChunks = array_chunk($activeCnpjs, $chunkSize);

            $processedCount = 0;

            foreach ($cnpjChunks as $chunk) {
                $organizations = $this->organizationRepository->findWhereIn('cnpj', $chunk);
                $orgIds = $organizations->pluck('id')->toArray();

                if (empty($orgIds))
                    continue;

                // 3. Find Contacts linked to these Organizations
                $people = $this->personRepository->findWhereIn('organization_id', $orgIds);

                foreach ($people as $person) {
                    // Extract email
                    $targetEmail = null;
                    if (is_array($person->emails)) {
                        foreach ($person->emails as $emailData) {
                            if (($emailData['label'] ?? '') === 'work' || empty($targetEmail)) {
                                $targetEmail = $emailData['value'];
                            }
                        }
                    }

                    if (!$targetEmail)
                        continue;

                    // 4. Sync with Chatwoot
                    // Search contact
                    $contact = $this->chatwootService->searchContact($targetEmail);

                    if ($contact) {
                        // Update Attributes
                        $updated = $this->chatwootService->updateContactAttributes($contact['id'], [
                            'crm_wallet_owner_email' => $user->email,
                            'crm_organization_name' => $person->organization?->name ?? 'Sem Empresa',
                            'crm_person_name' => $person->name,
                            'crm_updated_at' => now()->toIso8601String()
                        ]);

                        if ($updated) {
                            $this->line("   [OK] Synced {$targetEmail}");
                            $processedCount++;
                        } else {
                            $this->error("   [ERR] Failed to update {$targetEmail}");
                        }
                    } else {
                        // Contact not found in Chatwoot
                        // $this->warn("   [MISS] Contact not found in Chatwoot: {$targetEmail}");
                    }
                }
            }

            $this->info(" - Synced {$processedCount} contacts for {$user->name}.");
        }

        $this->info('Sync Completed!');
    }
}
