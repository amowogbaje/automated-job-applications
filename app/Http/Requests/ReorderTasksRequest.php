<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'task_ids.*' => ['required', 'integer', 'distinct', Rule::exists('tasks', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')],
        ];
    }
}
