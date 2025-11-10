<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        return url('/login');
    }

    /**
     * Handle unauthenticated requests for APIs.
     */
    protected function unauthenticated($request, array $guards)
    {
        if ($request->expectsJson()) {
            throw new \Illuminate\Auth\AuthenticationException(
                'Você precisa estar autenticado para acessar este recurso.',
                $guards,
                $this->redirectTo($request)
            );
        }

        parent::unauthenticated($request, $guards);
    }
}

