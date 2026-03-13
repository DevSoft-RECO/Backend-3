<?php

namespace Tests\Feature;

use App\Models\SolicitudApoyo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudAuditTest extends TestCase
{
    /**
     * Test that audit listing works.
     */
    public function test_audit_index_returns_data()
    {
        // Mocking user to bypass SSO or assuming we can use a factory
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->getJson('/api/audit/solicitudes');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'last_page'
        ]);
    }

    /**
     * Test that agency catalog returns data.
     */
    public function test_audit_agencias_catalog_returns_data()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->getJson('/api/audit/agencias-catalog');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    }
}
