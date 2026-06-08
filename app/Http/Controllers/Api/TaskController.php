<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\Task;
use App\Services\DomusNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function __construct(private readonly DomusNotificationService $notifications)
    {
    }

    public function parentIndex(Request $request): JsonResponse
    {
        $parent = $request->user();
        if (! $parent) {
            return response()->json(['message' => 'Unauthenticated context in /parent/tasks.'], 401);
        }
        if ($parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can view tasks.'], 403);
        }

        $tasks = Task::query()
            ->where('parent_user_id', $parent->id)
            ->latest()
            ->get();

        return response()->json(['tasks' => $tasks]);
    }

    public function parentStore(Request $request): JsonResponse
    {
        $parent = $request->user();
        if (! $parent) {
            return response()->json(['message' => 'Unauthenticated context in /parent/tasks.'], 401);
        }
        if ($parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can create tasks.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'reward_amount' => ['required', 'integer', 'min:0'],
            'reward_points' => ['required', 'integer', 'min:0'],
        ]);

        $task = DB::transaction(function () use ($parent, $validated): Task {
            $task = Task::create([
                'parent_user_id' => $parent->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'reward_amount' => (int) $validated['reward_amount'],
                'reward_points' => (int) $validated['reward_points'],
                'status' => 'open',
            ]);

            $this->notifications->recordForParent(
                $parent->id,
                'creacion',
                'tareas',
                'Creaste la tarea '.$task->name.'.'
            );

            return $task;
        });

        return response()->json([
            'message' => 'Task created.',
            'task' => $task,
        ], 201);
    }

    public function childIndex(Request $request): JsonResponse
    {
        $child = $request->user();
        if (! $child) {
            return response()->json(['message' => 'Unauthenticated context in /child/tasks.'], 401);
        }
        if (! in_array($child->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can view child tasks.'], 403);
        }

        $familyMember = FamilyMember::query()
            ->where('user_id', $child->id)
            ->first();

        if (! $familyMember) {
            return response()->json([
                'tasks' => [
                    'open' => [],
                    'accepted' => [],
                    'ended' => [],
                ],
            ]);
        }

        $baseQuery = Task::query()
            ->where('parent_user_id', $familyMember->parent_user_id)
            ->latest();

        $openTasks = (clone $baseQuery)
            ->where('status', 'open')
            ->get();

        $acceptedTasks = (clone $baseQuery)
            ->whereIn('status', ['accepted', 'in_progress', 'awaiting_parent_confirmation'])
            ->where('accepted_by_user_id', $child->id)
            ->get();

        $endedTasks = (clone $baseQuery)
            ->whereIn('status', ['closed', 'ended', 'completed'])
            ->where(function ($query) use ($child) {
                $query->where('completed_by_user_id', $child->id)
                    ->orWhere(function ($legacyQuery) use ($child) {
                        $legacyQuery->whereNull('completed_by_user_id')
                            ->where('accepted_by_user_id', $child->id);
                    });
            })
            ->get();

        return response()->json([
            'tasks' => [
                'open' => $openTasks,
                'accepted' => $acceptedTasks,
                'ended' => $endedTasks,
            ],
        ]);
    }

    public function childAccept(Request $request, Task $task): JsonResponse
    {
        $child = $request->user();
        if (! $child) {
            return response()->json(['message' => 'Unauthenticated context in /child/tasks/{task}/accept.'], 401);
        }
        if (! in_array($child->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can accept tasks.'], 403);
        }

        $familyMember = FamilyMember::query()
            ->where('user_id', $child->id)
            ->first();

        if (! $familyMember) {
            return response()->json(['message' => 'Family member record not found.'], 422);
        }

        if ($task->parent_user_id !== $familyMember->parent_user_id) {
            return response()->json(['message' => 'You cannot accept tasks from a different parent context.'], 403);
        }

        if ($task->status !== 'open') {
            return response()->json(['message' => 'Task is not open for acceptance.'], 422);
        }

        $task = DB::transaction(function () use ($child, $task): Task {
            $task->accepted_by_user_id = $child->id;
            $task->member_completion_requested_at = null;
            $task->status = 'accepted';
            $task->save();

            $this->notifications->recordForMember(
                $child->id,
                'aceptacion',
                'tareas',
                'Aceptaste la tarea '.$task->name.'.'
            );
            $this->notifications->recordForParent(
                $task->parent_user_id,
                'aceptacion',
                'tareas',
                $child->name.' acepto la tarea '.$task->name.'.'
            );

            return $task;
        });

        return response()->json([
            'message' => 'Task accepted.',
            'task' => $task,
        ]);
    }

    public function memberMarkCompleted(Request $request, Task $task): JsonResponse
    {
        $child = $request->user();
        if (! $child) {
            return response()->json(['message' => 'Unauthenticated context in /tasks/member/completed/{task}.'], 401);
        }
        if (! in_array($child->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can mark tasks as completed.'], 403);
        }

        $familyMember = FamilyMember::query()
            ->where('user_id', $child->id)
            ->first();

        if (! $familyMember) {
            return response()->json(['message' => 'Family member record not found.'], 422);
        }

        if ($task->parent_user_id !== $familyMember->parent_user_id) {
            return response()->json(['message' => 'This task does not belong to your parent/admin.'], 403);
        }

        if ($task->accepted_by_user_id !== $child->id) {
            return response()->json(['message' => 'You can only complete tasks accepted by you.'], 422);
        }

        if (! in_array($task->status, ['accepted', 'in_progress', 'awaiting_parent_confirmation'], true)) {
            return response()->json(['message' => 'Task cannot be marked as completed in the current status.'], 422);
        }

        $task = DB::transaction(function () use ($child, $task): Task {
            $task->status = 'awaiting_parent_confirmation';
            $task->member_completion_requested_at = now();
            $task->save();

            $this->notifications->recordForMember(
                $child->id,
                'completada',
                'tareas',
                'Marcaste como completada la tarea '.$task->name.'.'
            );
            $this->notifications->recordForParent(
                $task->parent_user_id,
                'revision',
                'tareas',
                $child->name.' pidio revision de la tarea '.$task->name.'.'
            );

            return $task;
        });

        return response()->json([
            'message' => 'Completion request sent to parent/admin.',
            'task' => $task,
        ]);
    }
}
