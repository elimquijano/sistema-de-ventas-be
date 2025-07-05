<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->user),
            ],
            'password' => 'sometimes|nullable|string|min:8',
            'status' => 'sometimes|in:active,inactive,pending',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'exists:roles,id',
        ];
    }
}
