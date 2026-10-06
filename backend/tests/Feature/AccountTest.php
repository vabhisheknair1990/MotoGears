<?php

namespace Tests\Feature;

use App\Models\VehicleVariant;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function test_address_book(): void
    {
        $user = $this->actingAsCustomer();
        $payload = ['name' => 'Asha', 'phone' => '9876543210', 'line1' => '1 MG Road', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560001'];

        $first = $this->postJson($this->api('me/addresses'), $payload)->assertCreated()->assertJsonPath('data.is_default', true)->json('data');
        $second = $this->postJson($this->api('me/addresses'), $payload + ['is_default' => true, 'label' => 'Work'])->assertCreated()->json('data');
        $this->getJson($this->api('me/addresses'))->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $second['id']);
        $this->postJson($this->api('me/addresses'), ['postal_code' => '12'])->assertStatus(422)->assertJsonValidationErrors(['postal_code', 'name']);

        $this->patchJson($this->api("me/addresses/{$first['id']}"), ['city' => 'Mysuru'])->assertOk()->assertJsonPath('data.city', 'Mysuru');
        $this->deleteJson($this->api("me/addresses/{$second['id']}"))->assertOk();
        $this->assertTrue($user->addresses()->first()->is_default);

        $this->actingAsCustomer();
        $this->patchJson($this->api("me/addresses/{$first['id']}"), ['city' => 'Hack'])->assertForbidden();
    }

    public function test_garage_saved_vehicles(): void
    {
        $this->actingAsCustomer();
        $variant = VehicleVariant::factory()->create(['year_from' => 2020, 'year_to' => 2023]);

        $this->postJson($this->api('me/vehicles'), ['vehicle_variant_id' => $variant->id, 'year' => 2025])->assertStatus(422)->assertJsonValidationErrors('year');
        $v = $this->postJson($this->api('me/vehicles'), ['vehicle_variant_id' => $variant->id, 'year' => 2022, 'nickname' => 'Weekend'])
            ->assertCreated()->assertJsonPath('data.is_default', true)->assertJsonPath('data.variant.id', $variant->id)->json('data');
        $this->postJson($this->api('me/vehicles'), ['vehicle_variant_id' => $variant->id, 'year' => 2022])->assertStatus(422);
        $this->getJson($this->api('me/vehicles'))->assertJsonCount(1, 'data');
        $this->getJson($this->api('me/dashboard'))->assertOk()->assertJsonPath('data.default_vehicle.id', $v['id']);
        $this->deleteJson($this->api("me/vehicles/{$v['id']}"))->assertOk();
    }
}
