<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    /**
     * Verifica se a aplicação e o banco de dados estão funcionando.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(): JsonResponse
    {
        try {
            // Verifica conexão com o banco de dados
            DB::connection()->getPdo();
            $dbStatus = 'connected';
            $statusCode = 200;
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
            $statusCode = 503;
        }

        return response()->json([
            'status' => 'ok',
            'application' => 'running',
            'database' => $dbStatus,
            'timestamp' => now()->toISOString(),
        ], $statusCode);
    }
}

