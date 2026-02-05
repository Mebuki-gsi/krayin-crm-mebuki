<?php

namespace Webkul\Core\Services;

use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Support\Facades\Log;
use Exception;

class BigQueryService
{
    /**
     * BigQuery client instance.
     *
     * @var \Google\Cloud\BigQuery\BigQueryClient|null
     */
    protected $client;

    /**
     * Config keys.
     */
    protected $config = [];

    /**
     * Create a new service instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->config = [
            'enable' => core()->getConfigData('general.bigquery.settings.enable'),
            'project_id' => core()->getConfigData('general.bigquery.settings.project_id'),
            'dataset_id' => core()->getConfigData('general.bigquery.settings.dataset_id'),
            'table_id' => core()->getConfigData('general.bigquery.settings.table_id'),
            'service_account' => core()->getConfigData('general.bigquery.settings.service_account'),
        ];
    }

    /**
     * Is enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return (bool) $this->config['enable'];
    }

    /**
     * Get BigQuery client.
     *
     * @return \Google\Cloud\BigQuery\BigQueryClient|null
     */
    public function getClient()
    {
        if ($this->client) {
            return $this->client;
        }

        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $keyData = json_decode($this->config['service_account'], true);

            if (!$keyData) {
                throw new Exception('Invalid Service Account JSON.');
            }

            $this->client = new BigQueryClient([
                'projectId' => $this->config['project_id'],
                'keyMapper' => function ($key) use ($keyData) {
                    return $keyData[$key] ?? null;
                },
                'keyFile' => $keyData,
            ]);

            return $this->client;
        } catch (Exception $e) {
            Log::error('BigQuery Connection Error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Run a query.
     *
     * @param  string  $query
     * @param  array   $params
     * @return array
     */
    public function runQuery($query, $params = [])
    {
        $client = $this->getClient();

        if (!$client) {
            return [];
        }

        try {
            $queryConfig = $client->query($query);

            if (!empty($params)) {
                $queryConfig->parameters($params);
            }

            $job = $client->runQuery($queryConfig);

            $results = [];

            foreach ($job->rows() as $row) {
                $results[] = $row;
            }

            return $results;
        } catch (Exception $e) {
            Log::error('BigQuery Query Error: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Get vendor codes by email.
     *
     * @param  string  $email
     * @return array
     */
    public function getVendorCodesByEmail($email)
    {
        $query = "SELECT DISTINCT CODIGO_VENDEDOR 
                  FROM `{$this->config['project_id']}.{$this->config['dataset_id']}.{$this->config['table_id']}`
                  WHERE EMAIL_REP = @email";

        $results = $this->runQuery($query, ['email' => $email]);

        return array_column($results, 'CODIGO_VENDEDOR');
    }

    /**
     * Get revenue and meta stats for the given period and email.
     *
     * @param  string  $email
     * @param  string  $startDate
     * @param  string  $endDate
     * @return array
     */
    public function getRevenueStats($email, $startDate, $endDate)
    {
        $vendorCodes = $this->getVendorCodesByEmail($email);

        if (empty($vendorCodes)) {
            // Fallback to email directly if no vendor code found (though unlikely given validation)
            $whereClause = "WHERE EMAIL_REP = @email";
            $params = ['email' => $email];
        } else {
            $whereClause = "WHERE CODIGO_VENDEDOR IN UNNEST(@codes)";
            $params = ['codes' => $vendorCodes];
        }

        $query = "SELECT 
                    SUM(faturamento_semst) as total_faturamento,
                    MAX(Meta_vendedor) as total_meta
                  FROM `{$this->config['project_id']}.{$this->config['dataset_id']}.{$this->config['table_id']}`
                  {$whereClause}
                  AND DATE(EMISSAO_faturamento) BETWEEN @start AND @end";

        $params['start'] = $startDate;
        $params['end'] = $endDate;

        $results = $this->runQuery($query, $params);

        return $results[0] ?? ['total_faturamento' => 0, 'total_meta' => 0];
    }
}
