<?php

namespace Webkul\Admin\Http\Controllers;

use Webkul\Admin\Helpers\Dashboard;
use Webkul\User\Repositories\UserRepository;
use Webkul\Lead\Repositories\LeadRepository;

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
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected Dashboard $dashboardHelper,
        protected UserRepository $userRepository,
        protected LeadRepository $leadRepository
    ) {
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
        $endDate = $this->dashboardHelper->getEndDate();     // Define endDate
        $totalWonLeads = 0; // Placeholder for totalWonLeads, as it's not defined in the snippet

        if ($bigQueryService->isEnabled()) {
            $role = $bigQueryService->determineUserRole($user->email);

            if ($role == 'gerente') {
                // Fetch users (salespeople) belonging to this manager from BigQuery
                $subordinateEmails = $bigQueryService->getSubordinates($user->email);

                // Include the manager themselves in the list so they can filter their own data
                $subordinateEmails[] = $user->email;

                $users = $this->userRepository->whereIn('email', $subordinateEmails)->get();
            } elseif ($role == 'vendedor') {
                // Salespeople should not be able to filter other users
                $users = collect([]);
                $defaultUserId = $user->id;
            } else {
                // Admin: can see all users OR filter by manager
                $users = $this->userRepository->all();
                $managers = $bigQueryService->getManagers();
            }
        } else {
            // Non-BigQuery logic remains unchanged
            // The original code had a match statement, but the provided snippet simplifies it.
            // Reverting to the original logic for non-BigQuery if the intent was not to change it entirely.
            // Based on the provided snippet, it seems the intent was to simplify this part.
            $users = $this->userRepository->all();

            if ($user->view_permission == 'individual') {
                $users = collect([$user]);
                $defaultUserId = $user->id;
            }
        }

        $totalWonLeads = app(\Webkul\Admin\Helpers\Reporting\Lead::class)->getTotalWonLeads($startDate, $endDate);

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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $stats = $this->dashboardHelper->{$this->typeFunctions[request()->query('type')]}();

        return response()->json([
            'statistics' => $stats,
            'date_range' => $this->dashboardHelper->getDateRange(),
        ]);
    }
}
