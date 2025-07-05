<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles')->ignore($this->role),
            ],
            'description' => 'sometimes|nullable|string|max:500',
            'status' => 'sometimes|in:active,inactive',
            'permission_ids' => 'sometimes|array',
            'permission_ids.*' => 'exists:permissions,id',
        ];
    }
}
