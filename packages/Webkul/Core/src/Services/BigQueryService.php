<?php

namespace Webkul\Core\Services;

use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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
            'service_account' => core()->getConfigData('general.bigquery.settings.service_account'),
            'revenue_dataset' => core()->getConfigData('general.bigquery.settings.revenue_dataset'),
            'revenue_table' => core()->getConfigData('general.bigquery.settings.revenue_table'),
            'positivation_dataset' => core()->getConfigData('general.bigquery.settings.positivation_dataset'),
            'positivation_table' => core()->getConfigData('general.bigquery.settings.positivation_table'),
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
                Log::error('BigQuery Service Account JSON is empty or invalid.');
                return null;
            }

            $this->client = new BigQueryClient([
                'projectId' => $this->config['project_id'],
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
            Log::error('BigQuery Query Error: ' . $e->getMessage() . " | Query: " . $query);

            return [];
        }
    }

    /**
     * Get vendor codes by emails.
     *
     * @param  array|string  $emails
     * @param  string        $dataset
     * @param  string        $table
     * @return array
     */
    public function getVendorCodesByEmails($emails, $dataset, $table)
    {
        if (empty($emails)) {
            return [];
        }

        $emails = is_array($emails) ? $emails : [$emails];

        $query = "SELECT DISTINCT CODIGO_VENDEDOR 
                  FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                  WHERE EMAIL_REP IN UNNEST(@emails)";

        $results = $this->runQuery($query, ['emails' => $emails]);

        return array_column($results, 'CODIGO_VENDEDOR');
    }

    /**
     * Determine user role from BigQuery.
     * 
     * @param  string  $email
     * @return string  (admin|gerente|vendedor)
     */
    public function determineUserRole($email)
    {
        return \Illuminate\Support\Facades\Cache::remember('bq_role_' . $email, 86400, function () use ($email) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];

            if (!$dataset || !$table) {
                return 'admin';
            }

            $query = "SELECT 'gerente' as role FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE EMAIL_REGIONAL_METAS = @email LIMIT 1
                      UNION ALL 
                      SELECT 'vendedor' as role FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE EMAIL_REP = @email LIMIT 1";

            $results = $this->runQuery($query, ['email' => $email]);

            if (empty($results)) {
                return 'admin';
            }

            $roles = array_column($results, 'role');

            if (in_array('gerente', $roles)) {
                return 'gerente';
            }

            return 'vendedor';
        });
    }

    /**
     * Get subordinates emails for a manager.
     * 
     * @param  string  $managerEmail
     * @return array
     */
    public function getSubordinates($managerEmail)
    {
        return \Illuminate\Support\Facades\Cache::remember('bq_subordinates_' . $managerEmail, 86400, function () use ($managerEmail) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];

            if (!$dataset || !$table) {
                return [];
            }

            $query = "SELECT DISTINCT EMAIL_REP 
                      FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                      WHERE EMAIL_REGIONAL_METAS = @managerEmail";

            $results = $this->runQuery($query, ['managerEmail' => $managerEmail]);

            return array_column($results, 'EMAIL_REP');
        });
    }

    /**
     * Get revenue and meta stats for the given period and emails.
     *
     * @param  array|string  $emails
     * @param  string        $startDate
     * @param  string        $endDate
     * @return array
     */
    public function getRevenueStats($emails, $startDate, $endDate)
    {
        $emails = is_array($emails) ? $emails : [$emails];
        sort($emails);
        $cacheKey = 'bq_rev_' . md5(implode(',', $emails) . $startDate . $endDate);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 1800, function () use ($emails, $startDate, $endDate) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];

            if (!$dataset || !$table) {
                return ['total_faturamento' => 0, 'total_meta' => 0];
            }

            $vendorCodes = $this->getVendorCodesByEmails($emails, $dataset, $table);

            if (empty($vendorCodes)) {
                return ['total_faturamento' => 0, 'total_meta' => 0];
            }

            // Subquery to get max meta per vendor per month, then sum
            $query = "SELECT 
                        SUM(faturamento_vendedor) as total_faturamento,
                        SUM(meta_vendedor_mes) as total_meta
                    FROM (
                        SELECT 
                            FORMAT_DATE('%Y-%m', EMISSAO_faturamento) as mes,
                            CODIGO_VENDEDOR,
                            MAX(Meta_vendedor) as meta_vendedor_mes,
                            SUM(faturamento_semst) as faturamento_vendedor
                        FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                        WHERE CODIGO_VENDEDOR IN UNNEST(@codes)
                        AND DATE(EMISSAO_faturamento) BETWEEN @start AND @end
                        GROUP BY mes, CODIGO_VENDEDOR
                    )";

            $params = [
                'codes' => $vendorCodes,
                'start' => $startDate,
                'end' => $endDate,
            ];

            $results = $this->runQuery($query, $params);

            return $results[0] ?? ['total_faturamento' => 0, 'total_meta' => 0];
        });
    }

    /**
     * Get positivation and meta stats for the given period and emails.
     *
     * @param  array|string  $emails
     * @param  string        $startDate
     * @param  string        $endDate
     * @return array
     */
    public function getPositivationStats($emails, $startDate, $endDate)
    {
        $emails = is_array($emails) ? $emails : [$emails];
        sort($emails);
        $cacheKey = 'bq_pos_' . md5(implode(',', $emails) . $startDate . $endDate);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 1800, function () use ($emails, $startDate, $endDate) {
            $dataset = $this->config['positivation_dataset'];
            $table = $this->config['positivation_table'];

            if (!$dataset || !$table) {
                return ['total_positivacao' => 0, 'total_meta' => 0];
            }

            $vendorCodes = $this->getVendorCodesByEmails($emails, $dataset, $table);

            if (empty($vendorCodes)) {
                return ['total_positivacao' => 0, 'total_meta' => 0];
            }

            // Positivation = Count of distinct CODIGO_CLIENTE
            $query = "SELECT 
                        SUM(positivacao_vendedor) as total_positivacao,
                        SUM(meta_positivacao_mes) as total_meta
                    FROM (
                        SELECT 
                            FORMAT_DATE('%Y-%m', EMISSAO_faturamento) as mes,
                            CODIGO_VENDEDOR,
                            MAX(Meta_positivacao) as meta_positivacao_mes,
                            COUNT(DISTINCT CODIGO_CLIENTE) as positivacao_vendedor
                        FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                        WHERE CODIGO_VENDEDOR IN UNNEST(@codes)
                        AND DATE(EMISSAO_faturamento) BETWEEN @start AND @end
                        GROUP BY mes, CODIGO_VENDEDOR
                    )";

            $params = [
                'codes' => $vendorCodes,
                'start' => $startDate,
                'end' => $endDate,
            ];

            $results = $this->runQuery($query, $params);

            return $results[0] ?? ['total_positivacao' => 0, 'total_meta' => 0];
        });
    }
}
