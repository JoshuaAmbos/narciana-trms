<?php

namespace Tests\Feature;

use App\Models\Appliance;
use App\Models\Bill;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

class TenantLifecycleAndMoaEncodingTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;
    private User $manager;
    private User $caretaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
        $this->manager = User::factory()->role('Manager')->create();
        $this->caretaker = User::factory()->role('Caretaker')->create();
    }

    // ==========================================
    // 1. Authorization & RBAC
    // ==========================================

    public function test_caretaker_is_forbidden_from_moa_encoding_routes(): void
    {
        $room = $this->makeRoom('101');

        $this->actingAs($this->caretaker)
            ->get('/leases/create')
            ->assertForbidden();

        $this->actingAs($this->caretaker)
            ->post('/leases', $this->moaForm($room))
            ->assertForbidden();
    }

    public function test_owner_and_manager_are_authorized_to_encode_moa(): void
    {
        $room = $this->makeRoom('101');

        $this->actingAs($this->owner)
            ->get('/leases/create')
            ->assertOk();

        $this->actingAs($this->manager)
            ->get('/leases/create')
            ->assertOk();
    }

    // ==========================================
    // 2. Tenant Lifecycle, Archiving & Directory
    // ==========================================

    public function test_tenant_archiving_and_restoration(): void
    {
        $tenant = Tenant::create([
            'first_name' => 'Juan',
            'last_name' => 'Luna',
            'full_name' => 'Juan Luna',
            'contact_no' => '09171234567',
            'valid_id_type' => "Driver's License",
            'valid_id_no' => 'N01-23-456789',
            'status' => 'Inactive',
        ]);

        // Archive
        $this->actingAs($this->manager)
            ->patch(route('tenants.archive', $tenant))
            ->assertRedirect(route('tenants.index'))
            ->assertSessionHas('status');

        $tenant->refresh();
        $this->assertSame('Archived', $tenant->status);
        $this->assertNotNull($tenant->deleted_at);

        // Active listing should not display archived tenant
        session()->forget('status');
        $this->actingAs($this->manager)
            ->get(route('tenants.index'))
            ->assertDontSee('Juan Luna');

        // Show archived filter displays the archived tenant
        $this->actingAs($this->manager)
            ->get(route('tenants.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('Juan Luna')
            ->assertSee('Restore');

        // Restore
        $this->actingAs($this->manager)
            ->patch(route('tenants.restore', $tenant))
            ->assertRedirect(route('tenants.index', ['archived' => 1]))
            ->assertSessionHas('status');

        $tenant->refresh();
        $this->assertSame('Inactive', $tenant->status);
        $this->assertNull($tenant->deleted_at);
    }

    public function test_archiving_tenant_with_active_lease_is_prevented(): void
    {
        $room = $this->makeRoom('102');
        $lease = $this->makeLease($room, $this->manager, null, 'Active Tenant');
        $tenant = $lease->tenant;

        $this->actingAs($this->manager)
            ->patch(route('tenants.archive', $tenant))
            ->assertSessionHas('error');

        $tenant->refresh();
        $this->assertSame('Active', $tenant->status);
        $this->assertNull($tenant->deleted_at);
    }

    public function test_blacklisted_and_archived_tenants_are_excluded_from_moa_selection(): void
    {
        $blacklisted = Tenant::create([
            'first_name' => 'Bad',
            'last_name' => 'Renter',
            'full_name' => 'Bad Renter',
            'contact_no' => '09170001111',
            'valid_id_type' => 'Passport',
            'valid_id_no' => 'P1234567',
            'status' => 'Blacklisted',
        ]);

        $archived = Tenant::create([
            'first_name' => 'Old',
            'last_name' => 'Renter',
            'full_name' => 'Old Renter',
            'contact_no' => '09170002222',
            'valid_id_type' => 'Passport',
            'valid_id_no' => 'P7654321',
            'status' => 'Archived',
        ]);

        $room = $this->makeRoom('103');

        // Verify they are not in the create view dropdown
        $this->actingAs($this->manager)
            ->get('/leases/create')
            ->assertDontSee('Bad Renter')
            ->assertDontSee('Old Renter');

        // Verify submitting returning tenant with blacklisted is rejected
        $this->actingAs($this->manager)
            ->post('/leases', [
                'tenant_mode' => 'existing',
                'tenant_id' => $blacklisted->tenant_id,
                'room_id' => $room->room_id,
                'moa_date' => '2026-06-24',
                'move_in_date' => '2026-06-25',
                'due_day' => 25,
                'monthly_rent' => '3500.00',
                'min_stay_months' => 4,
                'advance_amount' => '3500.00',
                'deposit_maintenance' => '1000.00',
                'deposit_damage' => '1500.00',
                'deposit_bills' => '700.00',
                'deposit_keys' => '300.00',
                'mode' => 'Cash',
            ])
            ->assertSessionHasErrors('tenant_id');

        // Verify submitting returning tenant with archived is rejected
        $this->actingAs($this->manager)
            ->post('/leases', [
                'tenant_mode' => 'existing',
                'tenant_id' => $archived->tenant_id,
                'room_id' => $room->room_id,
                'moa_date' => '2026-06-24',
                'move_in_date' => '2026-06-25',
                'due_day' => 25,
                'monthly_rent' => '3500.00',
                'min_stay_months' => 4,
                'advance_amount' => '3500.00',
                'deposit_maintenance' => '1000.00',
                'deposit_damage' => '1500.00',
                'deposit_bills' => '700.00',
                'deposit_keys' => '300.00',
                'mode' => 'Cash',
            ])
            ->assertSessionHasErrors('tenant_id');
    }

    // ==========================================
    // 3. Past / Returning Prospects Metric Calculation
    // ==========================================

    public function test_past_renters_metric_only_counts_tenants_with_ended_leases_or_past_inquiries(): void
    {
        // 1. Tenant who only has an active lease right now (NOT a past prospect)
        $room1 = $this->makeRoom('104');
        $this->makeLease($room1, $this->manager, null, 'Active Currently');

        // 2. Tenant who had a lease that Ended (IS a past prospect)
        $room2 = $this->makeRoom('105');
        $leaseEnded = $this->makeLease($room2, $this->manager, null, 'Past Leaser');
        $leaseEnded->update(['status' => 'Ended']);

        // 3. Tenant who never rented but has a logged inquiry (IS a past prospect)
        $prospectWithInquiry = Tenant::create([
            'first_name' => 'Inquiry',
            'last_name' => 'Prospect',
            'full_name' => 'Inquiry Prospect',
            'contact_no' => '09191112233',
            'valid_id_type' => 'Passport',
            'valid_id_no' => 'P9876543',
            'status' => 'Active',
        ]);
        Inquiry::create([
            'inquiry_date' => today()->subDays(5),
            'prospect_name' => 'Inquiry Prospect',
            'contact_no' => '09191112233',
            'channel' => 'Walk-in',
            'status' => 'Waiting',
            'preferred_type' => 'Basic',
            'no_of_occupants' => 1,
            'user_id' => $this->manager->user_id,
        ]);

        // 4. Freshly registered tenant with neither past lease nor inquiry (NOT a past prospect)
        Tenant::create([
            'first_name' => 'Fresh',
            'last_name' => 'Registered',
            'full_name' => 'Fresh Registered',
            'contact_no' => '09193334455',
            'valid_id_type' => 'Passport',
            'valid_id_no' => 'P1122334',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->manager)->get(route('tenants.index'));
        $response->assertOk();

        // Should count exactly 2 past renters (the ended lease tenant and the prospect with inquiry)
        $response->assertViewHas('metrics', function ($metrics) {
            return ($metrics['past_renters'] ?? null) === 2;
        });
    }

    // ==========================================
    // 4. Dynamic Valid ID Validation
    // ==========================================

    public function test_dynamic_id_formatting_validation_rules(): void
    {
        $room = $this->makeRoom('106');

        // Invalid PhilSys ID (not 16 digits or 4-4-4-4 formatted)
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'valid_id_type' => 'PhilSys National ID (PCN)',
            'valid_id_no' => '123',
        ]))->assertSessionHasErrors('valid_id_no');

        // Valid PhilSys ID
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'valid_id_type' => 'PhilSys National ID (PCN)',
            'valid_id_no' => '1234-5678-9012-3456',
        ]))->assertSessionHasNoErrors();

        // Valid Driver's License
        $room2 = $this->makeRoom('107');
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room2, [
            'valid_id_type' => "Driver's License",
            'valid_id_no' => 'N01-23-456789',
        ]))->assertSessionHasNoErrors();
    }

    // ==========================================
    // 5. Contract Terms: Staggered Due Dates & Min Stay
    // ==========================================

    public function test_minimum_stay_policy_enforces_at_least_four_months(): void
    {
        $room = $this->makeRoom('108');

        // Less than 4 months fails
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'min_stay_months' => 3,
        ]))->assertSessionHasErrors('min_stay_months');

        // 4 months passes
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'min_stay_months' => 4,
        ]))->assertSessionHasNoErrors();
    }

    public function test_staggered_due_date_validation_and_snapping(): void
    {
        $room = $this->makeRoom('109');

        // Invalid arbitrary due day (e.g. 7) fails validation
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'due_day' => 7,
        ]))->assertSessionHasErrors('due_day');

        // Empty due day snaps to closest cycle day in [10, 15, 20, 25, 30] based on move-in date
        // Move-in: 2026-06-22 -> closest is 20
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'move_in_date' => '2026-06-22',
            'due_day' => '',
        ]))->assertSessionHasNoErrors();

        $lease = Lease::where('room_id', $room->room_id)->firstOrFail();
        $this->assertSame(20, $lease->due_day);
    }

    // ==========================================
    // 6. Upfront Move-In Payment & Initial Settlement Bill
    // ==========================================

    public function test_upfront_payment_creates_initial_bill_and_payment_record(): void
    {
        $room = $this->makeRoom('110');

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'mode' => 'GCash',
            'gcash_ref_no' => '1001234567890',
            'monthly_rent' => '3500.00',
            'advance_amount' => '3500.00',
            'deposit_maintenance' => '1000.00',
            'deposit_damage' => '1500.00',
            'deposit_bills' => '700.00',
            'deposit_keys' => '300.00',
        ]))->assertSessionHasNoErrors();

        $lease = Lease::where('room_id', $room->room_id)->firstOrFail();

        // 1-month advance (3500) + deposits (1000+1500+700+300 = 3500) = 7000.00
        $this->assertDatabaseHas('tbl_bill', [
            'lease_id' => $lease->lease_id,
            'is_initial' => 1,
            'status' => 'Paid',
            'total_amount' => '7000.00',
        ]);

        $initialBill = Bill::where('lease_id', $lease->lease_id)->where('is_initial', 1)->firstOrFail();

        $this->assertDatabaseHas('tbl_payment', [
            'bill_id' => $initialBill->bill_id,
            'amount' => '7000.00',
            'mode' => 'GCash',
            'gcash_ref_no' => '1001234567890',
            'user_id' => $this->manager->user_id,
        ]);

        $payment = Payment::where('bill_id', $initialBill->bill_id)->firstOrFail();
        $this->assertStringStartsWith('ACK-', $payment->ack_no);
        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Payment recorded',
            'table_name' => 'tbl_payment',
            'record_id' => $payment->payment_id,
        ]);
    }

    // ==========================================
    // 7. Workflow Integration: Inquiry Conversion
    // ==========================================

    public function test_inquiry_to_lease_conversion(): void
    {
        $room = $this->makeRoom('111');
        $inquiry = Inquiry::create([
            'inquiry_date' => today()->subDays(2),
            'prospect_name' => 'Converted Prospect',
            'contact_no' => '09181234567',
            'channel' => 'Call',
            'status' => 'Waiting',
            'preferred_type' => 'Basic',
            'no_of_occupants' => 1,
            'room_id' => $room->room_id,
            'user_id' => $this->manager->user_id,
        ]);

        // Opening create route pre-selects room and pre-fills tenant details
        $this->actingAs($this->manager)
            ->get(route('leases.create', ['inquiry_id' => $inquiry->inquiry_id]))
            ->assertOk()
            ->assertSee('Converted Prospect')
            ->assertSee('Inquiry-to-Lease Conversion');

        // Submitting MOA with inquiry_id converts the inquiry
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'inquiry_id' => $inquiry->inquiry_id,
            'full_name' => 'Converted Prospect',
            'contact_no' => '09181234567',
        ]))->assertSessionHasNoErrors();

        $inquiry->refresh();
        $this->assertSame('Rented', $inquiry->status);
        $this->assertSame($room->room_id, $inquiry->room_id);

        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Inquiry rented',
            'table_name' => 'tbl_inquiry',
            'record_id' => $inquiry->inquiry_id,
        ]);
    }

    // ==========================================
    // 8. Appliance Registration for Unmetered Room
    // ==========================================

    public function test_appliance_declaration_during_moa_encoding_persists_to_appliances_table(): void
    {
        // Basic unmetered room
        $room = $this->makeRoom('112', 'Basic', ['is_metered' => false]);

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'appliance_name' => 'Electric Rice Cooker',
        ]))->assertSessionHasNoErrors();

        $lease = Lease::where('room_id', $room->room_id)->firstOrFail();

        $this->assertDatabaseHas('tbl_appliance', [
            'lease_id' => $lease->lease_id,
            'appliance_name' => 'Electric Rice Cooker',
            'user_id' => $this->manager->user_id,
        ]);
    }
}
