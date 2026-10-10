<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Lease;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DelinquencyService;
use App\Services\PenaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

class MoaDelinquencyAndClause8TerminationTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private User $manager;

    private User $caretaker;

    private Setting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->role('Owner')->create(['full_name' => 'Owner User']);
        $this->manager = User::factory()->role('Manager')->create(['full_name' => 'Manager User']);
        $this->caretaker = User::factory()->role('Caretaker')->create(['full_name' => 'Caretaker User']);

        $this->setting = $this->settings($this->owner, [
            'grace_days' => 3,
            'penalty_rate' => 1.00, // 1% per day
            'penalty_basis' => 'Per day of rent',
        ]);
    }

    public function test_days_1_to_3_grace_protection(): void
    {
        $room = $this->makeRoom('101', 'Basic', ['monthly_rent' => 3000]);
        $lease = $this->makeLease($room, $this->manager, tenantName: 'Grace Tenant');
        $lease->update([
            'deposit_damage' => 2000.00,
            'deposit_bills' => 1500.00,
            'deposit_maintenance' => 1000.00,
            'deposit_keys' => 500.00,
        ]); // Total deposit = 5000.00 > 3600.00 bill

        // Due on 2026-10-01
        $bill = Bill::create([
            'lease_id' => $lease->lease_id,
            'period_start' => '2026-09-01',
            'due_date' => '2026-10-01',
            'rent_amount' => 3000.00,
            'water_amount' => 200.00,
            'electricity_amount' => 400.00,
            'total_amount' => 3600.00,
            'penalty_amount' => 0.00,
            'status' => 'Unpaid',
            'user_id' => $this->manager->user_id,
        ]);

        $penalties = app(PenaltyService::class);

        // Day 1 past due: 2026-10-02
        $day1 = $penalties->forBill($bill, Carbon::parse('2026-10-02'));
        $this->assertSame(0, $day1['days_late']);
        $this->assertSame(1, $day1['days_past_due']);
        $this->assertTrue($day1['in_grace']);
        $this->assertSame(1, $day1['grace_day']);
        $this->assertSame(0.0, $day1['penalty']);

        // Day 2 past due: 2026-10-03
        $day2 = $penalties->forBill($bill, Carbon::parse('2026-10-03'));
        $this->assertSame(0, $day2['days_late']);
        $this->assertSame(2, $day2['days_past_due']);
        $this->assertTrue($day2['in_grace']);
        $this->assertSame(2, $day2['grace_day']);
        $this->assertSame(0.0, $day2['penalty']);

        // Day 3 past due: 2026-10-04
        $day3 = $penalties->forBill($bill, Carbon::parse('2026-10-04'));
        $this->assertSame(0, $day3['days_late']);
        $this->assertSame(3, $day3['days_past_due']);
        $this->assertTrue($day3['in_grace']);
        $this->assertSame(3, $day3['grace_day']);
        $this->assertSame(0.0, $day3['penalty']);

        // Verify status badge on Payments page during grace period
        $this->travelTo(Carbon::parse('2026-10-03'));
        $response = $this->actingAs($this->manager)->get(route('payments.index'));
        $response->assertOk();
        $response->assertSee('Grace Period (Day 2 of 3)');

        // Verify status badge on Dashboard page during grace period
        $dashResponse = $this->actingAs($this->manager)->get(route('dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('Grace Period (Day 2 of 3)');
    }

    public function test_base_rent_penalty_only_at_12_days_delayed(): void
    {
        $room = $this->makeRoom('102', 'Basic', ['monthly_rent' => 3000]);
        $lease = $this->makeLease($room, $this->manager, tenantName: 'Late Tenant');

        // Due date: 2026-10-01. With 3 grace days, Day 4 (Oct 5) is 1 day late.
        // 12 days delayed -> payment on Oct 16 (15 days past due - 3 grace days = 12 delayed days).
        $bill = Bill::create([
            'lease_id' => $lease->lease_id,
            'period_start' => '2026-09-01',
            'due_date' => '2026-10-01',
            'rent_amount' => 3000.00,
            'water_amount' => 200.00,
            'electricity_amount' => 400.00,
            'total_amount' => 3600.00,
            'penalty_amount' => 0.00,
            'status' => 'Unpaid',
            'user_id' => $this->manager->user_id,
        ]);

        $penalties = app(PenaltyService::class);
        $result = $penalties->forBill($bill, Carbon::parse('2026-10-16'));

        $this->assertSame(12, $result['days_late']);
        $this->assertFalse($result['in_grace']);

        // Daily penalty = ₱3,000 * 0.01 = ₱30.00 per day.
        // 12 days delayed = ₱30.00 * 12 = ₱360.00.
        // Base total = ₱3,600.00 (rent 3000 + water 200 + electric 400).
        // Total with penalty = ₱3,600.00 + ₱360.00 = ₱3,960.00.
        $this->assertSame(360.00, $result['penalty']);
        $this->assertSame(3960.00, $result['total']);
    }

    public function test_deposit_boundary_check(): void
    {
        $room = $this->makeRoom('103', 'Basic', ['monthly_rent' => 3000]);
        // Total deposit = 1000 + 1500 + 700 + 300 = 3500.00
        $lease = $this->makeLease($room, $this->manager, tenantName: 'Boundary Tenant');
        $this->assertSame(3500.00, $lease->depositTotal());

        // Create a bill with total 3,000 (less than 3,500 deposit)
        $bill1 = Bill::create([
            'lease_id' => $lease->lease_id,
            'period_start' => '2026-09-01',
            'due_date' => '2026-10-01',
            'rent_amount' => 2800.00,
            'water_amount' => 200.00,
            'total_amount' => 3000.00,
            'penalty_amount' => 0.00,
            'status' => 'Unpaid',
            'user_id' => $this->manager->user_id,
        ]);

        $delinquency = app(DelinquencyService::class);
        $this->travelTo(Carbon::parse('2026-10-01'));

        // Case 1: Arrears (3,000) < Deposit (3,500)
        $this->assertFalse($delinquency->canTerminateClause8($lease));

        // Attempting to terminate under Clause 8 must be rejected
        $response = $this->actingAs($this->manager)
            ->post(route('leases.terminate-clause8', $lease));
        $response->assertSessionHasErrors('lease');
        $this->assertSame('Active', $lease->fresh()->status);

        // Case 2: Accrue second bill such that Arrears >= Deposit
        $bill2 = Bill::create([
            'lease_id' => $lease->lease_id,
            'period_start' => '2026-10-01',
            'due_date' => '2026-11-01',
            'rent_amount' => 1000.00,
            'water_amount' => 0.00,
            'total_amount' => 1000.00,
            'penalty_amount' => 0.00,
            'status' => 'Unpaid',
            'user_id' => $this->manager->user_id,
        ]);

        // Total arrears = 3,000 + 1,000 = 4,000 >= 3,500 deposit
        $this->assertTrue($delinquency->canTerminateClause8($lease));

        // Breach warning activates on Dashboard
        $dashResponse = $this->actingAs($this->manager)->get(route('dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('Deposit Exhausted');
        $dashResponse->assertSee('Eligible for termination');
        $dashResponse->assertDontSee('MOA Clause');

        // Breach warning activates on Payments Bills to Collect
        $payResponse = $this->actingAs($this->manager)->get(route('payments.index'));
        $payResponse->assertOk();
        $payResponse->assertSee('[!] Breach: Deposit Exhausted (Eligible for Termination under MOA Clause 8)');

        // Breach warning activates on Lease show page
        $leaseResponse = $this->actingAs($this->manager)->get(route('leases.show', $lease));
        $leaseResponse->assertOk();
        $leaseResponse->assertSee('[!] Breach: Deposit Exhausted (Eligible for Termination under MOA Clause 8)');
    }

    public function test_atomic_termination_under_clause_8(): void
    {
        $room = $this->makeRoom('104', 'Basic', ['monthly_rent' => 3000]);
        $lease = $this->makeLease($room, $this->manager, tenantName: 'Evicted Tenant');
        $tenant = $lease->tenant;

        // Total deposit = 3500.00
        $this->assertSame(3500.00, $lease->depositTotal());

        // Create 2 unpaid bills totaling 4000.00 (exceeds deposit)
        $bill1 = Bill::create([
            'lease_id' => $lease->lease_id,
            'period_start' => '2026-08-01',
            'due_date' => '2026-09-01',
            'rent_amount' => 2000.00,
            'total_amount' => 2000.00,
            'penalty_amount' => 0.00,
            'status' => 'Unpaid',
            'user_id' => $this->manager->user_id,
        ]);

        $bill2 = Bill::create([
            'lease_id' => $lease->lease_id,
            'period_start' => '2026-09-01',
            'due_date' => '2026-10-01',
            'rent_amount' => 2000.00,
            'total_amount' => 2000.00,
            'penalty_amount' => 0.00,
            'status' => 'Unpaid',
            'user_id' => $this->manager->user_id,
        ]);

        $this->travelTo(Carbon::parse('2026-10-10'));

        // Terminate under Clause 8
        $response = $this->actingAs($this->owner)
            ->post(route('leases.terminate-clause8', $lease), [
                'termination_notes' => 'Contract cancelled per MOA Line 8 default.',
            ]);

        $response->assertRedirect(route('leases.show', $lease));

        // 1. Bills are marked Paid and audit logged
        $this->assertSame('Paid', $bill1->fresh()->status);
        $this->assertSame('Paid', $bill2->fresh()->status);
        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Bill settled from deposit',
            'table_name' => 'tbl_bill',
            'record_id' => $bill1->bill_id,
        ]);
        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Bill settled from deposit',
            'table_name' => 'tbl_bill',
            'record_id' => $bill2->bill_id,
        ]);

        // 2. Lease is Cancelled
        $this->assertSame('Cancelled', $lease->fresh()->status);

        // 3. Tenant is Inactive
        $this->assertSame('Inactive', $tenant->fresh()->status);

        // 4. Room is Vacant with no expected vacant date
        $refreshedRoom = $room->fresh();
        $this->assertSame('Vacant', $refreshedRoom->status);
        $this->assertNull($refreshedRoom->expected_vacant_date);

        // 5. Audit logs for Lease, Room, and Tenant
        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Lease terminated under MOA Clause 8',
            'table_name' => 'tbl_lease',
            'record_id' => $lease->lease_id,
        ]);
        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Room repossessed under Clause 8',
            'table_name' => 'tbl_room',
            'record_id' => $room->room_id,
        ]);
        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Tenant deactivated',
            'table_name' => 'tbl_tenant',
            'record_id' => $tenant->tenant_id,
        ]);
    }

    public function test_caretaker_is_strictly_forbidden_from_clause8(): void
    {
        $room = $this->makeRoom('105', 'Basic', ['monthly_rent' => 3000]);
        $lease = $this->makeLease($room, $this->manager, tenantName: 'Forbidden Tenant');

        // Bills exceeding deposit
        Bill::create([
            'lease_id' => $lease->lease_id,
            'period_start' => '2026-08-01',
            'due_date' => '2026-09-01',
            'rent_amount' => 4000.00,
            'total_amount' => 4000.00,
            'penalty_amount' => 0.00,
            'status' => 'Unpaid',
            'user_id' => $this->manager->user_id,
        ]);

        // Caretaker dashboard does not see breach queue or financial data
        $dashResponse = $this->actingAs($this->caretaker)->get(route('dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertDontSee('Deposit Exhausted');
        $dashResponse->assertDontSee('Eligible for termination');

        // Caretaker POST terminate-clause8 is rejected with 403 Forbidden
        $postResponse = $this->actingAs($this->caretaker)
            ->post(route('leases.terminate-clause8', $lease));
        $postResponse->assertForbidden();
    }
}
