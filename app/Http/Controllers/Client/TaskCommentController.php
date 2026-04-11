<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreTaskCommentRequest;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\RedirectResponse;

class TaskCommentController extends Controller
{
    public function store(StoreTaskCommentRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('create', [TaskComment::class, $task]);

        $task->comments()->create([
            'author_id' => $request->user()->id,
            'content' => $request->validated('content'),
            'attachments' => $request->validated('attachments'),
        ]);

        return back()->with('success', 'Комментарий добавлен.');
    }
}
