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
            'enable' => core()->getConfigData('general.bigquery.settings.enable') ?? env('BIGQUERY_ENABLE', false),
            'project_id' => core()->getConfigData('general.bigquery.settings.project_id') ?? env('BIGQUERY_PROJECT_ID'),
            'service_account' => core()->getConfigData('general.bigquery.settings.service_account') ?? env('BIGQUERY_SERVICE_ACCOUNT'),
            'revenue_dataset' => core()->getConfigData('general.bigquery.settings.revenue_dataset') ?? env('BIGQUERY_REVENUE_DATASET'),
            'revenue_table' => core()->getConfigData('general.bigquery.settings.revenue_table') ?? env('BIGQUERY_REVENUE_TABLE'),
            'positivation_dataset' => core()->getConfigData('general.bigquery.settings.positivation_dataset') ?? env('BIGQUERY_POSITIVATION_DATASET'),
            'positivation_table' => core()->getConfigData('general.bigquery.settings.positivation_table') ?? env('BIGQUERY_POSITIVATION_TABLE'),
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
        $emails = array_map('strtolower', $emails);

        $query = "SELECT DISTINCT CODIGO_VENDEDOR 
                  FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                  WHERE LOWER(EMAIL_REP) IN UNNEST(@emails)";

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
        $email = strtolower($email);

        return \Illuminate\Support\Facades\Cache::remember('bq_role_' . $email, 3600, function () use ($email) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];

            if (!$dataset || !$table) {
                return 'admin';
            }

            $query = "SELECT 'gerente' as role FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE LOWER(EMAIL_REGIONAL_METAS) = @email LIMIT 1
                      UNION ALL 
                      SELECT 'vendedor' as role FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE LOWER(EMAIL_REP) = @email LIMIT 1";

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
        $managerEmail = strtolower($managerEmail);

        return \Illuminate\Support\Facades\Cache::remember('bq_subordinates_' . $managerEmail, 3600, function () use ($managerEmail) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];

            if (!$dataset || !$table) {
                return [];
            }

            $query = "SELECT DISTINCT EMAIL_REP 
                      FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                      WHERE LOWER(EMAIL_REGIONAL_METAS) = @managerEmail";

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

            // Refined query: 
            // 1. Sum faturamento for exact range.
            // 2. Sum unique monthly metas for the full months in range (ensuring meta shows even with 0 sales).
            $query = "
                WITH raw_data AS (
                    SELECT 
                        CODIGO_VENDEDOR,
                        DATE(EMISSAO_faturamento) as data_emissao,
                        FORMAT_DATE('%Y-%m', EMISSAO_faturamento) as mes,
                        COALESCE(faturamento_semst, 0) as fat,
                        COALESCE(Meta_vendedor, 0) as meta
                    FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                    WHERE CODIGO_VENDEDOR IN UNNEST(@codes)
                    AND DATE(EMISSAO_faturamento) BETWEEN DATE_TRUNC(@start, MONTH) AND LAST_DAY(@end)
                ),
                monthly_metas AS (
                    SELECT 
                        CODIGO_VENDEDOR,
                        mes,
                        MAX(meta) as meta_mes
                    FROM raw_data
                    GROUP BY CODIGO_VENDEDOR, mes
                ),
                faturamento_sum AS (
                    SELECT 
                        SUM(fat) as total_faturamento
                    FROM raw_data
                    WHERE data_emissao BETWEEN @start AND @end
                )
                SELECT 
                    COALESCE(f.total_faturamento, 0) as total_faturamento,
                    COALESCE((SELECT SUM(meta_mes) FROM monthly_metas), 0) as total_meta
                FROM faturamento_sum f
            ";

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
            $dataset = $this->config['positivation_dataset'] ?? $this->config['revenue_dataset'];
            $table = $this->config['positivation_table'] ?? $this->config['revenue_table'];

            if (!$dataset || !$table) {
                return ['total_positivacao' => 0, 'total_meta' => 0];
            }

            $vendorCodes = $this->getVendorCodesByEmails($emails, $dataset, $table);

            if (empty($vendorCodes)) {
                return ['total_positivacao' => 0, 'total_meta' => 0];
            }

            // Positivation: 
            // 1. Count distinct COD_ORIGEM in range.
            // 2. Sum unique monthly meta_positivacao.
            $query = "
                WITH raw_data AS (
                    SELECT 
                        CODIGO_VENDEDOR,
                        COD_ORIGEM,
                        DATE(EMISSAO_faturamento) as data_emissao,
                        FORMAT_DATE('%Y-%m', EMISSAO_faturamento) as mes,
                        COALESCE(meta_positivacao, 0) as meta
                    FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                    WHERE CODIGO_VENDEDOR IN UNNEST(@codes)
                    AND DATE(EMISSAO_faturamento) BETWEEN DATE_TRUNC(@start, MONTH) AND LAST_DAY(@end)
                ),
                monthly_metas AS (
                    SELECT 
                        CODIGO_VENDEDOR,
                        mes,
                        MAX(meta) as meta_mes
                    FROM raw_data
                    GROUP BY CODIGO_VENDEDOR, mes
                ),
                positivacao_sum AS (
                    SELECT 
                        COUNT(DISTINCT COD_ORIGEM) as total_positivacao
                    FROM raw_data
                    WHERE data_emissao BETWEEN @start AND @end
                )
                SELECT 
                    COALESCE(p.total_positivacao, 0) as total_positivacao,
                    COALESCE((SELECT SUM(meta_mes) FROM monthly_metas), 0) as total_meta
                FROM positivacao_sum p
            ";

            $params = [
                'codes' => $vendorCodes,
                'start' => $startDate,
                'end' => $endDate,
            ];

            $results = $this->runQuery($query, $params);

            return $results[0] ?? ['total_positivacao' => 0, 'total_meta' => 0];
        });
    }

    /**
     * Get all managers from BigQuery.
     * 
     * @return array
     */
    public function getManagers()
    {
        return Cache::remember('bq_managers', 86400, function () {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];

            if (!$dataset || !$table) {
                return [];
            }

            $query = "
                SELECT DISTINCT 
                    LOWER(TRIM(EMAIL_REGIONAL_METAS)) as email,
                    ANY_VALUE(GERENCIA_REGIONAL) as name
                FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                WHERE EMAIL_REGIONAL_METAS IS NOT NULL 
                  AND EMAIL_REGIONAL_METAS != ''
                GROUP BY email
            ";

            return $this->runQuery($query);
        });
    }
}
