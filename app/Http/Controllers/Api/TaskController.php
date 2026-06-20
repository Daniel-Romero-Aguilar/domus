<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\Task;
use App\Services\DomusAchievementService;
use App\Services\DomusNotificationService;
use App\Support\BalanceHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function __construct(
        private readonly DomusNotificationService $notifications,
        private readonly DomusAchievementService $achievements,
    )
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
            ->with(['acceptedBy:id,name', 'completedBy:id,name'])
            ->where('parent_user_id', $parent->id)
            ->latest()
            ->get();

        $balance = Balance::query()->firstOrCreate(
            ['user_id' => $parent->id],
            ['amount' => 0]
        );

        return response()->json([
            'tasks' => $tasks,
            'available_balance_cents' => BalanceHelper::parentMoneyUsedCents($parent),
        ]);
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
            'reward_points' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $payload = DB::transaction(function () use ($parent, $validated) {
            $balance = Balance::query()
                ->where('user_id', $parent->id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = Balance::create([
                    'user_id' => $parent->id,
                    'amount' => 0,
                ]);

                $balance = Balance::query()
                    ->where('user_id', $parent->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $rewardAmountCents = (int) $validated['reward_amount'] * 100;

            $task = Task::create([
                'parent_user_id' => $parent->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'reward_amount' => (int) $validated['reward_amount'],
                'reward_points' => (int) $validated['reward_points'],
                'status' => 'open',
            ]);

            $parentMoneyUsedBefore = BalanceHelper::parentMoneyUsedCents($parent);
            $parentMoneyUsedAfter = $parentMoneyUsedBefore + $rewardAmountCents;

            $balance->movements()->create([
                'amount_added' => $rewardAmountCents,
                'movement_type' => 'task_reserve',
                'note' => 'Task reward registered: '.$task->name,
                'resulting_balance' => $parentMoneyUsedAfter,
            ]);

            $this->notifications->recordForParent(
                $parent->id,
                'creacion',
                'tareas',
                'Creaste la tarea '.$task->name.'.'
            );

            return [
                'task' => $task,
                'available_balance_cents' => $parentMoneyUsedAfter,
            ];
        });

        if (isset($payload['error'])) {
            return $payload['error'];
        }

        return response()->json([
            'message' => 'Task created.',
            'task' => $payload['task'],
            'available_balance_cents' => $payload['available_balance_cents'],
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
            ->whereIn('status', ['closed', 'ended', 'completed', 'canceled'])
            ->where(function ($query) use ($child) {
                $query->where('completed_by_user_id', $child->id)
                    ->orWhere('accepted_by_user_id', $child->id)
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

        $task = DB::transaction(function () use ($child, $task, $familyMember): Task {
            $task = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($task->parent_user_id !== $familyMember->parent_user_id) {
                abort(response()->json(['message' => 'You cannot accept tasks from a different parent context.'], 403));
            }

            if ($task->status !== 'open') {
                abort(response()->json(['message' => 'Task is not open for acceptance.'], 422));
            }

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

        $payload = DB::transaction(function () use ($child, $task, $familyMember) {
            $task = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($task->parent_user_id !== $familyMember->parent_user_id) {
                abort(response()->json(['message' => 'This task does not belong to your parent/admin.'], 403));
            }

            if ($task->accepted_by_user_id !== $child->id) {
                abort(response()->json(['message' => 'You can only complete tasks accepted by you.'], 422));
            }

            if (! in_array($task->status, ['accepted', 'in_progress'], true)) {
                abort(response()->json(['message' => 'Task cannot be marked as completed in the current status.'], 422));
            }

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

            return [
                'task' => $task,
                'achievements' => [],
            ];
        });

        return response()->json([
            'message' => 'Completion request sent to parent/admin.',
            'task' => $payload['task'],
            'achievements' => $payload['achievements'],
        ]);
    }

    public function parentReview(Request $request, Task $task): JsonResponse
    {
        $parent = $request->user();
        if (! $parent) {
            return response()->json(['message' => 'Unauthenticated context in /parent/tasks/{task}/review.'], 401);
        }
        if ($parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can review tasks.'], 403);
        }
        $validated = $request->validate([
            'action' => ['required', 'in:approve,retry,cancel'],
        ]);

        $payload = DB::transaction(function () use ($task, $parent, $validated) {
            $task = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($task->parent_user_id !== $parent->id) {
                abort(response()->json(['message' => 'Task does not belong to your account.'], 403));
            }

            $action = $validated['action'];

            if ($action === 'approve') {
                if ($task->status !== 'awaiting_parent_confirmation' || ! $task->accepted_by_user_id) {
                    abort(response()->json(['message' => 'Task is not awaiting parent confirmation.'], 422));
                }

                $childBalance = Balance::query()
                    ->where('user_id', $task->accepted_by_user_id)
                    ->lockForUpdate()
                    ->first();

                if (! $childBalance) {
                    $childBalance = Balance::create([
                        'user_id' => $task->accepted_by_user_id,
                        'amount' => 0,
                    ]);

                    $childBalance = Balance::query()
                        ->where('user_id', $task->accepted_by_user_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $rewardAmountCents = (int) $task->reward_amount * 100;
                $childBalance->amount = (int) $childBalance->amount + $rewardAmountCents;
                $childBalance->save();

                $childBalance->movements()->create([
                    'amount_added' => $rewardAmountCents,
                    'movement_type' => 'task_reward',
                    'note' => 'Task reward paid: '.$task->name,
                    'resulting_balance' => $childBalance->amount,
                ]);

                $task->status = 'completed';
                $task->completed_by_user_id = $task->accepted_by_user_id;
                $task->save();

                $this->notifications->recordForMember(
                    $task->accepted_by_user_id,
                    'aprobacion',
                    'tareas',
                    'Tu padre confirmo la tarea '.$task->name.' y ya recibiste tu recompensa.'
                );
                $this->notifications->recordForParent(
                    $task->parent_user_id,
                    'aprobacion',
                    'tareas',
                    'Confirmaste la tarea '.$task->name.'.'
                );

                return [
                    'message' => 'Task confirmed.',
                    'task' => $task,
                    'available_balance_cents' => null,
                    'available_child_balance_cents' => (int) $childBalance->amount,
                    'achievements' => $this->achievements->unlockFirstTaskCompletion($task->accepted_by_user_id, [
                        'task_id' => $task->id,
                        'task_name' => $task->name,
                    ]),
                ];
            }

            if ($action === 'retry') {
                if ($task->status !== 'awaiting_parent_confirmation' || ! $task->accepted_by_user_id) {
                    abort(response()->json(['message' => 'Task is not awaiting parent confirmation.'], 422));
                }

                $task->status = 'accepted';
                $task->member_completion_requested_at = null;
                $task->save();

                $this->notifications->recordForMember(
                    $task->accepted_by_user_id,
                    'revision',
                    'tareas',
                    'Tu padre devolvio la tarea '.$task->name.' para que la rehagas.'
                );
                $this->notifications->recordForParent(
                    $task->parent_user_id,
                    'revision',
                    'tareas',
                    'Devolviste la tarea '.$task->name.' para rehacer.'
                );

                return [
                    'message' => 'Task returned for retry.',
                    'task' => $task,
                    'available_balance_cents' => null,
                    'available_child_balance_cents' => null,
                    'achievements' => [],
                ];
            }

            if (in_array($task->status, ['completed', 'closed', 'ended', 'canceled'], true)) {
                abort(response()->json(['message' => 'This task can no longer be canceled.'], 422));
            }

            $parentBalance = Balance::query()
                ->where('user_id', $parent->id)
                ->lockForUpdate()
                ->first();

            if (! $parentBalance) {
                $parentBalance = Balance::create([
                    'user_id' => $parent->id,
                    'amount' => 0,
                ]);

                $parentBalance = Balance::query()
                    ->where('user_id', $parent->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $refundCents = (int) $task->reward_amount * 100;
            $parentMoneyUsedBefore = BalanceHelper::parentMoneyUsedCents($parent);
            $parentMoneyUsedAfter = max($parentMoneyUsedBefore - $refundCents, 0);

            $parentBalance->movements()->create([
                'amount_added' => $refundCents,
                'movement_type' => 'task_refund',
                'note' => 'Task canceled refund: '.$task->name,
                'resulting_balance' => $parentMoneyUsedAfter,
            ]);

            $task->status = 'canceled';
            $task->member_completion_requested_at = null;
            $task->save();

            if ($task->accepted_by_user_id) {
                $this->notifications->recordForMember(
                    $task->accepted_by_user_id,
                    'cancelacion',
                    'tareas',
                    'La tarea '.$task->name.' fue cancelada definitivamente por tu padre.'
                );
            }
            $this->notifications->recordForParent(
                $task->parent_user_id,
                'cancelacion',
                'tareas',
                'Cancelaste definitivamente la tarea '.$task->name.'.'
            );

            return [
                'message' => 'Task canceled.',
                'task' => $task,
                'available_balance_cents' => $parentMoneyUsedAfter,
                'available_child_balance_cents' => null,
                'achievements' => [],
            ];
        });

        return response()->json($payload);
    }
}
