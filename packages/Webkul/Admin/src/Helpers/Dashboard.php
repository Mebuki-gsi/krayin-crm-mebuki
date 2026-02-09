<?php

namespace Webkul\Admin\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Webkul\Admin\Helpers\Reporting\Activity;
use Webkul\Admin\Helpers\Reporting\Lead;
use Webkul\Admin\Helpers\Reporting\Organization;
use Webkul\Admin\Helpers\Reporting\Person;
use Webkul\Admin\Helpers\Reporting\Product;
use Webkul\Admin\Helpers\Reporting\Quote;

class Dashboard
{
    /**
     * Create a controller instance.
     *
     * @return void
     */
    public function __construct(
        protected Lead $leadReporting,
        protected Activity $activityReporting,
        protected Product $productReporting,
        protected Person $personReporting,
        protected Organization $organizationReporting,
        protected Quote $quoteReporting,
    ) {
    }

    /**
     * Returns the overall revenue statistics.
     */
    public function getRevenueStats(): array
    {
        return [
            'total_won_revenue' => $this->leadReporting->getTotalWonLeadValueProgress(),
            'total_lost_revenue' => $this->leadReporting->getTotalLostLeadValueProgress(),
        ];
    }

    /**
     * Returns the overall statistics.
     */
    public function getOverAllStats(): array
    {
        return [
            'total_leads' => $this->leadReporting->getTotalLeadsProgress(),
            'average_lead_value' => $this->leadReporting->getAverageLeadValueProgress(),
            'average_leads_per_day' => $this->leadReporting->getAverageLeadsPerDayProgress(),
            'total_quotations' => $this->quoteReporting->getTotalQuotesProgress(),
            'total_persons' => $this->personReporting->getTotalPersonsProgress(),
            'total_organizations' => $this->organizationReporting->getTotalOrganizationsProgress(),
        ];
    }

    /**
     * Returns leads statistics.
     */
    public function getTotalLeadsStats(): array
    {
        return [
            'all' => [
                'over_time' => $this->leadReporting->getTotalLeadsOverTime(),
            ],

            'won' => [
                'over_time' => $this->leadReporting->getTotalWonLeadsOverTime(),
            ],
            'lost' => [
                'over_time' => $this->leadReporting->getTotalLostLeadsOverTime(),
            ],
        ];
    }

    /**
     * Returns leads revenue statistics by sources.
     */
    public function getLeadsStatsBySources(): mixed
    {
        return $this->leadReporting->getTotalWonLeadValueBySources();
    }

    /**
     * Returns leads revenue statistics by types.
     */
    public function getLeadsStatsByTypes(): mixed
    {
        return $this->leadReporting->getTotalWonLeadValueByTypes();
    }

    /**
     * Returns open leads statistics by states.
     */
    public function getOpenLeadsByStates(): mixed
    {
        return $this->leadReporting->getOpenLeadsByStates();
    }

    /**
     * Returns top selling products statistics.
     */
    public function getTopSellingProducts(): Collection
    {
        return $this->productReporting->getTopSellingProductsByRevenue(5);
    }

    /**
     * Returns top selling products statistics.
     */
    public function getTopPersons(): Collection
    {
        return $this->personReporting->getTopCustomersByRevenue(5);
    }

    /**
     * Get the start date.
     *
     * @return \Carbon\Carbon
     */
    public function getStartDate(): Carbon
    {
        return $this->leadReporting->getStartDate();
    }

    /**
     * Get the end date.
     *
     * @return \Carbon\Carbon
     */
    public function getEndDate(): Carbon
    {
        return $this->leadReporting->getEndDate();
    }

    /**
     * Returns date range
     */
    public function getDateRange(): string
    {
        return $this->getStartDate()->format('d M') . ' - ' . $this->getEndDate()->format('d M');
    }

    /**
     * Returns client risk analysis statistics.
     */
    public function getClientRiskStats(): array
    {
        return $this->leadReporting->getClientRiskAnalysis();
    }

