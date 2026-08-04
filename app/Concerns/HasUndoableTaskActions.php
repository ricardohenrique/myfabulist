<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Exceptions\DomainException;
use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\TaskService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;

/**
 * The undo bar's state and behaviour (S7/Step 4), shared identically by
 * Tasks\TaskPanel and Tasks\StarredPanel. Undo covers only reversible,
 * idempotent state changes — complete, move and star (C4). A delete never
 * calls rememberLastAction(), so it never offers an undo; M7 keeps its
 * `wire:confirm` instead (see the plan's C4 for why a hard-deleted row
 * cannot be honestly "undone" today). The bar holds exactly one action —
 * undo is a safety net for the last thing you did, not a history stack
 * (C5) — and is rendered by <x-tasks.undo-bar>, never a Flux toast, which
 * has no action-button slot.
 */
trait HasUndoableTaskActions
{
    /**
     * `at` is a render-time uniqueness token only (never read for undo
     * logic) — it lets <x-tasks.undo-bar>'s wire:key change on every new
     * action so Alpine remounts and its auto-dismiss timer restarts, rather
     * than a stale timer from a previous action still counting down.
     *
     * @var array{type: string, taskId: int, payload: array<string, mixed>, at: float}|null
     */
    public ?array $lastAction = null;

    /**
     * Reverse the last remembered action. Idempotent service calls mean a
     * double-click cannot corrupt anything. A missing/foreign task (deleted
     * since, or belonging to someone else) is refused the same way every
     * other action here is — authorizedTask()'s 404/403.
     */
    public function undo(TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        if ($this->lastAction === null) {
            return;
        }

        ['type' => $type, 'taskId' => $taskId, 'payload' => $payload] = $this->lastAction;

        $task = $this->authorizedTask($taskId, $tasks, 'update');

        try {
            match ($type) {
                'complete' => $taskService->restore($task),
                'star' => $taskService->setStarred($task, (bool) ($payload['wasStarred'] ?? false)),
                'move' => $taskService->move($task, Auth::user(), (int) $payload['fromListId'], null),
                default => null,
            };
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->lastAction = null;
        unset($this->tasks);
    }

    /**
     * Dismiss the bar without reversing anything — called by its close
     * button and, client-side, by the Alpine auto-dismiss timer.
     */
    public function dismissUndo(): void
    {
        $this->lastAction = null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function rememberLastAction(string $type, int $taskId, array $payload = []): void
    {
        $this->lastAction = [
            'type' => $type,
            'taskId' => $taskId,
            'payload' => $payload,
            'at' => microtime(true),
        ];
    }

    abstract protected function authorizedTask(int $taskId, TaskRepositoryInterface $tasks, string $ability): Task;
}
