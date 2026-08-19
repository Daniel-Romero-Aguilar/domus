<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\AdminAbilities;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class AdminFamilyReadService
{
    public function paginate(Admin $admin, int $perPage = 6): LengthAwarePaginator
    {
        $this->authorize($admin);

        $parents = User::query()
            ->select(['id', 'name', 'email', 'username', 'role', 'created_at'])
            ->where('role', 'parent')
            ->with('balance:id,user_id,amount')
            ->latest('created_at')
            ->latest('id')
            ->paginate(max(1, min($perPage, 20)))
            ->withQueryString();

        $parentIds = $parents->getCollection()->pluck('id');
        $membersByParent = $parentIds->isEmpty()
            ? collect()
            : FamilyMember::query()
                ->select([
                    'id',
                    'parent_user_id',
                    'user_id',
                    'is_minor',
                    'guardian_declaration_accepted',
                    'created_at',
                ])
                ->whereIn('parent_user_id', $parentIds)
                ->with([
                    'user' => fn ($query) => $query
                        ->select(['id', 'name', 'email', 'username', 'role', 'created_at'])
                        ->with('balance:id,user_id,amount'),
                ])
                ->orderBy('parent_user_id')
                ->orderBy('id')
                ->get()
                ->groupBy('parent_user_id');

        foreach ($parents->getCollection() as $parent) {
            $parent->setRelation(
                'adminFamilyMembers',
                $membersByParent->get($parent->id, collect())->values()
            );
        }

        return $parents;
    }

    public function totalMembers(Admin $admin): int
    {
        $this->authorize($admin);

        return FamilyMember::query()->count();
    }

    private function authorize(Admin $admin): void
    {
        Gate::forUser($admin)->authorize(AdminAbilities::VIEW_FAMILY_OVERVIEW);
    }
}
