<?php

namespace Webkul\Core\Services;

use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class BigQueryService
{
    protected $client;
    protected $config = [];

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

    public function isEnabled() { return (bool) $this->config['enable']; }

    public function getClient()
    {
        if ($this->client) return $this->client;
        if (!$this->isEnabled()) return null;
        try {
            $keyData = json_decode($this->config['service_account'], true);
            if (!$keyData) return null;
            $this->client = new BigQueryClient(['projectId' => $this->config['project_id'], 'keyFile' => $keyData]);
            return $this->client;
        } catch (Exception $e) { return null; }
    }

    public function runQuery($query, $params = [])
    {
        $client = $this->getClient();
        if (!$client) return [];
        try {
            $queryConfig = $client->query($query);
            if (!empty($params)) $queryConfig->parameters($params);
            $job = $client->runQuery($queryConfig);
            $results = [];
            foreach ($job->rows() as $row) { $results[] = $row; }
            return $results;
        } catch (Exception $e) { return []; }
    }

    public function getVendorCodesByEmails($emails, $dataset, $table)
    {
        if (empty($emails)) return [];
        $emails = is_array($emails) ? $emails : [$emails];
        $emails = array_map('strtolower', $emails);
        sort($emails);
        $cacheKey = 'bq_vendor_codes_' . md5(implode(',', $emails) . $dataset . $table);
        return Cache::remember($cacheKey, 86400, function () use ($emails, $dataset, $table) {
            $query = "SELECT DISTINCT CODIGO_VENDEDOR FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE LOWER(EMAIL_REP) IN UNNEST(@emails)";
            $results = $this->runQuery($query, ['emails' => $emails]);
            return array_column($results, 'CODIGO_VENDEDOR');
        });
    }

    public function determineUserRole($email)
    {
        $email = strtolower($email);
        return Cache::remember('bq_role_' . $email, 3600, function () use ($email) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];
            if (!$dataset || !$table) return 'admin';
            $query = "SELECT 'gerente' as role FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE LOWER(EMAIL_REGIONAL_METAS) = @email LIMIT 1
                      UNION ALL 
                      SELECT 'vendedor' as role FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE LOWER(EMAIL_REP) = @email LIMIT 1";
            $results = $this->runQuery($query, ['email' => $email]);
            if (empty($results)) return 'admin';
            $roles = array_column($results, 'role');
            return in_array('gerente', $roles) ? 'gerente' : 'vendedor';
        });
    }

    public function getSubordinates($managerEmail)
    {
        $managerEmail = strtolower($managerEmail);
        return Cache::remember('bq_subordinates_' . $managerEmail, 3600, function () use ($managerEmail) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];
            if (!$dataset || !$table) return [];
            $query = "SELECT DISTINCT EMAIL_REP FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE LOWER(EMAIL_REGIONAL_METAS) = @managerEmail";
            $results = $this->runQuery($query, ['managerEmail' => $managerEmail]);
            return array_column($results, 'EMAIL_REP');
        });
    }

    public function getRevenueStats($emails, $startDate, $endDate)
    {
        $emails = is_array($emails) ? $emails : [$emails];
        sort($emails);
        $cacheKey = 'bq_rev_' . md5(implode(',', $emails) . $startDate . $endDate);
        return Cache::remember($cacheKey, 1800, function () use ($emails, $startDate, $endDate) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];
            if (!$dataset || !$table) return ['total_faturamento' => 0, 'total_meta' => 0];
            $vendorCodes = $this->getVendorCodesByEmails($emails, $dataset, $table);
            if (empty($vendorCodes)) return ['total_faturamento' => 0, 'total_meta' => 0];
            $query = "
                WITH raw_data AS (
                    SELECT CODIGO_VENDEDOR, DATE(EMISSAO_faturamento) as data_emissao, FORMAT_DATE('%Y-%m', EMISSAO_faturamento) as mes, COALESCE(faturamento_semst, 0) as fat, COALESCE(Meta_vendedor, 0) as meta
                    FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                    WHERE CODIGO_VENDEDOR IN UNNEST(@codes) AND DATE(EMISSAO_faturamento) BETWEEN DATE_TRUNC(@start, MONTH) AND LAST_DAY(@end)
                ),
                monthly_metas AS (SELECT CODIGO_VENDEDOR, mes, MAX(meta) as meta_mes FROM raw_data GROUP BY CODIGO_VENDEDOR, mes),
                faturamento_sum AS (SELECT SUM(fat) as total_faturamento FROM raw_data WHERE data_emissao BETWEEN @start AND @end)
                SELECT COALESCE(f.total_faturamento, 0) as total_faturamento, COALESCE((SELECT SUM(meta_mes) FROM monthly_metas), 0) as total_meta FROM faturamento_sum f
            ";
            $results = $this->runQuery($query, ['codes' => $vendorCodes, 'start' => $startDate, 'end' => $endDate]);
            return $results[0] ?? ['total_faturamento' => 0, 'total_meta' => 0];
        });
    }

    public function getPositivationStats($emails, $startDate, $endDate)
    {
        $emails = is_array($emails) ? $emails : [$emails];
        sort($emails);
        $cacheKey = 'bq_pos_' . md5(implode(',', $emails) . $startDate . $endDate);
        return Cache::remember($cacheKey, 1800, function () use ($emails, $startDate, $endDate) {
            $dataset = $this->config['positivation_dataset'] ?? $this->config['revenue_dataset'];
            $table = $this->config['positivation_table'] ?? $this->config['revenue_table'];
            if (!$dataset || !$table) return ['total_positivacao' => 0, 'total_meta' => 0];
            $vendorCodes = $this->getVendorCodesByEmails($emails, $dataset, $table);
            if (empty($vendorCodes)) return ['total_positivacao' => 0, 'total_meta' => 0];
            $query = "
                WITH raw_data AS (
                    SELECT CODIGO_VENDEDOR, COD_ORIGEM, DATE(EMISSAO_faturamento) as data_emissao, FORMAT_DATE('%Y-%m', EMISSAO_faturamento) as mes, COALESCE(meta_positivacao, 0) as meta
                    FROM `{$this->config['project_id']}.{$dataset}.{$table}`
                    WHERE CODIGO_VENDEDOR IN UNNEST(@codes) AND DATE(EMISSAO_faturamento) BETWEEN DATE_TRUNC(@start, MONTH) AND LAST_DAY(@end)
                ),
                monthly_metas AS (SELECT CODIGO_VENDEDOR, mes, MAX(meta) as meta_mes FROM raw_data GROUP BY CODIGO_VENDEDOR, mes),
                positivacao_sum AS (SELECT COUNT(DISTINCT COD_ORIGEM) as total_positivacao FROM raw_data WHERE data_emissao BETWEEN @start AND @end)
                SELECT COALESCE(p.total_positivacao, 0) as total_positivacao, COALESCE((SELECT SUM(meta_mes) FROM monthly_metas), 0) as total_meta FROM positivacao_sum p
            ";
            $results = $this->runQuery($query, ['codes' => $vendorCodes, 'start' => $startDate, 'end' => $endDate]);
            return $results[0] ?? ['total_positivacao' => 0, 'total_meta' => 0];
        });
    }

    public function getManagers()
    {
        return Cache::remember('bq_managers', 86400, function () {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];
            if (!$dataset || !$table) return [];
            $query = "SELECT DISTINCT LOWER(TRIM(EMAIL_REGIONAL_METAS)) as email, ANY_VALUE(GERENCIA_REGIONAL) as name FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE EMAIL_REGIONAL_METAS IS NOT NULL AND EMAIL_REGIONAL_METAS != '' GROUP BY email";
            return $this->runQuery($query);
        });
    }

    public function getTopSellingProducts(array $emails, $startDate, $endDate, $limit = 5): array
    {
        if (!$this->isEnabled()) return [];
        $cacheKey = "bq_top_products_" . md5(json_encode($emails) . $startDate . $endDate . $limit);
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($emails, $startDate, $endDate, $limit) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];
            $vendorCodes = $this->getVendorCodesByEmails($emails, $dataset, $table);
            if (empty($vendorCodes)) return [];
            $query = "SELECT PRODUTO_METAS as name, ANY_VALUE(COD_ITEM_METAS) as code, SUM(COALESCE(faturamento_semst, 0)) as revenue, SUM(COALESCE(QTDE_faturamento, 0)) as quantity FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE CODIGO_VENDEDOR IN UNNEST(@codes) AND DATE(EMISSAO_faturamento) BETWEEN @start AND @end GROUP BY name HAVING revenue > 0 ORDER BY revenue DESC LIMIT @limit";
            $client = $this->getClient();
            if (!$client) return [];
            $jobConfig = $client->query($query)->parameters(['codes' => $vendorCodes, 'start' => $startDate, 'end' => $endDate, 'limit' => (int) $limit]);
            $results = $client->runQuery($jobConfig);
            $items = [];
            foreach ($results as $row) { $items[] = ['name' => $row['name'], 'code' => $row['code'], 'revenue' => (float) $row['revenue'], 'quantity' => (float) $row['quantity']]; }
            return $items;
        });
    }

    public function getTopCustomers(array $emails, $startDate, $endDate, $limit = 5): array
    {
        if (!$this->isEnabled()) return [];
        $cacheKey = "bq_top_customers_" . md5(json_encode($emails) . $startDate . $endDate . $limit);
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($emails, $startDate, $endDate, $limit) {
            $dataset = $this->config['revenue_dataset'];
            $table = $this->config['revenue_table'];
            $vendorCodes = $this->getVendorCodesByEmails($emails, $dataset, $table);
            if (empty($vendorCodes)) return [];
            $query = "SELECT RAZAO as name, ANY_VALUE(COD_ORIGEM) as code, SUM(COALESCE(faturamento_semst, 0)) as revenue FROM `{$this->config['project_id']}.{$dataset}.{$table}` WHERE CODIGO_VENDEDOR IN UNNEST(@codes) AND DATE(EMISSAO_faturamento) BETWEEN @start AND @end GROUP BY name HAVING revenue > 0 ORDER BY revenue DESC LIMIT @limit";
            $client = $this->getClient();
            if (!$client) return [];
            $jobConfig = $client->query($query)->parameters(['codes' => $vendorCodes, 'start' => $startDate, 'end' => $endDate, 'limit' => (int) $limit]);
            $results = $client->runQuery($jobConfig);
            $items = [];
            foreach ($results as $row) { $items[] = ['name' => $row['name'], 'code' => $row['code'], 'revenue' => (float) $row['revenue']]; }
            return $items;
        });
    }

    public function getClientRiskAnalysis($emails): array
    {
        if (!$this->isEnabled()) return [];
        $emails = is_string($emails) ? [$emails] : $emails;
        $cacheKey = "bq_client_risk_" . md5(json_encode($emails));
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($emails) {
            $projectId = $this->config['project_id'];
            $fantasiaPadQuery = "SELECT DISTINCT FANTASIA_PAD FROM `{$projectId}.VENDAS.VendasHistoricasDois` WHERE LOWER(EMAIL_REP) IN UNNEST(@emails)";
            $client = $this->getClient();
            if (!$client) return [];
            $lowerEmails = array_map('strtolower', $emails);
            $jobConfig = $client->query($fantasiaPadQuery)->parameters(['emails' => $lowerEmails]);
            $results = $client->runQuery($jobConfig);
            $fantasiaPads = [];
            foreach ($results as $row) { if (!empty($row['FANTASIA_PAD'])) $fantasiaPads[] = $row['FANTASIA_PAD']; }
            if (empty($fantasiaPads)) return [];

            $query = "
                WITH carteira AS (
                    SELECT DISTINCT REGEXP_REPLACE(REGEXP_REPLACE(COD_ORIGEM, r\"['\\.\-/]\", ''), r'[^0-9]', '') AS cnpj, COD_ORIGEM AS cnpj_original, Razao AS razao, Status_Carteira AS status_carteira, Segmento AS segmento, UF AS uf, Municipio AS municipio, Telefone AS telefone, Email AS email, FANTASIA_PAD AS fantasia_pad
                    FROM `{$projectId}.VENDAS.CarteiraGeral`
                    WHERE FANTASIA_PAD IN UNNEST(@fantasia_pads) AND Status_Carteira = 'ATIVO'
                ),
                vendas_agg AS (
                    SELECT COD_ORIGEM AS cnpj, COUNT(DISTINCT PEDIDO) AS total_pedidos, SUM(SAFE_CAST(TOTAL_ITEM AS FLOAT64)) AS valor_total, MAX(PARSE_DATE('%Y-%m-%d', SUBSTR(EMISSAO, 1, 10))) AS ultima_compra, MIN(PARSE_DATE('%Y-%m-%d', SUBSTR(EMISSAO, 1, 10))) AS primeira_compra, AVG(SAFE_CAST(TOTAL_ITEM AS FLOAT64)) AS ticket_medio, ANY_VALUE(FANTASIA_PAD) AS fantasia_pad
                    FROM `{$projectId}.VENDAS.VendasHistoricasDois`
                    WHERE FANTASIA_PAD IN UNNEST(@fantasia_pads) GROUP BY COD_ORIGEM
                ),
                ticket_medio_ativos AS (
                    SELECT COALESCE(AVG(v.valor_total), 0) AS threshold FROM carteira c LEFT JOIN vendas_agg v ON c.cnpj = v.cnpj WHERE c.status_carteira = 'ATIVO' AND v.valor_total IS NOT NULL
                )
                SELECT 
                    COALESCE(c.cnpj_original, v.cnpj) AS cnpj_original, COALESCE(c.razao, 'Cliente s/ Cadastro') AS razao, COALESCE(c.status_carteira, 'NÃO ATIVO') AS status_carteira, c.segmento, c.uf, c.municipio, COALESCE(c.telefone, 'S/ Tel') AS telefone, c.email, COALESCE(c.fantasia_pad, v.fantasia_pad) AS fantasia_pad, COALESCE(v.total_pedidos, 0) AS total_pedidos, COALESCE(v.valor_total, 0) AS valor_total, v.ultima_compra, v.primeira_compra, COALESCE(v.ticket_medio, 0) AS ticket_medio,
                    GREATEST(DATE_DIFF(CURRENT_DATE(), v.ultima_compra, DAY), 0) AS dias_sem_compra, t.threshold AS ticket_threshold,
                    CASE 
                        WHEN c.cnpj IS NULL AND v.cnpj IS NOT NULL THEN 'OPORTUNIDADE_RECUPERACAO'
                        WHEN c.cnpj IS NOT NULL AND v.ultima_compra IS NULL THEN 'INATIVO_BAIXO_POTENCIAL'
                        WHEN GREATEST(DATE_DIFF(CURRENT_DATE(), v.ultima_compra, DAY), 0) > 90 THEN 'RISCO_INATIVACAO'
                        WHEN GREATEST(DATE_DIFF(CURRENT_DATE(), v.ultima_compra, DAY), 0) <= 30 THEN 'ATIVO_FREQUENTE'
                        WHEN v.ultima_compra IS NOT NULL THEN 'ATIVO_REGULAR'
                        ELSE 'SEM_HISTORICO'
                    END AS classificacao_risco
                FROM carteira c FULL OUTER JOIN vendas_agg v ON c.cnpj = v.cnpj CROSS JOIN ticket_medio_ativos t ORDER BY v.valor_total DESC NULLS LAST
            ";
            $jobConfig = $client->query($query)->parameters(['fantasia_pads' => $fantasiaPads]);
            $results = $client->runQuery($jobConfig);
            $clients = [];
            foreach ($results as $row) { $clients[] = ['cnpj' => $row['cnpj_original'], 'razao' => $row['razao'], 'status_carteira' => $row['status_carteira'], 'segmento' => $row['segmento'], 'uf' => $row['uf'], 'municipio' => $row['municipio'], 'telefone' => $row['telefone'], 'email' => $row['email'], 'total_pedidos' => (int) $row['total_pedidos'], 'valor_total' => (float) $row['valor_total'], 'ultima_compra' => $row['ultima_compra'], 'primeira_compra' => $row['primeira_compra'], 'ticket_medio' => (float) $row['ticket_medio'], 'dias_sem_compra' => (int) ($row['dias_sem_compra'] ?? 0), 'classificacao_risco' => $row['classificacao_risco'], 'ticket_threshold' => (float) ($row['ticket_threshold'] ?? 0)]; }
            return $clients;
        });
    }

    public function getActiveCnpjs($emails): array
    {
        if (!$this->isEnabled()) return [];
        $emails = is_string($emails) ? [$emails] : $emails;
        $cacheKey = "bq_active_cnpjs_" . md5(json_encode($emails));
        return Cache::remember($cacheKey, now()->addHours(4), function () use ($emails) {
            $projectId = $this->config['project_id'];
            $fantasiaPadQuery = "SELECT DISTINCT FANTASIA_PAD FROM `{$projectId}.VENDAS.VendasHistoricasDois` WHERE LOWER(EMAIL_REP) IN UNNEST(@emails)";
            $client = $this->getClient();
            if (!$client) return [];
            $lowerEmails = array_map('strtolower', $emails);
            $jobConfig = $client->query($fantasiaPadQuery)->parameters(['emails' => $lowerEmails]);
            $results = $client->runQuery($jobConfig);
            $fantasiaPads = [];
            foreach ($results as $row) { if (!empty($row['FANTASIA_PAD'])) $fantasiaPads[] = $row['FANTASIA_PAD']; }
            if (empty($fantasiaPads)) return [];
            $query = "SELECT DISTINCT REGEXP_REPLACE(REGEXP_REPLACE(COD_ORIGEM, r\"['\\.\-/]\", ''), r'[^0-9]', '') AS cnpj FROM `{$projectId}.VENDAS.CarteiraGeral` WHERE FANTASIA_PAD IN UNNEST(@fantasia_pads) AND Status_Carteira = 'ATIVO'";
            $jobConfig = $client->query($query)->parameters(['fantasia_pads' => $fantasiaPads]);
            $results = $client->runQuery($jobConfig);
            $cnpjs = [];
            foreach ($results as $row) { if (!empty($row['cnpj'])) $cnpjs[] = $row['cnpj']; }
            return $cnpjs;
        });
    }

    /**
     * Get client orders from BigQuery.
     * Returns all orders for a specific CNPJ (without formatting, numbers only).
     *
     * @param  string  $cnpj
     * @return array
     */
    public function getClientOrders($cnpj): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        if (!$cnpj) {
            return [];
        }

        $projectId = $this->config['project_id'];
        $client = $this->getClient();

        if (!$client) {
            return [];
        }

        try {
            // Query to get all orders for this CNPJ
            // COD_ORIGEM in VendasHistoricasDois is the CNPJ with formatting, so we need to clean it
            $query = "
                SELECT
                    EMISSAO as data_emissao,
                    PEDIDO as pedido,
                    CODIGO_PRODUTO as codigo_produto,
                    DESCRICAO_PRODUTO as produto,
                    SAFE_CAST(QUANTIDADE AS FLOAT64) as quantidade,
                    SAFE_CAST(TOTAL_ITEM AS FLOAT64) as valor_total
                FROM `{$projectId}.VENDAS.VendasHistoricasDois`
                WHERE COD_ORIGEM = @cnpj
                ORDER BY PARSE_DATE('%Y-%m-%d', SUBSTR(EMISSAO, 1, 10)) DESC
            ";

            $jobConfig = $client->query($query)->parameters(['cnpj' => $cnpj]);
            $results = $client->runQuery($jobConfig);

            $orders = [];
            foreach ($results as $row) {
                $orders[] = [
                    'data_emissao' => $row['data_emissao'],
                    'pedido' => $row['pedido'],
                    'produto' => $row['produto'],
                    'quantidade' => (int) $row['quantidade'],
                    'valor_total' => (float) $row['valor_total'],
                ];
            }

            return $orders;

        } catch (Exception $e) {
            Log::error('Get Client Orders Error: ' . $e->getMessage());
            return [];
        }
    }
}
