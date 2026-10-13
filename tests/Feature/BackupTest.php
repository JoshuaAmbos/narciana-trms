<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\BackupRunner;
use App\Services\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\Support\FakeBackupRunner;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/** Backup and Restore (Owner only). mysqldump / mysql are replaced by FakeBackupRunner except in the last test. */
class BackupTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private FakeBackupRunner $runner;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);

        $this->dir = storage_path('framework/testing/backups-'.uniqid());
        config(['narciana.backup.path' => $this->dir]);
        $this->runner = new FakeBackupRunner;
        $this->app->instance(BackupRunner::class, $this->runner);
        $this->travelTo(Carbon::parse('2026-10-02 14:30:00'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function backups(): BackupService
    {
        return app(BackupService::class);
    }

    public function test_owner_creates_a_backup_now(): void
    {
        $this->actingAs($this->owner)->post(route('backups.store'))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'backup-2026-10-02_143000.sql'));

        $this->assertFileExists($this->dir.'/backup-2026-10-02_143000.sql');
        $backup = $this->backups()->list()->sole();
        $this->assertSame('Manual', $backup['type']);
        $this->assertSame('Test Owner (Owner)', $backup['by']);

        $log = AuditLog::where('action', 'Backup created')->sole();
        $this->assertSame($this->owner->user_id, $log->user_id);
        $this->assertStringContainsString('Manual: backup-2026-10-02_143000.sql', $log->details);
    }

    public function test_backup_page_lists_date_size_and_who_made_it(): void
    {
        $this->backups()->create('Manual', $this->owner);

        $this->actingAs($this->owner)->get(route('backups.index'))->assertOk()
            ->assertSee('Create Backup Now')
            ->assertSee('2 Oct 2026, 2:30 PM')
            ->assertSee('bytes')
            ->assertSee('Test Owner (Owner)')
            ->assertSee(route('backups.download', 'backup-2026-10-02_143000.sql'), false)
            ->assertSee('Confirm Account Password')
            ->assertDontSee('Type RESTORE to confirm');
    }

    public function test_a_failed_backup_leaves_no_file_and_shows_the_error(): void
    {
        $this->runner->failDump = true;

        $this->actingAs($this->owner)->from(route('backups.index'))->post(route('backups.store'))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'Access denied'));

        $this->assertCount(0, $this->backups()->list());
        $this->assertSame([], File::glob($this->dir.'/*.sql'));
        $this->assertSame(0, AuditLog::where('action', 'Backup created')->count());
    }

    public function test_download_sends_the_file_and_is_audited(): void
    {
        $name = $this->backups()->create('Manual', $this->owner)['name'];

        $this->actingAs($this->owner)->get(route('backups.download', $name))
            ->assertOk()->assertDownload($name);

        $this->assertSame($name, AuditLog::where('action', 'Backup downloaded')->sole()->details);
    }

    public function test_only_backup_files_can_be_downloaded_or_deleted(): void
    {
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/notes.sql', 'x');

        foreach (['notes.sql', 'backup-2026-10-02_999999.sql', '..%2F..%2F.env', '.htaccess'] as $name) {
            $this->actingAs($this->owner)->get("/backups/{$name}/download")->assertNotFound();
            $this->actingAs($this->owner)->delete("/backups/{$name}")->assertNotFound();
        }
        $this->assertFileExists($this->dir.'/notes.sql');
    }

    public function test_delete_removes_the_backup_and_is_audited(): void
    {
        $name = $this->backups()->create('Manual', $this->owner)['name'];

        $this->actingAs($this->owner)->delete(route('backups.destroy', $name))->assertRedirect(route('backups.index'));

        $this->assertFileDoesNotExist($this->dir.'/'.$name);
        $this->assertFileDoesNotExist($this->dir.'/'.str_replace('.sql', '.json', $name));
        $this->assertSame($name, AuditLog::where('action', 'Backup deleted')->sole()->details);
    }

    public function test_backup_run_command_keeps_only_the_newest_30(): void
    {
        for ($i = 0; $i < 32; $i++) {
            $this->artisan('backup:run')->assertSuccessful();
            $this->travel(1)->hours();
        }

        $list = $this->backups()->list();
        $this->assertCount(30, $list);
        $this->assertSame('backup-2026-10-03_213000.sql', $list->first()['name']); // newest
        $this->assertSame('backup-2026-10-02_163000.sql', $list->last()['name']);  // the 14:30 and 15:30 ones were removed
        $this->assertSame('Scheduled', $list->first()['type']);
        $this->assertSame('Automatic (scheduled)', $list->first()['by']);

        // Scheduled backups are logged under the Owner account (the audit log needs a user).
        $this->assertSame(32, AuditLog::where('action', 'Backup created')->where('user_id', $this->owner->user_id)->count());
        $this->assertSame(2, AuditLog::where('action', 'Backup deleted')->count());
    }

    public function test_backup_run_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'backup:run'));

        $this->assertNotNull($event, 'backup:run is not scheduled');
        $this->assertSame('0 2 * * *', $event->expression);
        $this->assertSame('Asia/Manila', $event->timezone);
    }

    public function test_restore_requires_account_password(): void
    {
        $name = $this->backups()->create('Manual', $this->owner)['name'];

        // Missing password
        $this->actingAs($this->owner)->post(route('backups.restore'), ['backup_file' => $name, 'password' => ''])
            ->assertSessionHasErrors('password');

        // Incorrect password
        $this->actingAs($this->owner)->post(route('backups.restore'), ['backup_file' => $name, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('password')
            ->assertSessionHas('warning', 'The provided password does not match your account password.');

        $this->assertSame([], $this->runner->imported);
        $this->assertCount(1, $this->backups()->list()); // no safety backup either
        $this->assertAuthenticatedAs($this->owner);

        // Security event recorded in tbl_audit_log
        $failedLog = AuditLog::where('action', 'Database restoration failed: Invalid password authorization')->first();
        $this->assertNotNull($failedLog);
        $this->assertSame($this->owner->user_id, $failedLog->user_id);

        // Missing backup file
        $this->actingAs($this->owner)->post(route('backups.restore'), ['backup_file' => '', 'password' => 'password'])
            ->assertSessionHasErrors('backup_file');
    }

    public function test_restore_of_an_unknown_backup_is_refused_before_anything_happens(): void
    {
        $this->actingAs($this->owner)->post(route('backups.restore'), ['backup_file' => '../.env', 'password' => 'password'])->assertNotFound();

        $this->assertSame([], $this->runner->imported);
        $this->assertCount(0, $this->backups()->list());
    }

    public function test_restore_makes_a_safety_backup_restores_logs_and_logs_out(): void
    {
        $name = $this->backups()->create('Manual', $this->owner)['name'];
        $this->travel(5)->minutes();

        $this->actingAs($this->owner)->post(route('backups.restore'), ['backup_file' => $name, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'backup-2026-10-02_143500.sql'));

        $this->assertGuest();
        $this->assertSame([$name], $this->runner->imported);

        $safety = $this->backups()->list()->firstWhere('type', 'Safety');
        $this->assertSame('backup-2026-10-02_143500.sql', $safety['name']);

        $log = AuditLog::where('action', 'Backup restored')->sole();
        $this->assertSame($this->owner->user_id, $log->user_id);
        $this->assertSame("Restored {$name}; safety backup backup-2026-10-02_143500.sql", $log->details);

        $authLog = AuditLog::where('action', "Database restored from {$name} via password authorization")->first();
        $this->assertNotNull($authLog);
    }

    public function test_restore_is_not_attempted_when_the_safety_backup_fails(): void
    {
        $name = $this->backups()->create('Manual', $this->owner)['name'];
        $this->runner->failDump = true;

        $this->actingAs($this->owner)->from(route('backups.index'))
            ->post(route('backups.restore'), ['backup_file' => $name, 'password' => 'password'])
            ->assertRedirect(route('backups.index'))->assertSessionHas('error');

        $this->assertSame([], $this->runner->imported);
        $this->assertAuthenticatedAs($this->owner);
    }

    /** The real mysqldump on this machine (path from BACKUP_MYSQLDUMP_PATH). Skipped where it is not installed. */
    public function test_real_mysqldump_writes_a_backup_file(): void
    {
        $this->app->instance(BackupRunner::class, new BackupRunner);

        try {
            $backup = $this->backups()->create('Manual', $this->owner);
        } catch (RuntimeException $e) {
            $this->markTestSkipped('mysqldump is not available here: '.$e->getMessage());
        }

        $sql = File::get($this->dir.'/'.$backup['name']);
        $this->assertStringContainsString('CREATE TABLE `tbl_setting`', $sql);
        $this->assertStringContainsString('CREATE TABLE `tbl_audit_log`', $sql);
        $this->assertStringNotContainsString('CREATE TABLE `sessions`', $sql);
    }
}
