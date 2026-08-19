<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Services\Admin\AdminFamilyOverviewService;
use App\Support\AdminAbilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminFamilyOverviewController extends Controller
{
    public function __invoke(Request $request, AdminFamilyOverviewService $overview): View
    {
        $admin = Auth::guard('admin_web')->user();
        abort_unless($admin instanceof Admin, 403);

        Gate::forUser($admin)->authorize(AdminAbilities::VIEW_FAMILY_OVERVIEW);

        return view('admin-family-overview', [
            'admin' => $admin,
            ...$overview->overview($admin, 6),
        ]);
    }
}
