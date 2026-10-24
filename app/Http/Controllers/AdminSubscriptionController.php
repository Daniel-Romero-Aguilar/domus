<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminSubscriptionController extends Controller
{
    public function __invoke(): View
    {
        $admin = Auth::guard('admin_web')->user();
        abort_unless($admin instanceof Admin, 403);

        return view('admin-subscriptions', [
            'admin' => $admin,
        ]);
    }
}
