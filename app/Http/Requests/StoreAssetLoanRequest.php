<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetLoanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'asset_id' => 'required|exists:assets,id',
            'borrower_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'loan_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:loan_date',
            'notes' => 'nullable|string',
        ];
    }
}
