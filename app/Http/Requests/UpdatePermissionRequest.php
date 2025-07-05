<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
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
                Rule::unique('permissions')->ignore($this->permission),
            ],
            'display_name' => 'sometimes|string|max:255',
            'module' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:view,create,edit,delete,manage',
            'description' => 'sometimes|string|max:500',
        ];
    }
}
