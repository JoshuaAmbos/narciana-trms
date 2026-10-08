<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Concern;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Moveout;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/**
 * Module 10: the whole process in order, through the screens, each step done by the role that does it in real life.
 *
 * Settings: water ₱200.00 per occupant, appliance ₱200.00, ₱15.00 per kWh, grace 3 days, refund within 7 days.
 *
 * Tenant A, Aircon room 216 (own meter), rent ₱4,000.00, 2 occupants, move-in 25 Jun 2026, due every 25th,
 * deposits 1,200 maintenance + 1,600 damage + 800 bills + 400 keys = 4,000.00, move-in reading 1,000 kWh.
 *   July bill   : rent 4,000 + water 2 × 200 = 400 + (1,120 − 1,000) × 15 = 1,800              = ₱6,200.00 (paid by GCash)
 *   Aug–Oct     : rent 4,000 + water 400 + 100 kWh × 15 = 1,500                                = ₱5,900.00 each (paid in cash)
 *   Final bill  : 25–28 Oct, rent 0 + water 400 + (1,450 − 1,420) × 15 = 450 + aircon repair 500 = ₱1,350.00 (unpaid)
 *   Settlement  : refundable 1,600 + 800 + 400 = 2,800 − final bill 1,350 − remote 300            = ₱1,150.00 refund
 *
 * Tenant B, Basic room 205 (no meter), rent ₱3,000.00, 1 occupant, 1 appliance (rice cooker):
 *   July bill   : rent 3,000 + water 200 + appliance 200                                         = ₱3,400.00
 */
class EndToEndTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private User $manager;

    private User $caretaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner, ['kwh_rate' => 15]);
        $this->manager = User::factory()->role('Manager')->create();
        $this->caretaker = User::factory()->role('Caretaker')->create();
    }

    private function on(string $date): void
    {
        $this->travelTo(Carbon::parse($date));
    }

    private function moa(Room $room, string $name, string $rent, array $deposits, array $extra = []): array
    {
        return $this->moaForm($room, array_merge([
            'full_name' => $name, 'moa_date' => '2026-06-24', 'move_in_date' => '2026-06-25', 'due_day' => '25',
            'monthly_rent' => $rent, 'advance_amount' => $rent,
            'deposit_maintenance' => $deposits[0], 'deposit_damage' => $deposits[1], 'deposit_bills' => $deposits[2], 'deposit_keys' => $deposits[3],
        ], $extra));
    }

    private function reading(float $previous, float $current, string $date): array
    {
        return ['previous_reading' => $previous, 'current_reading' => $current, 'reading_date' => $date, 'source' => 'Meter'];
    }

    public function test_the_whole_rental_process_from_inquiry_to_refund(): void
    {
        // ---------- Owner sets up the rooms ----------
        $this->on('2026-06-15 09:00');
        foreach ([['216', 'Aircon', '4000.00'], ['205', 'Basic', '3000.00']] as [$no, $type, $rent]) {
            $this->actingAs($this->owner)->post(route('rooms.store'), [
                'room_no' => $no, 'room_type' => $type, 'is_metered' => $type === 'Aircon' ? '1' : '0', 'max_occupants' => '2', 'monthly_rent' => $rent,
            ])->assertSessionHasNoErrors();
        }
        $aircon = Room::where('room_no', '216')->sole();
        $basic = Room::where('room_no', '205')->sole();
        $this->assertTrue($aircon->is_metered);
        $this->assertSame('Vacant', $aircon->status);

        // ---------- 1. Caretaker logs an inquiry ----------
        $this->on('2026-06-20 10:00');
        $inquiry = [
            'prospect_name' => 'Sample Prospect A', 'contact_no' => '0917 555 0101', 'preferred_type' => 'Aircon',
            'no_of_occupants' => '2', 'channel' => 'Walk-in', 'via_representative' => '0', 'inquiry_date' => '2026-06-20',
            'status' => 'Waiting', 'room_id' => '', 'remarks' => 'With a co-tenant',
        ];
        $this->actingAs($this->caretaker)->post(route('inquiries.store'), $inquiry)->assertSessionHasNoErrors();
        $inq = Inquiry::sole();
        $this->actingAs($this->caretaker)->get(route('inquiries.index', ['status' => 'Waiting']))->assertOk()
            ->assertSeeInOrder(['<th>Status</th>', 'Sample Prospect A', 'Waiting'], false);

        // ---------- 2. Manager marks it Rented ----------
        $this->on('2026-06-24 09:00');
        $this->actingAs($this->manager)->put(route('inquiries.update', $inq), ['status' => 'Rented', 'room_id' => $aircon->room_id] + $inquiry)
            ->assertSessionHasNoErrors();
        $this->assertSame('Rented', $inq->refresh()->status);
        $this->assertSame('Vacant', $aircon->refresh()->status, 'Marking Rented does not change the room; the MOA does');

        // ---------- 3. Manager encodes the tenant and MOA (2 occupants, move-in meter reading) ----------
        $this->actingAs($this->caretaker)->get(route('leases.create'))->assertForbidden();
        $this->actingAs($this->caretaker)->post(route('leases.store'), $this->moa($aircon, 'Sample Tenant A', '4000.00', ['1200.00', '1600.00', '800.00', '400.00']))
            ->assertForbidden();

        $this->actingAs($this->manager)->post(route('leases.store'), $this->moa($aircon, 'Sample Tenant A', '4000.00',
            ['1200.00', '1600.00', '800.00', '400.00'], ['co_tenant_name' => 'Sample Co-tenant A', 'move_in_reading' => '1000']))
            ->assertSessionHasNoErrors();
        $leaseA = Lease::whereHas('tenant', fn ($q) => $q->where('full_name', 'Sample Tenant A'))->sole();
        $this->assertSame(2, $leaseA->activeOccupants()->count());
        $this->assertSame('Occupied', $aircon->refresh()->status);
        $this->assertSame('Active', $leaseA->load('moveout')->displayStatus());

        // Second tenant in a Basic room (appliances are charged only in rooms without a meter).
        $this->actingAs($this->manager)->post(route('leases.store'), $this->moa($basic, 'Sample Tenant B', '3000.00', ['900.00', '1200.00', '600.00', '300.00']))
            ->assertSessionHasNoErrors();
        $leaseB = Lease::whereHas('tenant', fn ($q) => $q->where('full_name', 'Sample Tenant B'))->sole();

        // ---------- 4. Caretaker registers 1 appliance (Basic room) ----------
        $this->on('2026-06-25 18:00');
        $this->actingAs($this->caretaker)->post(route('leases.appliances.store', $leaseB),
            ['appliance_name' => 'Rice cooker', 'source' => 'Declared', 'date_added' => '2026-06-25'])->assertSessionHasNoErrors();
        $this->actingAs($this->caretaker)->post(route('leases.appliances.store', $leaseA),
            ['appliance_name' => 'Kettle', 'source' => 'Declared', 'date_added' => '2026-06-25'])->assertSessionHasErrors('appliance_name', null, 'appliance');
        $this->assertSame(1, $leaseB->activeAppliances()->count());
        $this->assertSame(0, $leaseA->appliances()->count());

        // ---------- 5. Manager enters the meter reading and generates the July bills ----------
        $this->on('2026-07-24 17:00');
        $this->actingAs($this->caretaker)->post(route('bills.store'), ['lease_id' => $leaseA->lease_id, 'due_date' => '2026-07-25']
            + $this->reading(1000, 1120, '2026-07-24'))->assertForbidden();

        $this->actingAs($this->manager)->post(route('bills.store'), ['lease_id' => $leaseA->lease_id, 'due_date' => '2026-07-25']
            + $this->reading(1000, 1120, '2026-07-24'))->assertSessionHasNoErrors();
        $billA = Bill::where('lease_id', $leaseA->lease_id)->where('is_initial', false)->sole();
        $this->assertSame(['4000.00', '400.00', '1800.00', '0.00', '0.00', '0.00', '6200.00', 'Unpaid', '2026-06-25', '2026-07-25'], [
            $billA->rent_amount, $billA->water_amount, $billA->electricity_amount, $billA->appliance_amount, $billA->penalty_amount,
            $billA->other_charges, $billA->total_amount, $billA->status, $billA->period_start->toDateString(), $billA->due_date->toDateString(),
        ]);
        $this->assertSame('15.00', $billA->meterReading->rate_per_kwh);
        $this->actingAs($this->manager)->get(route('bills.show', $billA))->assertOk()
            ->assertSee('₱6,200.00')->assertSee('1,000')->assertSee('1,120');

        $this->actingAs($this->manager)->post(route('bills.store'), ['lease_id' => $leaseB->lease_id, 'due_date' => '2026-07-25'])->assertSessionHasNoErrors();
        $billB = Bill::where('lease_id', $leaseB->lease_id)->where('is_initial', false)->sole();
        $this->assertSame(['3000.00', '200.00', '0.00', '200.00', '3400.00'],
            [$billB->rent_amount, $billB->water_amount, $billB->electricity_amount, $billB->appliance_amount, $billB->total_amount]);

        // ---------- 6. Manager records a GCash payment; the bill shows Paid ----------
        $this->on('2026-07-25 10:00');
        $this->actingAs($this->caretaker)->post(route('payments.store'), ['bill_id' => $billA->bill_id, 'payment_date' => '2026-07-25',
            'mode' => 'GCash', 'gcash_ref_no' => '9171234567890', 'amount' => '6200.00'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('payments.store'), ['bill_id' => $billA->bill_id, 'payment_date' => '2026-07-25',
            'mode' => 'GCash', 'gcash_ref_no' => '9171 234 567 890', 'amount' => '6200.00'])->assertSessionHasNoErrors();

        $payment = Payment::where('bill_id', $billA->bill_id)->sole();
        $this->assertSame(['ACK-2026-0003', '9171234567890', '6200.00', $this->manager->user_id],
            [$payment->ack_no, $payment->gcash_ref_no, $payment->amount, $payment->user_id]);
        $this->assertSame('Paid', $billA->refresh()->status);
        $this->actingAs($this->manager)->get(route('bills.show', $billA))->assertOk()->assertSee('Paid')->assertSee('ACK-2026-0003');
        $this->actingAs($this->manager)->get(route('payments.show', $payment))->assertOk()->assertSee('ACK-2026-0003')->assertSee('GCash');

        // ---------- 7. Caretaker logs a concern with a photo and resolves it; the Manager sets the cost ----------
        $this->on('2026-07-28 20:00');
        $concernForm = [
            'category' => 'Aircon', 'room_id' => $aircon->room_id, 'lease_id' => $leaseA->lease_id,
            'reported_at' => '2026-07-28T19:45', 'channel' => 'Group chat', 'priority' => 'Routine',
            'description' => 'Aircon not cooling', 'assigned_to' => $this->caretaker->user_id, 'status' => 'Open',
        ];
        $this->actingAs($this->caretaker)->post(route('concerns.store'), $concernForm + ['photo' => UploadedFile::fake()->create('aircon.jpg', 100, 'image/jpeg')])
            ->assertSessionHasNoErrors();
        $concern = Concern::sole();
        Storage::disk('local')->assertExists($concern->photo_path);

        $this->on('2026-07-29 11:00');
        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern), ['status' => 'Resolved',
            'resolution' => 'Technician cleaned the filter; tenant broke the vent cover',
            'resolution_photo' => UploadedFile::fake()->create('fixed.jpg', 100, 'image/jpeg')] + $concernForm)->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->put(route('concerns.update', $concern), ['status' => 'Resolved',
            'resolution' => 'Technician cleaned the filter; tenant broke the vent cover', 'cost' => '500.00', 'charged_to_tenant' => '1'] + $concernForm)
            ->assertSessionHasNoErrors();
        $concern->refresh();
        $this->assertSame(['Resolved', '500.00', true, $this->caretaker->user_id, $this->caretaker->user_id],
            [$concern->status, $concern->cost, $concern->charged_to_tenant, $concern->reported_by, $concern->resolved_by]);
        $this->assertNotNull($concern->resolution_photo_path);
        $this->actingAs($this->caretaker)->get(route('concerns.show', $concern))->assertOk()
            ->assertSeeInOrder(['Reported', '28 Jul 2026, 7:45 PM', 'Resolved', '29 Jul 2026, 11:00 AM']);
        $this->actingAs($this->caretaker)->get(route('concerns.photo', [$concern, 'resolved']))->assertOk();

        // ---------- Regular bills for Aug–Oct, paid in cash (the repair is kept for the final bill) ----------
        $monthly = function (string $month, float $prev, float $cur) use ($leaseA): void {
            $this->on("{$month}-24 17:00");
            $this->actingAs($this->manager)->post(route('bills.store'), ['lease_id' => $leaseA->lease_id, 'due_date' => "{$month}-25"]
                + $this->reading($prev, $cur, "{$month}-24"))->assertSessionHasNoErrors();
            $bill = Bill::where('lease_id', $leaseA->lease_id)->latest('due_date')->first();
            $this->assertSame('5900.00', $bill->total_amount);

            $this->on("{$month}-25 10:00");
            $this->actingAs($this->manager)->post(route('payments.store'), ['bill_id' => $bill->bill_id, 'payment_date' => "{$month}-25",
                'mode' => 'Cash', 'amount' => '5900.00'])->assertSessionHasNoErrors();
        };
        $monthly('2026-08', 1120, 1220);
        $monthly('2026-09', 1220, 1320);

        // ---------- 8. Move-out: one month's notice on 25 Sep (Manager) ----------
        $this->actingAs($this->manager)->post(route('moveouts.store'), ['lease_id' => $leaseA->lease_id,
            'notice_date' => '2026-09-25', 'planned_date' => '2026-10-28'])->assertSessionHasNoErrors()->assertSessionMissing('warning');
        $moveout = Moveout::sole();
        $this->assertSame('Vacating', $aircon->refresh()->status);
        $this->assertSame('2026-10-28', $aircon->expected_vacant_date->toDateString());
        $this->assertSame('Moving out', $leaseA->refresh()->load('moveout')->displayStatus());
        $this->actingAs($this->manager)->get(route('leases.index', ['status' => 'Moving out']))->assertSee('Sample Tenant A');
        $this->actingAs($this->manager)->get('/')->assertSee('Moving out')->assertSee('Sample Tenant A');

        // The October bill still falls before the move-out date.
        $monthly('2026-10', 1320, 1420);

        // Moved out on 28 Oct: final bill (final reading + the repair), inspection, computation.
        $this->on('2026-10-28 16:00');
        $this->actingAs($this->manager)->put(route('moveouts.update', $moveout), ['notice_date' => '2026-09-25',
            'planned_date' => '2026-10-28', 'actual_date' => '2026-10-28'])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('moveouts.final-bill.store', $moveout),
            $this->reading(1420, 1450, '2026-10-28') + ['concerns' => [$concern->concern_id]])->assertSessionHasNoErrors();
        $final = $moveout->refresh()->finalBill;
        $this->assertSame(['0.00', '400.00', '450.00', '500.00', '1350.00', '2026-10-28'],
            [$final->rent_amount, $final->water_amount, $final->electricity_amount, $final->other_charges, $final->total_amount, $final->due_date->toDateString()]);
        $this->assertSame($final->bill_id, $concern->refresh()->bill_id, 'The repair is charged once, on the final bill');

        $this->actingAs($this->manager)->post(route('moveouts.compute', $moveout))->assertSessionHasErrors('moveout'); // inspection first
        $this->actingAs($this->manager)->post(route('moveouts.items.store', $moveout), ['description' => 'Aircon remote control missing', 'cost' => '300.00'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->patch(route('moveouts.inspect', $moveout))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('moveouts.compute', $moveout))->assertSessionHasNoErrors();

        $moveout->refresh();
        $this->assertSame(['1350.00', '300.00', '1150.00', '0.00', '2026-11-04', false],
            [$moveout->unpaid_balance, $moveout->damage_total, $moveout->refund_amount, $moveout->excess_amount,
                $moveout->refund_due_date->toDateString(), $moveout->early_departure]);
        $this->actingAs($this->manager)->get(route('moveouts.show', $moveout))->assertOk()
            ->assertSeeInOrder(['Refundable deposit', '₱2,800.00', 'Bill due 28 Oct 2026', '(₱1,350.00)',
                'Aircon remote control missing', '(₱300.00)', 'Final refund to tenant', '₱1,150.00']);

        // ---------- Owner approval, then the refund is released ----------
        $this->actingAs($this->manager)->patch(route('moveouts.approve', $moveout))->assertForbidden();
        $this->actingAs($this->owner)->patch(route('moveouts.approve', $moveout))->assertSessionHasNoErrors();
        $this->assertSame('Refunded', $moveout->refresh()->status);
        $this->assertSame('Paid', $final->refresh()->status, 'Final bill settled from the deposit');

        $this->on('2026-11-02 10:00');
        $this->actingAs($this->manager)->patch(route('moveouts.release', $moveout))->assertForbidden();
        $this->actingAs($this->owner)->patch(route('moveouts.release', $moveout))->assertSessionHasNoErrors();

        // ---------- The end ----------
        $this->assertSame('Settled', $moveout->refresh()->status);
        $this->assertSame('Ended', $leaseA->refresh()->status);
        $this->assertSame('Moved out', $leaseA->load('moveout')->displayStatus());
        $aircon->refresh();
        $this->assertSame('Vacant', $aircon->status);
        $this->assertNull($aircon->expected_vacant_date);
        $this->assertSame(0, Bill::where('lease_id', $leaseA->lease_id)->where('status', '!=', 'Paid')->count());
        $this->actingAs($this->manager)->get(route('leases.index', ['status' => 'Moved out']))->assertSee('Sample Tenant A');

        // Every step left its mark in the audit log, by the right person.
        foreach ([
            ['Room created', $this->owner], ['Lease created', $this->manager], ['Bill generated', $this->manager],
            ['Payment recorded', $this->manager], ['Concern logged', $this->caretaker], ['Concern resolved', $this->caretaker],
            ['Concern charged', $this->manager], ['Move-out notice', $this->manager], ['Move-out recorded', $this->manager],
            ['Final bill generated', $this->manager], ['Move-out inspected', $this->manager], ['Settlement computed', $this->manager],
            ['Settlement approved', $this->owner], ['Bill settled from deposit', $this->owner], ['Refund released', $this->owner],
            ['Settlement settled', $this->owner],
        ] as [$action, $user]) {
            $this->assertTrue(AuditLog::where('action', $action)->where('user_id', $user->user_id)->exists(), "Audit: {$action} by {$user->role}");
        }
    }
}
