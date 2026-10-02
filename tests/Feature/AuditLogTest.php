<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/** Audit Log viewer (Owner only): newest first, paginated, filters, and entries can never be changed. */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create(['full_name' => 'Owner Account']);
        $this->manager = User::factory()->role('Manager')->create(['full_name' => 'Manager Account']);
    }

    private function entry(User $user, string $action, string $at, string $table = 'tbl_bill', int $id = 1, ?string $details = null): AuditLog
    {
        return AuditLog::create(['user_id' => $user->user_id, 'action' => $action, 'table_name' => $table,
            'record_id' => $id, 'details' => $details, 'logged_at' => $at]);
    }

    public function test_entries_are_listed_newest_first_with_all_columns(): void
    {
        $this->entry($this->manager, 'Bill generated', '2026-09-01 08:00:00', 'tbl_bill', 12);
        $this->entry($this->owner, 'Settings changed', '2026-09-03 09:15:00', 'tbl_setting', 1, 'Water rate (per occupant): ₱200.00 → ₱250.00');

        $this->actingAs($this->owner)->get(route('audit-log.index'))->assertOk()
            ->assertSeeInOrder(['3 Sep 2026', '9:15:00 AM', 'Owner Account', 'Settings changed', 'Settings #1', '₱200.00 → ₱250.00',
                '1 Sep 2026', 'Manager Account', 'Bill generated', 'Bill #12']);
    }

    public function test_the_log_is_paginated_25_per_page(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->entry($this->manager, 'Bill generated', sprintf('2026-09-%02d 08:00:00', $i), 'tbl_bill', $i);
        }

        $this->actingAs($this->owner)->get(route('audit-log.index'))->assertOk()
            ->assertSee('Page 1 of 2 (30 in all)')->assertSee('Bill #30')->assertDontSee('Bill #5<');
        $this->actingAs($this->owner)->get(route('audit-log.index', ['page' => 2]))->assertOk()
            ->assertSee('Bill #5')->assertDontSee('Bill #6<', false);
    }

    public function test_filter_by_user(): void
    {
        $this->entry($this->manager, 'Payment recorded', '2026-09-02 10:00:00', 'tbl_payment', 7);
        $this->entry($this->owner, 'User created', '2026-09-02 11:00:00', 'tbl_user', 3);

        $this->actingAs($this->owner)->get(route('audit-log.index', ['user' => $this->manager->user_id]))->assertOk()
            ->assertSee('Payment #7')->assertDontSee('User #3');
    }

    public function test_filter_by_action_type_includes_its_bracketed_variants(): void
    {
        $this->entry($this->owner, 'Payment voided (ACK-2026-0001)', '2026-09-02 10:00:00', 'tbl_payment', 1);
        $this->entry($this->owner, 'Payment voided (ACK-2026-0002)', '2026-09-03 10:00:00', 'tbl_payment', 2);
        $this->entry($this->owner, 'Payment recorded', '2026-09-04 10:00:00', 'tbl_payment', 3);

        $response = $this->actingAs($this->owner)->get(route('audit-log.index', ['action' => 'Payment voided']))->assertOk()
            ->assertSee('ACK-2026-0001')->assertSee('ACK-2026-0002')->assertDontSee('Payment #3');

        // The action list offers "Payment voided" once, not once per ACK number.
        $this->assertSame(1, substr_count($response->getContent(), '<option value="Payment voided"'));
    }

    public function test_filter_by_date_range(): void
    {
        $this->entry($this->manager, 'Bill generated', '2026-08-31 23:59:00', 'tbl_bill', 1);
        $this->entry($this->manager, 'Bill generated', '2026-09-01 00:00:00', 'tbl_bill', 2);
        $this->entry($this->manager, 'Bill generated', '2026-09-30 23:59:00', 'tbl_bill', 3);
        $this->entry($this->manager, 'Bill generated', '2026-10-01 00:00:00', 'tbl_bill', 4);

        $this->actingAs($this->owner)->get(route('audit-log.index', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk()
            ->assertSee('Bill #2')->assertSee('Bill #3')->assertDontSee('Bill #1<', false)->assertDontSee('Bill #4');
    }

    public function test_filters_combine_and_bad_filters_are_rejected(): void
    {
        $this->entry($this->manager, 'Bill generated', '2026-09-05 08:00:00', 'tbl_bill', 1);
        $this->entry($this->owner, 'Bill generated', '2026-09-05 09:00:00', 'tbl_bill', 2);
        $this->entry($this->manager, 'Bill deleted', '2026-09-05 10:00:00', 'tbl_bill', 3);

        $this->actingAs($this->owner)
            ->get(route('audit-log.index', ['user' => $this->manager->user_id, 'action' => 'Bill generated', 'from' => '2026-09-05', 'to' => '2026-09-05']))
            ->assertOk()->assertSee('Bill #1')->assertDontSee('Bill #2')->assertDontSee('Bill #3');

        $this->actingAs($this->owner)->get(route('audit-log.index', ['from' => '2026-09-30', 'to' => '2026-09-01']))->assertSessionHasErrors('to');
        $this->actingAs($this->owner)->get(route('audit-log.index', ['user' => 99999]))->assertSessionHasErrors('user');
        $this->actingAs($this->owner)->get(route('audit-log.index', ['from' => 'yesterday']))->assertSessionHasErrors('from');
    }

    public function test_entries_cannot_be_changed_or_deleted_by_anyone(): void
    {
        $log = $this->entry($this->manager, 'Bill generated', '2026-09-05 08:00:00', 'tbl_bill', 1);

        // No routes exist for it, not even for the Owner.
        $this->actingAs($this->owner)->put('/audit-log/'.$log->log_id, ['action' => 'x'])->assertNotFound();
        $this->actingAs($this->owner)->delete('/audit-log/'.$log->log_id)->assertNotFound();
        $this->actingAs($this->owner)->delete('/audit-log')->assertStatus(405);

        // And the model refuses, so no future code can do it by mistake.
        try {
            $log->update(['action' => 'Changed']);
            $this->fail('An audit entry was changed.');
        } catch (LogicException) {
        }
        try {
            $log->delete();
            $this->fail('An audit entry was deleted.');
        } catch (LogicException) {
        }
        $this->assertSame('Bill generated', AuditLog::find($log->log_id)->action);
    }
}
