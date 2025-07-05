<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModuleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:modules',
            'description' => 'sometimes|string|max:500',
            'icon' => 'sometimes|string|max:255',
            'sort_order' => 'sometimes|integer|min:0',
            'parent_id' => 'sometimes|nullable|exists:modules,id',
            'type' => 'sometimes|in:module,group,page,button',
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
