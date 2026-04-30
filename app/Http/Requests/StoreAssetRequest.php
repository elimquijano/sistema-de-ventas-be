<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|max:255',
            'total_quantity' => 'required|integer|min:0',
            'available_quantity' => 'required|integer|min:0|lte:total_quantity',
            'unit_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive,maintenance,lost',
        ];
    }
}
