<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\SavingsBox;
use App\Support\AdminAbilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminSavingsBoxReadService
{
    private const RECENT_PER_PARENT = 4;

    /** @param array<int, int> $parentIds */
    public function forParents(Admin $admin, array $parentIds): array
    {
        $this->authorize($admin);
        $parentIds = collect($parentIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($parentIds->isEmpty()) {
            return [];
        }

        $result = $parentIds
            ->mapWithKeys(fn (int $parentId): array => [$parentId => [
                'summary' => $this->emptySummary(),
                'recent' => collect(),
            ]])
            ->all();

        $accountTotals = $this->accountTotalsQuery();
        $statusRows = DB::table('savings_boxes as admin_boxes')
            ->leftJoinSub($accountTotals, 'admin_box_accounts', function ($join): void {
                $join->on('admin_box_accounts.savings_box_id', '=', 'admin_boxes.id');
            })
            ->whereIn('admin_boxes.parent_user_id', $parentIds)
            ->groupBy('admin_boxes.parent_user_id', 'admin_boxes.status')
            ->get([
                'admin_boxes.parent_user_id',
                'admin_boxes.status',
                DB::raw('COUNT(*) as aggregate_count'),
                DB::raw('COALESCE(SUM(COALESCE(admin_box_accounts.account_count, 0)), 0) as aggregate_accounts'),
                DB::raw('COALESCE(SUM(COALESCE(admin_box_accounts.held_principal_cents, 0)), 0) as aggregate_held_cents'),
                DB::raw('COALESCE(SUM(COALESCE(admin_box_accounts.settled_benefit_cents, 0)), 0) as aggregate_benefit_cents'),
            ]);

        foreach ($statusRows as $row) {
            $parentId = (int) $row->parent_user_id;
            $status = (string) $row->status;
            $count = (int) $row->aggregate_count;

            $result[$parentId]['summary']['total'] += $count;
            $result[$parentId]['summary']['accounts'] += (int) $row->aggregate_accounts;
            $result[$parentId]['summary']['held_principal_cents'] += (int) $row->aggregate_held_cents;
            $result[$parentId]['summary']['settled_benefit_cents'] += (int) $row->aggregate_benefit_cents;
            $result[$parentId]['summary']['by_status'][$status] = $count;

            if ($status === 'active') {
                $result[$parentId]['summary']['active'] += $count;
            }
        }

        $rankedBoxes = DB::table('savings_boxes as admin_recent_boxes')
            ->leftJoinSub($this->accountTotalsQuery(), 'admin_recent_accounts', function ($join): void {
                $join->on('admin_recent_accounts.savings_box_id', '=', 'admin_recent_boxes.id');
            })
            ->whereIn('admin_recent_boxes.parent_user_id', $parentIds)
            ->select([
                'admin_recent_boxes.id',
                'admin_recent_boxes.parent_user_id',
                'admin_recent_boxes.name',
                'admin_recent_boxes.status',
                'admin_recent_boxes.audience',
                'admin_recent_boxes.duration_months',
                'admin_recent_boxes.cycle_days',
                'admin_recent_boxes.max_per_cycle_cents',
                'admin_recent_boxes.max_amount_cents',
                'admin_recent_boxes.benefit_percent',
                'admin_recent_boxes.cutoff_at',
                'admin_recent_boxes.delivery_date',
                'admin_recent_boxes.created_at',
                DB::raw('COALESCE(admin_recent_accounts.account_count, 0) as account_count'),
                DB::raw('COALESCE(admin_recent_accounts.held_principal_cents, 0) as held_principal_cents'),
                DB::raw('COALESCE(admin_recent_accounts.settled_benefit_cents, 0) as settled_benefit_cents'),
            ])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY admin_recent_boxes.parent_user_id ORDER BY admin_recent_boxes.created_at DESC, admin_recent_boxes.id DESC) as admin_row_number');

        $recentByParent = DB::query()
            ->fromSub($rankedBoxes, 'admin_ranked_boxes')
            ->where('admin_ranked_boxes.admin_row_number', '<=', self::RECENT_PER_PARENT)
            ->orderBy('admin_ranked_boxes.parent_user_id')
            ->orderBy('admin_ranked_boxes.admin_row_number')
            ->get()
            ->groupBy('parent_user_id');

        foreach ($result as $parentId => &$parentResult) {
            $parentResult['recent'] = $recentByParent->get($parentId, collect())->values();
        }
        unset($parentResult);

        return $result;
    }

    public function totalActive(Admin $admin): int
    {
        $this->authorize($admin);

        return SavingsBox::query()->where('status', 'active')->count();
    }

    private function accountTotalsQuery()
    {
        return DB::table('savings_box_accounts')
            ->select('savings_box_id')
            ->selectRaw('COUNT(*) as account_count')
            ->selectRaw('COALESCE(SUM(principal_cents + principal_pending_cents), 0) as held_principal_cents')
            ->selectRaw('COALESCE(SUM(settled_benefit_cents), 0) as settled_benefit_cents')
            ->groupBy('savings_box_id');
    }

    /** @return array{total:int,active:int,accounts:int,held_principal_cents:int,settled_benefit_cents:int,by_status:array<string,int>} */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'accounts' => 0,
            'held_principal_cents' => 0,
            'settled_benefit_cents' => 0,
            'by_status' => [],
        ];
    }

    private function authorize(Admin $admin): void
    {
        Gate::forUser($admin)->authorize(AdminAbilities::VIEW_FAMILY_OVERVIEW);
    }
}