    /**
     * Toggle client check status.
     * Saves to database for historical tracking.
     */
    public function toggleClientCheck(): array
    {
        $user = auth()->user();
        $cnpj = request('cnpj');
        $clientName = request('client_name');
        $classification = request('classification');
        $isChecked = request('is_checked', true);

        return $this->toggleClientCheckInternal($user, $cnpj, $clientName, $classification, $isChecked);
    }

    /**
     * Internal method to toggle client check.
     */
    private function toggleClientCheckInternal($user, $cnpj, $clientName, $classification, $isChecked): array
    {
        if (!$cnpj || !$user) {
            return ['success' => false, 'message' => 'Invalid request'];
        }

        $tracking = \Webkul\Core\Models\ClientWorkTracking::updateOrCreate(
            [
                'user_id' => $user->id,
                'client_cnpj' => $cnpj,
            ],
            [
                'client_name' => $clientName,
                'classification' => $classification,
                'is_checked' => $isChecked,
                'checked_at' => $isChecked ? now() : null,
                'unchecked_at' => !$isChecked ? now() : null,
            ]
        );

        return [
            'success' => true,
            'is_checked' => $tracking->is_checked,
            'checked_at' => $tracking->checked_at?->toIso8601String(),
        ];
    }

    /**
     * Get check history for date range (for chart).
     */
    /**
     * Get check history for date range (for chart).
     * Returns stacked data by classification.
     */
    public function getCheckHistory(): array
    {
        $user = auth()->user();
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();

        // Get daily counts by classification
        $dailyData = \Webkul\Core\Models\ClientWorkTracking::where('user_id', $user->id)
            ->where('is_checked', true)
            ->whereBetween('checked_at', [$startDate, $endDate->endOfDay()])
            ->selectRaw('DATE(checked_at) as date, classification, COUNT(*) as count')
            ->groupByRaw('DATE(checked_at), classification')
            ->orderBy('date')
            ->get();

        // Prepare structure
        $dates = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dates[$current->format('Y-m-d')] = $current->format('d/m');
            $current->addDay();
        }

        $classifications = [
            'ATIVO_FREQUENTE',
            'ATIVO_REGULAR',
            'RISCO_INATIVACAO',
            'OPORTUNIDADE_RECUPERACAO',
            'INATIVO_BAIXO_POTENCIAL',
            'SEM_HISTORICO'
        ];

        // Initialize datasets
        $datasets = [];
        foreach ($classifications as $cls) {
            $datasets[$cls] = array_fill_keys(array_keys($dates), 0);
        }

        // Fill data
        foreach ($dailyData as $row) {
            $date = $row->date;
            $cls = $row->classification ?? 'SEM_HISTORICO';
            if (isset($datasets[$cls][$date])) {
                $datasets[$cls][$date] = (int) $row->count;
            }
        }

        // Flatten for frontend
        $formattedDatasets = [];
        foreach ($datasets as $cls => $data) {
            $formattedDatasets[$cls] = array_values($data);
        }

        // Get today's count
        $todayCount = \Webkul\Core\Models\ClientWorkTracking::where('user_id', $user->id)
            ->where('is_checked', true)
            ->whereDate('checked_at', now()->toDateString())
            ->count();

