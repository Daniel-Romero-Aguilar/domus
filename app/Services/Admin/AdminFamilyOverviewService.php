<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Support\AdminAbilities;
use Illuminate\Support\Facades\Gate;

class AdminFamilyOverviewService
{
    public function __construct(
        private readonly AdminFamilyReadService $families,
        private readonly AdminLoanReadService $loans,
        private readonly AdminSavingsBoxReadService $savingsBoxes
    ) {}

    /** @return array<string, mixed> */
    public function overview(Admin $admin, int $perPage = 6): array
    {
        Gate::forUser($admin)->authorize(AdminAbilities::VIEW_FAMILY_OVERVIEW);

        $families = $this->families->paginate($admin, $perPage);
        $parentIds = $families->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $loansByParent = $this->loans->forParents($admin, $parentIds);
        $boxesByParent = $this->savingsBoxes->forParents($admin, $parentIds);

        $families->setCollection(
            $families->getCollection()->map(function ($parent) use ($loansByParent, $boxesByParent): array {
                return [
                    'parent' => $parent,
                    'members' => $parent->getRelation('adminFamilyMembers'),
                    'loans' => $loansByParent[$parent->id] ?? ['summary' => [], 'recent' => collect()],
                    'savings_boxes' => $boxesByParent[$parent->id] ?? ['summary' => [], 'recent' => collect()],
                ];
            })
        );

        return [
            'families' => $families,
            'platformSummary' => [
                'parents' => $families->total(),
                'members' => $this->families->totalMembers($admin),
                'open_loans' => $this->loans->totalOpen($admin),
                'active_savings_boxes' => $this->savingsBoxes->totalActive($admin),
            ],
        ];
    }
}
