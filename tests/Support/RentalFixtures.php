<?php

namespace Tests\Support;

use App\Models\Lease;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use App\Services\LeaseService;

/** Small helpers to build rooms, settings and leases in feature tests (fictional data only). */
trait RentalFixtures
{
    protected function settings(User $owner, array $overrides = []): Setting
    {
        return Setting::create(array_merge([
            'water_rate' => 200, 'appliance_fee' => 200, 'standard_basic_rent' => 3000, 'standard_aircon_rent' => 3600,
            'kwh_rate' => 15, 'grace_days' => 3,
            'penalty_basis' => 'Per day of rent', 'penalty_rate' => 1, 'refund_days' => 7, 'updated_by' => $owner->user_id,
        ], $overrides));
    }

    protected function makeRoom(string $no, string $type = 'Basic', array $overrides = []): Room
    {
        return Room::create($overrides + [
            'room_no' => $no, 'room_type' => $type, 'is_metered' => $type === 'Aircon', 'max_occupants' => 2,
            'monthly_rent' => 3500, 'status' => 'Vacant',
        ]);
    }

    /** Valid "Encode new MOA" form data for a new tenant. */
    protected function moaForm(Room $room, array $overrides = []): array
    {
        $isAircon = $room->room_type === 'Aircon' || $room->is_metered;
        $defaults = [
            'tenant_mode' => 'new',
            'full_name' => 'Sample Tenant A', 'contact_no' => '0918 555 0123',
            'valid_id_type' => "Driver's License", 'valid_id_no' => 'N01-23-456789',
            'provincial_address' => 'Purok 1, Sample Barangay, Davao del Norte', 'occupation' => 'Student',
            'room_id' => $room->room_id,
            'moa_date' => '2026-06-24', 'move_in_date' => '2026-06-25', 'due_day' => '25',
            'monthly_rent' => '3500.00', 'min_stay_months' => $isAircon ? '6' : '4', 'advance_amount' => '3500.00',
            'deposit_maintenance' => '1000.00', 'deposit_damage' => '1500.00', 'deposit_bills' => '700.00', 'deposit_keys' => '300.00',
        ];
        if ($isAircon) {
            $defaults['move_in_reading'] = '1000.00';
        }

        return array_merge($defaults, $overrides);
    }

    /** Encode a lease through the service (room becomes Occupied). */
    protected function makeLease(Room $room, User $by, ?string $coTenant = null, string $tenantName = 'Sample Tenant A', ?float $moveInReading = null): Lease
    {
        $isAircon = $room->room_type === 'Aircon' || $room->is_metered;
        $minStay = $isAircon ? Lease::MIN_STAY_AIRCON : Lease::MIN_STAY_BASIC;

        return app(LeaseService::class)->create(
            ['full_name' => $tenantName, 'contact_no' => '0918 555 0123', 'valid_id_type' => 'Passport', 'valid_id_no' => 'P1234567',
                'provincial_address' => 'Purok 2, Sample Barangay, Bukidnon', 'occupation' => 'Nurse'],
            ['room_id' => $room->room_id, 'moa_date' => '2026-06-24', 'move_in_date' => '2026-06-25', 'due_day' => 25,
                'monthly_rent' => 3500, 'min_stay_months' => $minStay, 'advance_amount' => 3500, 'deposit_maintenance' => 1000,
                'deposit_damage' => 1500, 'deposit_bills' => 700, 'deposit_keys' => 300],
            $coTenant,
            $by,
            $moveInReading,
        );
    }
}
