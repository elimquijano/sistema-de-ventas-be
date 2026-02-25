<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?: auth('sanctum')->user();

        if ($user && $user->status !== 'active') {
            return response()->json([
                'message' => 'Tu cuenta se encuentra inactiva. Por favor, contacta al administrador.',
            ], 403);
        }

        return $next($request);
    }
}
