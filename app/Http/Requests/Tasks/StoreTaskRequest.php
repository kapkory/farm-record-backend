<?php

namespace App\Http\Requests\Tasks;

use App\Models\Core\Task;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validStatuses = implode(',', array_keys(Task::STATUS_LABELS));   // "1,2,3,4,5"
        $validPriorities = implode(',', array_keys(Task::PRIORITY_LABELS)); // "1,2,3,4"

        return [
            'uuid' => 'nullable|uuid',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to_user_id' => 'nullable|integer|exists:users,id',
            'due_date' => 'nullable|date',
            'priority' => "nullable|integer|in:{$validPriorities}",
            'task_status' => "nullable|integer|in:{$validStatuses}",
            'parent_task_id' => 'nullable|integer|exists:tasks,id',
            'taskable_type' => 'nullable|string|in:planting,farm,treatment,animal_group,animal',
            'taskable_uuid' => 'nullable|uuid',
            'record_expense' => 'sometimes|boolean',
            'expense_amount' => 'nullable|numeric|min:0.01|required_if:record_expense,1',
        ];
    }
}
