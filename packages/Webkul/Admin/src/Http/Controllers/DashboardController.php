<?php
// Test edit DashboardController

namespace Webkul\Admin\Http\Controllers;

use Webkul\Admin\Helpers\Dashboard;
use Webkul\User\Repositories\UserRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Admin\Helpers\Reporting\Lead as LeadReporting;

class DashboardController extends Controller
{
    /**
     * Request param functions
     *
     * @var array
     */
    protected $typeFunctions = [
        'over-all' => 'getOverAllStats',
        'revenue-stats' => 'getRevenueStats',
        'total-leads' => 'getTotalLeadsStats',
        'revenue-by-sources' => 'getLeadsStatsBySources',
        'revenue-by-types' => 'getLeadsStatsByTypes',
        'top-selling-products' => 'getTopSellingProducts',
        'top-persons' => 'getTopPersons',
        'open-leads-by-states' => 'getOpenLeadsByStates',
        'client-risk-analysis' => 'getClientRiskStats',
        'toggle-client-check' => 'toggleClientCheck',
        'check-history' => 'getCheckHistory',
        'checked-clients' => 'getCheckedClients',
        'create-lead-from-client' => 'createLeadFromClient',
        'client-orders' => 'getClientOrders',
    ];


    /**
     * UserRepository object
     *
     * @var \Webkul\User\Repositories\UserRepository
     */
    protected $userRepository;

    /**
     * LeadRepository object
     *
     * @var \Webkul\Lead\Repositories\LeadRepository
     */
    protected $leadRepository;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected
        Dashboard $dashboardHelper,
        UserRepository $userRepository,
        LeadRepository $leadRepository
        )
    {
        $this->userRepository = $userRepository;
        $this->leadRepository = $leadRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = auth()->user();
        $bigQueryService = app('bigquery');

        $defaultUserId = null;
        $managers = [];
        $users = collect([]); // Initialize users as a collection
        $startDate = $this->dashboardHelper->getStartDate(); // Define startDate
        $endDate = $this->dashboardHelper->getEndDate(); // Define endDate

        if ($bigQueryService->isEnabled()) {
            $role = $bigQueryService->determineUserRole($user->email);
            $userRegional = $this->getUserRegional($user->email);

            if ($role == 'gerente') {
                // Fetch users (salespeople) belonging to this manager from BigQuery
                $subordinateEmails = $bigQueryService->getSubordinates($user->email);

                // Include the manager themselves in the list so they can filter their own data
                $subordinateEmails[] = $user->email;

                $users = $this->userRepository->whereIn('email', $subordinateEmails)->get();

                // Format each user name to include regional info
                foreach ($users as $u) {
                    $u->name = $u->name . ($userRegional ? ' - ' . $userRegional : '');
                }

                // If manager, they only see their regional's people, so no need for general manager filter
                $managers = [];
            }
            else {
                // Vendedor or other: Salespeople see only themselves
                // Format name with regional info
                $user->name = $user->name . ($userRegional ? ' - ' . $userRegional : '');
                $users = collect([$user]);
                $managers = [];
                $defaultUserId = $user->id;
            }
        }
        else {
            // Non-BigQuery logic (Standard Krayin)
            if ($user->view_permission == 'individual') {
                $users = collect([$user]);
                $managers = [];
                $defaultUserId = $user->id;
            }
            else {
                $users = $this->userRepository->all();
            }
        }

        $totalWonLeads = app(LeadReporting::class)->getWonLeadsCount($startDate, $endDate);

        return view('admin::dashboard.index')->with([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalWonLeads' => $totalWonLeads,
            'users' => $users,
            'managers' => $managers,
            'defaultUserId' => $defaultUserId,
        ]);
    }

    /**
     * Get user regional info from BigQuery (Local Fallback).
     * 
     * @param  string  $email
     * @return string|null
     */
    private function getUserRegional($email)
    {
        $bigQueryService = app('bigquery');
        $email = strtolower($email);

        return \Illuminate\Support\Facades\Cache::remember('bq_regional_' . $email, 3600, function () use ($bigQueryService, $email) {
            $projectId = env('BIGQUERY_PROJECT_ID');
            $dataset = env('BIGQUERY_REVENUE_DATASET');
            $table = env('BIGQUERY_REVENUE_TABLE');

            if (!$dataset || !$table) {
                return null;
            }

            $query = "SELECT ANY_VALUE(GERENCIA_REGIONAL) as regional 
                      FROM `{$projectId}.{$dataset}.{$table}`
                      WHERE LOWER(EMAIL_REP) = @email OR LOWER(EMAIL_REGIONAL_METAS) = @email LIMIT 1";

            $results = $bigQueryService->runQuery($query, ['email' => $email]);

            return $results[0]['regional'] ?? null;
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $stats = $this->dashboardHelper->{ $this->typeFunctions[request()->query('type')]}();

        return response()->json([
            'statistics' => $stats,
            'date_range' => $this->dashboardHelper->getDateRange(),
        ]);
    }
}