        return [
            'labels' => array_values($dates),
            'datasets' => $formattedDatasets,
            'today_count' => $todayCount,
            'total_period' => $dailyData->sum('count'),
        ];
    }

    /**
     * Get all checked client CNPJs for the current user.
     */
    public function getCheckedClients(): array
    {
        $user = auth()->user();

        $checked = \Webkul\Core\Models\ClientWorkTracking::where('user_id', $user->id)
            ->where('is_checked', true)
            ->pluck('client_cnpj')
            ->toArray();

        return ['checked_cnpjs' => $checked];
    }

    /**
     * Create a lead from client risk analysis data.
     * This creates Organization, Person, and Lead with pre-filled data.
     */
    public function createLeadFromClient(): array
    {
        $user = auth()->user();

        // Get client data from request
        $cnpj = request('cnpj');
        $razao = request('razao');
        $nomeContato = request('nome_contato', $razao); // Contact name, fallback to company name
        $telefone = request('telefone');
        $email = request('email');
        $segmento = request('segmento');
        $municipio = request('municipio');
        $uf = request('uf');
        $valorTotal = request('valor_total', 0);
        $ticketMedio = request('ticket_medio', 0);
        $totalPedidos = request('total_pedidos', 0);
        $diasSemCompra = request('dias_sem_compra', 0);
        $classificacao = request('classificacao_risco', '');

        if (!$cnpj || !$razao || !$user) {
            return ['success' => false, 'message' => 'Dados incompletos'];
        }

        try {
            // 0. Auto-check client logic
            $this->toggleClientCheckInternal($user, $cnpj, $razao, $classificacao, true);

            // 1. Create or find Organization
            $organizationRepo = app(\Webkul\Contact\Repositories\OrganizationRepository::class);
            $organization = $organizationRepo->findOneWhere(['name' => $razao]);

            if (!$organization) {
                $organization = $organizationRepo->create([
                    'name' => $razao,
                    'cnpj' => $cnpj,
                    'address' => [
                        'city' => $municipio,
                        'state' => $uf,
                        'country' => 'BR',
                    ],
                    'user_id' => $user->id,
                ]);
            }

            // 2. Create or find Person
            $personRepo = app(\Webkul\Contact\Repositories\PersonRepository::class);

            // Try to find by email first
            $person = null;
            if ($email) {
                $person = $personRepo->whereJsonContains('emails', [['value' => $email]])->first();
            }

            if (!$person) {
                $emailsArray = $email ? [['value' => $email, 'label' => 'work']] : [];
                $phonesArray = $telefone ? [['value' => $telefone, 'label' => 'work']] : [];

                $person = $personRepo->create([
                    'entity_type' => 'persons',
                    'name' => $nomeContato,
                    'emails' => $emailsArray,
                    'contact_numbers' => $phonesArray,
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                ]);
            }


            // 3. Build description with all client info
            $description = "📋 **Dados do Cliente BigQuery**\n\n";
            $description .= "**CNPJ:** {$cnpj}\n";
            $description .= "**Segmento:** {$segmento}\n";
            $description .= "**Localização:** {$municipio}/{$uf}\n\n";
            $description .= "📊 **Histórico de Vendas**\n";
            $description .= "- Total em Vendas: R$ " . number_format($valorTotal, 2, ',', '.') . "\n";
            $description .= "- Ticket Médio: R$ " . number_format($ticketMedio, 2, ',', '.') . "\n";
            $description .= "- Total de Pedidos: {$totalPedidos}\n";
            $description .= "- Dias sem Compra: {$diasSemCompra}\n\n";
            $description .= "⚠️ **Status:** {$classificacao}\n";
            $description .= "\n---\n_Lead criado automaticamente via Análise de Risco de Carteira_";

            // 4. Get default pipeline and first stage
            $pipelineRepo = app(\Webkul\Lead\Repositories\PipelineRepository::class);
            $pipeline = $pipelineRepo->getDefaultPipeline();
            $stage = $pipeline->stages()->first();

            // 5. Create Lead
            $leadRepo = app(\Webkul\Lead\Repositories\LeadRepository::class);

            $lead = $leadRepo->create([
                'entity_type' => 'leads',
                'title' => "Reativação - {$razao}",
                'description' => $description,
                'lead_value' => $ticketMedio,
                'status' => 1,
                'user_id' => $user->id,
                'person_id' => $person->id,
                'lead_pipeline_id' => $pipeline->id,
                'lead_pipeline_stage_id' => $stage->id,
            ]);


            return [
                'success' => true,
                'lead_id' => $lead->id,
                'message' => 'Lead criado com sucesso!',
                'redirect_url' => route('admin.leads.view', $lead->id),
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Create lead from client error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao criar lead: ' . $e->getMessage(),
            ];
        }
    }
}
