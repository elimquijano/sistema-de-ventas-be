<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('modules')->ignore($this->module),
            ],
            'description' => 'sometimes|string|max:500',
            'icon' => 'sometimes|string|max:255',
            'sort_order' => 'sometimes|integer|min:0',
            'parent_id' => 'sometimes|nullable|exists:modules,id',
            'type' => 'sometimes|in:module,group,page,button',
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
