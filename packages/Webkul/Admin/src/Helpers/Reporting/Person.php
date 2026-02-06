<?php

namespace Webkul\Admin\Helpers\Reporting;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\PersonRepository;

class Person extends AbstractReporting
{
    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(protected PersonRepository $personRepository)
    {
        parent::__construct();
    }

    /**
     * Retrieves total persons and their progress.
     */
    public function getTotalPersonsProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalPersons($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getTotalPersons($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total persons by date
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     */
    public function getTotalPersons($startDate, $endDate): int
    {
        $query = $this->personRepository
            ->resetModel()
            ->whereBetween('created_at', [$startDate, $endDate]);

        $this->applyPermissionScope($query);

        return $query->count();
    }

    /**
     * Gets top customers by revenue.
     *
     * @param  int  $limit
     */
    public function getTopCustomersByRevenue($limit = null): Collection
    {
        $bigQueryService = app('bigquery');

        if ($bigQueryService->isEnabled()) {
            try {
                $emails = $this->getFilteredUserEmails();

                $items = $bigQueryService->getTopCustomers(
                    $emails,
                    $this->startDate->format('Y-m-d'),
                    $this->endDate->format('Y-m-d'),
                    $limit
                );

                $itemEmails = collect($items)->pluck('code')->toArray();

                $localPersons = DB::table('persons')
                    ->where(function ($query) use ($itemEmails) {
                        foreach ($itemEmails as $email) {
                            $query->orWhere('emails', 'LIKE', '%' . $email . '%');
                        }
                    })
                    ->get(['id', 'emails']);

                return collect($items)->map(function ($item) use ($localPersons) {
                    $person = $localPersons->first(function ($p) use ($item) {
                        return str_contains($p->emails, $item['code']);
                    });

                    return [
                        'id' => $person ? $person->id : $item['code'],
                        'name' => $item['name'],
                        'emails' => [],
                        'contact_numbers' => [],
                        'revenue' => $item['revenue'] ?? 0,
                        'formatted_revenue' => core()->formatBasePrice($item['revenue'] ?? 0),
                    ];
                });
            } catch (\Exception $e) {
                // Log the error but don't crash - fall back to local database
                \Log::error('BigQuery getTopCustomers error: ' . $e->getMessage());
            }
        }

        $tablePrefix = DB::getTablePrefix();

        $items = $this->personRepository
            ->resetModel()
            ->leftJoin('leads', 'persons.id', '=', 'leads.person_id')
            ->select('*', 'persons.id as id')
            ->addSelect(DB::raw('SUM(' . $tablePrefix . 'leads.lead_value) as revenue'))
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->having(DB::raw('SUM(' . $tablePrefix . 'leads.lead_value)'), '>', 0)
            ->groupBy('person_id')
            ->orderBy('revenue', 'DESC')
            ->limit($limit);

        $this->applyPermissionScope($items, 'leads.user_id');

        $items = $items->get();

        $items = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'emails' => $item->emails,
                'contact_numbers' => $item->contact_numbers,
                'revenue' => $item->revenue,
                'formatted_revenue' => core()->formatBasePrice($item->revenue),
            ];
        });

        return $items;
    }
}
