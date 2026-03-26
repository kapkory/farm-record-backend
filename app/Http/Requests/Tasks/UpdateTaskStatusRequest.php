<?php

namespace App\Http\Requests\Tasks;

use App\Models\Core\Task;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $valid = implode(',', array_keys(Task::STATUS_LABELS)); // "1,2,3,4,5"

        return [
            'task_status' => "required|integer|in:{$valid}",
        ];
    }
}
