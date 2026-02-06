<?php

namespace Webkul\Admin\Http\Controllers;

use Webkul\Admin\Helpers\Dashboard;

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
    public function __construct(protected Dashboard $dashboardHelper)
    {
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
        if ($bigQueryService->isEnabled()) {
            $role = $bigQueryService->determineUserRole($user->email);

            if ($role == 'gerente') {
                $subordinateEmails = $bigQueryService->getSubordinates($user->email);

                // Ensure manager is in the list
                $subordinateEmails[] = $user->email;

                $users = app(\Webkul\User\Repositories\UserRepository::class)
                    ->findWhereIn('email', array_unique($subordinateEmails));
            } elseif ($role == 'vendedor') {
                $users = [];
                $defaultUserId = $user->id;
            } else {
                // Admin - sees everyone
                $users = app(\Webkul\User\Repositories\UserRepository::class)->all();
            }
        } else {
            $users = match ($user->view_permission) {
                'global', 'all' => app(\Webkul\User\Repositories\UserRepository::class)->all(),
                'group' => app(\Webkul\User\Repositories\UserRepository::class)->findWhereIn('id', app(\Webkul\User\Repositories\UserRepository::class)->getCurrentUserGroupsUserIds()),
                default => [],
            };

            if ($user->view_permission == 'individual') {
                $defaultUserId = $user->id;
            }
        }

        return view('admin::dashboard.index')->with([
            'startDate' => $this->dashboardHelper->getStartDate(),
            'endDate' => $this->dashboardHelper->getEndDate(),
            'users' => $users,
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
