<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChangeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->requiresPasswordChange()) {
            return $next($request);
        }

        if ($request->routeIs('password.force.edit', 'password.force.update', 'logout')) {
            return $next($request);
        }

        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()
            ->route('password.force.edit')
            ->with('warning', 'You must change your password before continuing.');
    }
}