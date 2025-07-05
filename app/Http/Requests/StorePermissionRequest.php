<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255|unique:permissions',
            'display_name' => 'required|string|max:255',
            'module' => 'required|string|max:255',
            'type' => 'sometimes|in:view,create,edit,delete,manage',
            'description' => 'sometimes|string|max:500',
        ];
    }
}
