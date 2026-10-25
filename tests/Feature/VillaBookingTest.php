<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\SeasonalRate;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VillaBookingTest extends TestCase
{
    use RefreshDatabase;

    private function villa(User $owner, array $attributes = []): Villa
    {
        return Villa::create($attributes + ['owner_id' => $owner->id, 'name' => 'Lake Villa', 'location' => 'North Shore', 'guest_capacity' => 4, 'nightly_rate' => 100, 'minimum_stay' => 1, 'active' => true]);
    }

    public function test_guest_can_book_and_server_applies_seasonal_nightly_rates(): void
    {
        $owner = User::factory()->create(); $guest = User::factory()->create(); $villa = $this->villa($owner);
        $start = now()->addDays(5)->toDateString(); $middle = now()->addDays(6)->toDateString(); $end = now()->addDays(7)->toDateString();
        SeasonalRate::create(['villa_id' => $villa->id, 'starts_on' => $middle, 'ends_on' => $end, 'nightly_rate' => 150]);
        $this->actingAs($guest)->post(route('reservations.store'), ['villa_id' => $villa->id, 'check_in' => $start, 'check_out' => $end, 'guests' => 2, 'total_price' => 1])->assertRedirect();
        $booking = Reservation::firstOrFail();
        $this->assertSame(2, $booking->nights);
        $this->assertSame('250.00', $booking->total_price);
        $this->assertSame(12, strlen($booking->reference));
        $this->actingAs($guest)->get(route('reservations.show', $booking))->assertOk()->assertSee($booking->reference);
    }

    public function test_conflicting_dates_capacity_and_minimum_stay_are_rejected(): void
    {
        $owner = User::factory()->create(); $guest = User::factory()->create(); $villa = $this->villa($owner, ['guest_capacity' => 2, 'minimum_stay' => 2]);
        $start = now()->addDays(5)->toDateString(); $end = now()->addDays(7)->toDateString();
        Reservation::create(['reference' => 'EXISTINGBOOK1', 'villa_id' => $villa->id, 'guest_id' => User::factory()->create()->id, 'check_in' => $start, 'check_out' => $end, 'guests' => 2, 'nights' => 2, 'total_price' => 200, 'status' => 'confirmed']);
        $payload = ['villa_id' => $villa->id, 'check_in' => $start, 'check_out' => $end, 'guests' => 2];
        $this->actingAs($guest)->post(route('reservations.store'), $payload)->assertSessionHasErrors('check_in');
        Reservation::query()->delete();
        $this->actingAs($guest)->post(route('reservations.store'), array_merge($payload, ['guests' => 3]))->assertSessionHasErrors('villa_id');
        $this->actingAs($guest)->post(route('reservations.store'), ['villa_id' => $villa->id, 'check_in' => $start, 'check_out' => now()->addDays(6)->toDateString(), 'guests' => 1])->assertSessionHasErrors('villa_id');
    }

    public function test_cancellation_and_owner_dashboard_are_scoped(): void
    {
        $owner = User::factory()->create(); $otherOwner = User::factory()->create(); $guest = User::factory()->create();
        $villa = $this->villa($owner); $otherVilla = $this->villa($otherOwner, ['name' => 'Hidden Villa']);
        $booking = Reservation::create(['reference' => 'OWNBOOKING123', 'villa_id' => $villa->id, 'guest_id' => $guest->id, 'check_in' => now()->addDays(5), 'check_out' => now()->addDays(7), 'guests' => 2, 'nights' => 2, 'total_price' => 200, 'status' => 'confirmed']);
        $this->actingAs($otherOwner)->get(route('owner.dashboard'))->assertOk()->assertDontSee('Lake Villa')->assertSee('Hidden Villa');
        $this->actingAs($otherOwner)->put(route('owner.villas.update', $villa), ['name' => 'Stolen', 'location' => 'X', 'guest_capacity' => 2, 'nightly_rate' => 50, 'minimum_stay' => 1])->assertForbidden();
        $this->actingAs($otherOwner)->patch(route('reservations.cancel', $booking))->assertForbidden();
        $this->actingAs($guest)->patch(route('reservations.cancel', $booking))->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
    }
}
