<?php

namespace Tests\Feature;

use App\Models\Appliance;
use App\Models\Bill;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoomSeeder;
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

    public function test_standalone_create_tenant_route_is_redirected_to_lease_encoding(): void
    {
        $this->actingAs($this->manager)
            ->get(route('tenants.create'))
            ->assertRedirect(route('leases.create'))
            ->assertSessionHas('info');

        $room = $this->makeRoom('201');
        $this->makeLease($room, $this->manager, null, 'Sample Tenant');

        $response = $this->actingAs($this->manager)->get(route('tenants.index'));
        $response->assertOk()
            ->assertDontSee('+ New Tenant')
            ->assertDontSee('+ Add Tenant')
            ->assertDontSee('+ Register Tenant')
            ->assertSee('Tenant Name')
            ->assertSee('Current Rented Room')
            ->assertSee('Contact Number')
            ->assertSee('Valid ID Type')
            ->assertSee('Masked ID Number')
            ->assertSee('Status')
            ->assertSee('Edit Profile');
    }

    public function test_leases_index_matches_layout_and_has_encode_moa_button(): void
    {
        $response = $this->actingAs($this->manager)->get(route('leases.index'));
        $response->assertOk()
            ->assertSee('+ Encode New MOA')
            ->assertSee('Active')
            ->assertSee('Ended')
            ->assertSee('Cancelled');
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
        $room = $this->makeRoom('108', 'Basic');

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

    // ==========================================
    // 9. Strict MOA Clause 6 Upfront Validation
    // ==========================================

    public function test_strict_moa_clause_6_upfront_breakdown_validation(): void
    {
        $room = $this->makeRoom('113', 'Basic', ['monthly_rent' => 3000.00]);

        // Advance rent does not match monthly rent (e.g., 2500 vs 3000)
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'monthly_rent' => 3000.00,
            'advance_amount' => 2500.00,
            'deposit_maintenance' => 900.00,
            'deposit_damage' => 1200.00,
            'deposit_bills' => 600.00,
            'deposit_keys' => 300.00,
        ]))->assertSessionHasErrors(['advance_amount']);

        // Deposit sum does not match monthly rent (e.g., 2900 vs 3000)
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'monthly_rent' => 3000.00,
            'advance_amount' => 3000.00,
            'deposit_maintenance' => 900.00,
            'deposit_damage' => 1200.00,
            'deposit_bills' => 600.00,
            'deposit_keys' => 200.00, // 2900 total
        ]))->assertSessionHasErrors(['deposit_maintenance']);

        // Exact match passes validation
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'monthly_rent' => 3000.00,
            'advance_amount' => 3000.00,
            'deposit_maintenance' => 900.00,
            'deposit_damage' => 1200.00,
            'deposit_bills' => 600.00,
            'deposit_keys' => 300.00, // 3000 total
            'payment_mode' => 'Cash',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Lease::where('room_id', $room->room_id)->count());
    }

    // ==========================================
    // 10. Contractual Minimum Stay Disclaimer & Default
    // ==========================================

    public function test_contractual_minimum_stay_disclaimer_and_backend_default(): void
    {
        $room = $this->makeRoom('114', 'Basic');
        $airconRoom = $this->makeRoom('114A', 'Aircon');

        $this->actingAs($this->manager)->get('/leases/create')
            ->assertOk()
            ->assertSee('Contractual Minimum Stay')
            ->assertSee('Months')
            ->assertDontSee('id="min_stay_months" name="min_stay_months" type="number"', false);

        // Submitting with min_stay_months omitted on Basic room defaults automatically to 4
        $form = $this->moaForm($room);
        unset($form['min_stay_months']);

        $this->actingAs($this->manager)->post('/leases', $form)->assertSessionHasNoErrors();

        $lease = Lease::where('room_id', $room->room_id)->firstOrFail();
        $this->assertSame(4, (int) $lease->min_stay_months);

        // Submitting with min_stay_months omitted on Aircon room defaults automatically to 6
        $airconForm = $this->moaForm($airconRoom, ['move_in_reading' => '1250.00']);
        unset($airconForm['min_stay_months']);

        $this->actingAs($this->manager)->post('/leases', $airconForm)->assertSessionHasNoErrors();

        $airconLease = Lease::where('room_id', $airconRoom->room_id)->firstOrFail();
        $this->assertSame(6, (int) $airconLease->min_stay_months);
    }

    // ==========================================
    // 11. Room Inventory & Preset Rate Architecture
    // ==========================================

    public function test_room_inventory_and_preset_rates_seeder(): void
    {
        $this->seed(RoomSeeder::class);

        $this->assertSame(17, Room::count());

        // Check specific room presets per specifications
        $r201 = Room::where('room_no', '201')->firstOrFail();
        $this->assertSame('Basic', $r201->room_type);
        $this->assertSame('3000.00', $r201->monthly_rent);
        $this->assertSame(2, $r201->max_occupants);

        $r204 = Room::where('room_no', '204')->firstOrFail();
        $this->assertSame('3500.00', $r204->monthly_rent);

        $r213 = Room::where('room_no', '213')->firstOrFail();
        $this->assertSame('2500.00', $r213->monthly_rent);
        $this->assertSame(1, $r213->max_occupants);

        $r207 = Room::where('room_no', '207')->firstOrFail();
        $this->assertSame('Aircon', $r207->room_type);
        $this->assertSame('3600.00', $r207->monthly_rent);

        $r214 = Room::where('room_no', '214')->firstOrFail();
        $this->assertSame('3700.00', $r214->monthly_rent);

        $r212 = Room::where('room_no', '212')->firstOrFail();
        $this->assertSame('3800.00', $r212->monthly_rent);
    }

    // ==========================================
    // 12. Settings Module: RBAC & Baseline Rates
    // ==========================================

    public function test_settings_module_restricted_strictly_to_owner(): void
    {
        // Manager is forbidden
        $this->actingAs($this->manager)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($this->manager)->put(route('settings.update'), [])->assertForbidden();

        // Caretaker is forbidden
        $this->actingAs($this->caretaker)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($this->caretaker)->put(route('settings.update'), [])->assertForbidden();

        // Owner is authorized
        $this->actingAs($this->owner)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Standard Basic room monthly rate')
            ->assertSee('Standard Aircon room monthly rate');
    }

    public function test_owner_updates_baseline_room_rates_and_logs_audit(): void
    {
        $this->actingAs($this->owner)->put(route('settings.update'), [
            'water_rate' => '200.00',
            'appliance_fee' => '200.00',
            'standard_basic_rent' => '3100.00',
            'standard_aircon_rent' => '3700.00',
            'kwh_rate' => '15.00',
            'grace_days' => 3,
            'penalty_basis' => 'Per day of rent',
            'penalty_rate' => '1.00',
            'refund_days' => 7,
        ])->assertRedirect(route('settings.index'))->assertSessionHasNoErrors();

        $setting = Setting::current();
        $this->assertSame('3100.00', $setting->standard_basic_rent);
        $this->assertSame('3700.00', $setting->standard_aircon_rent);

        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Settings changed',
            'table_name' => 'tbl_setting',
            'user_id' => $this->owner->user_id,
        ]);
    }

    public function test_owner_updating_room_base_rent_is_audited_and_does_not_affect_existing_leases(): void
    {
        $room = $this->makeRoom('115', 'Basic', ['monthly_rent' => 3000.00]);
        $lease = $this->makeLease($room, $this->manager);

        // Owner updates room base rent to 3800.00
        $this->actingAs($this->owner)->put(route('rooms.update', $room), [
            'room_no' => $room->room_no,
            'room_type' => $room->room_type,
            'monthly_rent' => 3800.00,
            'max_occupants' => $room->max_occupants,
            'is_metered' => $room->is_metered ? 1 : 0,
        ])->assertRedirect(route('rooms.index'));

        $room->refresh();
        $this->assertSame('3800.00', $room->monthly_rent);

        // Existing lease is untouched (contractual rent was 3500.00)
        $lease->refresh();
        $this->assertSame('3500.00', $lease->monthly_rent);

        // Audit log records the price change
        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Room changed',
            'table_name' => 'tbl_room',
            'record_id' => $room->room_id,
            'details' => 'Monthly rent: ₱3,000.00 → ₱3,800.00',
        ]);
    }

    // ==========================================
    // 13. UI Refinements & Subtitles Removal
    // ==========================================

    public function test_moa_encoding_ui_refinements_and_subtitles_removal(): void
    {
        $room = $this->makeRoom('116', 'Basic', ['monthly_rent' => 3000.00]);

        $response = $this->actingAs($this->manager)->get(route('leases.create', ['room_id' => $room->room_id]))
            ->assertOk();

        // 1. Due day: dropdown select with only staggered days; free-form text/number field removed
        $response->assertSee('<select id="due_day" name="due_day"', false);
        $response->assertDontSee('id="due_day" name="due_day" type="number"', false);
        foreach ([10, 15, 20, 25, 30] as $day) {
            $response->assertSee('<option value="'.$day.'"', false);
        }

        // 2. One-month advance paid: readonly and defaults to monthly rent
        $response->assertSee('name="advance_amount" type="number" min="0" step="0.01" inputmode="decimal" readonly', false);

        // 3. Subtitles removed
        $response->assertDontSee('Only verified tenants without an active lease are listed. Or switch to "Encode new tenant" above.');
        $response->assertDontSee('Contractual Minimum Stay: 4 Months (Leaving earlier forfeits deposit per MOA Clause 9).');
        $response->assertDontSee("Must equal exactly 1 month's rent per MOA Clause 6.");
        $response->assertDontSee('Saving sets the room to Occupied.');
        $response->assertDontSee("— the MOA deposit is one month's rent");

        // 4. Action buttons right-aligned
        $response->assertSee('justify-content: flex-end', false);
    }

    public function test_aircon_room_strictly_requires_move_in_meter_reading(): void
    {
        $aircon = $this->makeRoom('218', 'Aircon');
        Setting::current()->update(['kwh_rate' => 12.00]);

        // Missing move_in_reading on Aircon room fails validation
        $formWithoutReading = $this->moaForm($aircon);
        unset($formWithoutReading['move_in_reading']);

        $this->actingAs($this->manager)->post('/leases', $formWithoutReading)
            ->assertSessionHasErrors(['move_in_reading' => 'The baseline move-in meter reading is required for air-conditioned rooms per Aircon MOA Clause 6.']);

        // Providing move_in_reading succeeds and records reading with property default kwh_rate 12.00
        $formWithReading = $this->moaForm($aircon, ['move_in_reading' => '1500.25']);
        $this->actingAs($this->manager)->post('/leases', $formWithReading)
            ->assertSessionHasNoErrors();

        $reading = MeterReading::where('room_id', $aircon->room_id)->firstOrFail();
        $this->assertSame('1500.25', $reading->previous_reading);
        $this->assertSame('12.00', $reading->rate_per_kwh);
    }

    public function test_inquiry_conversion_action_in_inquiries_index(): void
    {
        $room = $this->makeRoom('219', 'Basic');
        $inquiry = Inquiry::create([
            'prospect_name' => 'Prospect John Doe',
            'contact_no' => '09171234567',
            'preferred_type' => 'Basic',
            'no_of_occupants' => 1,
            'channel' => 'Walk-in',
            'via_representative' => false,
            'inquiry_date' => today(),
            'status' => 'Waiting',
            'room_id' => $room->room_id,
            'user_id' => $this->manager->user_id,
        ]);

        $this->actingAs($this->manager)->get(route('inquiries.index'))
            ->assertOk()
            ->assertSee('Encode MOA')
            ->assertSee(route('leases.create', ['inquiry_id' => $inquiry->inquiry_id]));
    }
}
