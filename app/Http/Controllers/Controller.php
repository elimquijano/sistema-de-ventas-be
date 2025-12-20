<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    public function getPaginationSize(Request $request, $query, $default = 15)
    {
        $perPage = $request->get('per_page', $default);
        if ($perPage == -1) {
            $total = $query->clone()->count();
            return $total > 0 ? $total : $default;
        }
        return $perPage;
    }
}
