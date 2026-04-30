<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|in:worked,absent,day_off,sick,holiday',
            'notes' => 'nullable|string',
        ];
    }
}
