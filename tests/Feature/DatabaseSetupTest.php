<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSetupTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'tbl_user', 'tbl_room', 'tbl_setting', 'tbl_audit_log', 'tbl_inquiry', 'tbl_tenant',
        'tbl_lease', 'tbl_occupant', 'tbl_visitor', 'tbl_appliance', 'tbl_meter_reading',
        'tbl_bill', 'tbl_payment', 'tbl_concern', 'tbl_moveout', 'tbl_moveout_item',
    ];

    public function test_all_sixteen_tables_exist(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
        $this->assertFalse(Schema::hasTable('users'), "Laravel's default users table should not exist");
    }

    public function test_bill_table_has_every_column_from_the_data_dictionary(): void
    {
        $this->assertTrue(Schema::hasColumns('tbl_bill', [
            'bill_id', 'lease_id', 'reading_id', 'period_start', 'due_date', 'rent_amount', 'water_amount',
            'electricity_amount', 'appliance_amount', 'penalty_amount', 'other_charges', 'total_amount',
            'status', 'user_id',
        ]));
    }

    public function test_seeder_creates_three_staff_accounts_with_hashed_passwords(): void
    {
        config(['narciana.seed_passwords.owner' => 'owner-test-pass']);

        $this->seed();

        $this->assertSame(['Owner', 'Manager', 'Caretaker'], User::orderBy('user_id')->pluck('role')->all());

        $owner = User::where('username', 'owner')->first();
        $this->assertTrue($owner->is_active);
        $this->assertNotSame('owner-test-pass', $owner->password_hash, 'Password must not be stored in plain text');
        $this->assertTrue(Hash::check('owner-test-pass', $owner->password_hash));
    }

    public function test_seeder_can_run_twice_without_duplicates_or_password_reset(): void
    {
        config(['narciana.seed_passwords.owner' => 'first-pass']);
        $this->seed();

        config(['narciana.seed_passwords.owner' => 'second-pass']);
        $this->seed();

        $this->assertSame(3, User::count());
        $this->assertSame(1, Setting::count());
        $this->assertTrue(Hash::check('first-pass', User::where('username', 'owner')->value('password_hash')));
    }

    public function test_default_settings_follow_the_agreed_decisions(): void
    {
        $this->seed();

        $s = Setting::first();
        $this->assertSame('200.00', $s->water_rate);
        $this->assertSame('200.00', $s->appliance_fee);
        $this->assertSame('15.00', $s->kwh_rate);
        $this->assertSame(3, $s->grace_days);
        $this->assertSame('Per day of rent', $s->penalty_basis);
        $this->assertSame('1.00', $s->penalty_rate);
        $this->assertSame(7, $s->refund_days);
        $this->assertSame('Owner', $s->updatedBy->role);
    }

    public function test_main_seeder_does_not_create_sample_rooms(): void
    {
        $this->seed();

        $this->assertSame(0, Room::count());
    }

    public function test_demo_seeder_creates_sample_rooms(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(11, Room::count());
        $this->assertSame(4, Room::where('room_type', 'Aircon')->where('is_metered', true)->count());
        $this->assertSame(0, Room::where('room_type', 'Basic')->where('is_metered', true)->count());
        $this->assertSame(11, Room::where('status', 'Vacant')->where('max_occupants', 2)->count());
    }
}
