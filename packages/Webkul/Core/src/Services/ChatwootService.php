<?php

namespace Webkul\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatwootService
{
    protected $apiUrl;
    protected $apiToken;
    protected $accountId;

    public function __construct()
    {
        $this->apiUrl = rtrim(env('CHATWOOT_API_URL'), '/');
        $this->apiToken = env('CHATWOOT_API_KEY');
        $this->accountId = env('CHATWOOT_ACCOUNT_ID');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiToken) && !empty($this->accountId);
    }

    /**
     * Search contact by query (email, phone, name)
     */
    public function searchContact(string $query)
    {
        if (!$this->isConfigured()) {
            Log::warning("ChatwootService não configurado. Verifique .env");
            return null;
        }

        try {
            $response = Http::withHeaders([
                'api_access_token' => $this->apiToken,
            ])->get("{$this->apiUrl}/api/v1/accounts/{$this->accountId}/contacts/search", [
                        'q' => $query
                    ]);

            if ($response->successful()) {
                $data = $response->json();
                // Chatwoot returns { payload: [...] }
                return $data['payload'][0] ?? null;
            }

            Log::error('Chatwoot Search API Error: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Chatwoot Search Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update contact custom attributes
     */
    public function updateContactAttributes(int $contactId, array $customAttributes)
    {
        if (!$this->isConfigured())
            return false;

        try {
            $response = Http::withHeaders([
                'api_access_token' => $this->apiToken,
            ])->put("{$this->apiUrl}/api/v1/accounts/{$this->accountId}/contacts/{$contactId}", [
                        'custom_attributes' => $customAttributes
                    ]);

            if (!$response->successful()) {
                Log::error('Chatwoot Update API Error: ' . $response->body());
            }

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Chatwoot Update Exception: ' . $e->getMessage());
            return false;
        }
    }
}
