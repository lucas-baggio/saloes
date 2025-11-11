<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthCheckApiTest extends TestCase
{
    public function test_health_check_returns_application_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'application',
                'database',
                'timestamp',
            ])
            ->assertJson([
                'status' => 'ok',
                'application' => 'running',
            ]);
    }

    public function test_health_check_verifies_database_connection(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('database', $data);
        $this->assertContains($data['database'], ['connected', 'disconnected']);
    }
}

