<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Loan;
use App\Support\AdminAbilities;
use App\Support\BalanceHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminLoanReadService
{
    private const OPEN_STATUSES = ['pending', 'offered', 'approved'];

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

        $statusRows = Loan::query()
            ->whereIn('parent_user_id', $parentIds)
            ->select(['parent_user_id', 'status'])
            ->selectRaw('COUNT(*) as aggregate_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as aggregate_amount')
            ->groupBy('parent_user_id', 'status')
            ->get();

        foreach ($statusRows as $row) {
            $parentId = (int) $row->parent_user_id;
            $status = (string) $row->status;
            $count = (int) $row->aggregate_count;
            $amount = BalanceHelper::parseMoneyToCents($row->aggregate_amount);

            $result[$parentId]['summary']['total'] += $count;
            $result[$parentId]['summary']['total_amount'] += $amount;
            $result[$parentId]['summary']['by_status'][$status] = $count;

            if (in_array($status, self::OPEN_STATUSES, true)) {
                $result[$parentId]['summary']['open'] += $count;
                $result[$parentId]['summary']['open_amount'] += $amount;
            }
        }

        foreach ($result as &$parentResult) {
            $parentResult['summary']['total_amount'] /= 100;
            $parentResult['summary']['open_amount'] /= 100;
        }
        unset($parentResult);

        $rankedLoans = DB::table('loans')
            ->whereIn('parent_user_id', $parentIds)
            ->select([
                'id',
                'parent_user_id',
                'child_user_id',
                'amount',
                'total_amount',
                'status',
                'due_date',
                'installments_count',
                'created_at',
            ])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY parent_user_id ORDER BY created_at DESC, id DESC) as admin_row_number');

        $recentByParent = DB::query()
            ->fromSub($rankedLoans, 'admin_ranked_loans')
            ->leftJoin('users as admin_loan_children', 'admin_loan_children.id', '=', 'admin_ranked_loans.child_user_id')
            ->where('admin_ranked_loans.admin_row_number', '<=', self::RECENT_PER_PARENT)
            ->orderBy('admin_ranked_loans.parent_user_id')
            ->orderBy('admin_ranked_loans.admin_row_number')
            ->get([
                'admin_ranked_loans.id',
                'admin_ranked_loans.parent_user_id',
                'admin_ranked_loans.child_user_id',
                'admin_ranked_loans.amount',
                'admin_ranked_loans.total_amount',
                'admin_ranked_loans.status',
                'admin_ranked_loans.due_date',
                'admin_ranked_loans.installments_count',
                'admin_ranked_loans.created_at',
                'admin_loan_children.name as child_name',
                'admin_loan_children.username as child_username',
            ])
            ->groupBy('parent_user_id');

        foreach ($result as $parentId => &$parentResult) {
            $parentResult['recent'] = $recentByParent->get($parentId, collect())->values();
        }
        unset($parentResult);

        return $result;
    }

    public function totalOpen(Admin $admin): int
    {
        $this->authorize($admin);

        return Loan::query()->whereIn('status', self::OPEN_STATUSES)->count();
    }

    /** @return array{total:int,open:int,total_amount:int|float,open_amount:int|float,by_status:array<string,int>} */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'open' => 0,
            'total_amount' => 0,
            'open_amount' => 0,
            'by_status' => [],
        ];
    }

    private function authorize(Admin $admin): void
    {
        Gate::forUser($admin)->authorize(AdminAbilities::VIEW_FAMILY_OVERVIEW);
    }
}
