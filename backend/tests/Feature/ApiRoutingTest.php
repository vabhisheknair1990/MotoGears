<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The API is served from the root of its own host: https://api.themotogears.in/v1/... */
class ApiRoutingTest extends TestCase
{
    public function test_api_lives_under_v1_at_the_host_root(): void
    {
        $this->getJson('/v1')->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.docs', url('/docs'));
        $this->getJson('/v1/settings')->assertOk();
        $this->get('/docs')->assertOk();
        $this->get('/docs/openapi.yaml')->assertOk()->assertHeader('Content-Type', 'application/yaml');
        $this->getJson('/')->assertOk()->assertJsonPath('base_url', url('/v1'));
    }

    public function test_unknown_v1_routes_return_json_404(): void
    {
        $this->get('/v1/nope')->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_old_api_addresses_redirect_to_the_new_ones(): void
    {
        $this->get('/api/v1/products?page=2')->assertStatus(308)->assertRedirect(url('/v1/products?page=2'));
        $this->get('/api/docs')->assertRedirect(url('/docs'));
    }
}
