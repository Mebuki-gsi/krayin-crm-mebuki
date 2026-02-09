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
    public function getCheckHistory(): array
    {
        $user = auth()->user();
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();

        // Get daily counts
        $dailyCounts = \Webkul\Core\Models\ClientWorkTracking::where('user_id', $user->id)
            ->where('is_checked', true)
            ->whereBetween('checked_at', [$startDate, $endDate->endOfDay()])
            ->selectRaw('DATE(checked_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(checked_at)')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing dates with 0
        $allDates = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');
            $allDates[$dateStr] = $dailyCounts[$dateStr] ?? 0;
            $current->addDay();
        }

        // Get today's count
        $todayCount = \Webkul\Core\Models\ClientWorkTracking::where('user_id', $user->id)
            ->where('is_checked', true)
            ->whereDate('checked_at', now()->toDateString())
            ->count();

        // Get total for period
        $totalPeriod = array_sum($allDates);

        return [
            'daily_counts' => $allDates,
            'today_count' => $todayCount,
            'total_period' => $totalPeriod,
            'labels' => array_keys($allDates),
            'data' => array_values($allDates),
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
}
