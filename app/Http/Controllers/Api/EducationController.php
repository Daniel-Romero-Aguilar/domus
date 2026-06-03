<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;

class EducationController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::query()
            ->where('is_active', true)
            ->with(['category:id,name,slug', 'lessons' => function ($q) {
                $q->orderBy('position')->with(['parts' => function ($p) {
                    $p->orderBy('position');
                }]);
            }])
            ->orderBy('id')
            ->get();

        return response()->json(['courses' => $courses]);
    }
}
