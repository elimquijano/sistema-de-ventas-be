<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string',
            'address_detail' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'image' => 'nullable|image', //|max:2048', // Max 2MB
            'route' => 'nullable', // Can be JSON string or array
            'estimated_time' => 'nullable|string|max:255',
            'approximate_distance' => 'nullable|string|max:255',
        ];
    }
}
