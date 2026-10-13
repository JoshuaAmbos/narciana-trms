<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\BackupRunner;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\FakeBackupRunner;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/** Settings, Backup and Restore, and the Audit Log are Owner only: hidden menus and 403 for everyone else. */
class OwnerToolsAccessTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backups-'.uniqid());
        config(['narciana.backup.path' => $this->dir]);
        $this->app->instance(BackupRunner::class, new FakeBackupRunner);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** [method, url, data] for every Settings / Backup / Audit Log URL. */
    private function urls(string $backup): array
    {
        return [
            ['get', route('settings.index'), []],
            ['put', route('settings.update'), ['water_rate' => '1']],
            ['get', route('backups.index'), []],
            ['post', route('backups.store'), []],
            ['get', route('backups.download', $backup), []],
            ['delete', route('backups.destroy', $backup), []],
            ['post', route('backups.restore'), ['backup_file' => $backup, 'password' => 'password']],
            ['get', route('audit-log.index'), []],
        ];
    }

    public function test_manager_and_caretaker_get_403_on_every_url_and_nothing_happens(): void
    {
        $owner = User::factory()->role('Owner')->create();
        $this->settings($owner);
        $backup = app(BackupService::class)->create('Manual', $owner)['name'];

        foreach (['Manager', 'Caretaker'] as $role) {
            $user = User::factory()->role($role)->create();
            foreach ($this->urls($backup) as [$method, $url, $data]) {
                $this->actingAs($user)->{$method}($url, $data)->assertForbidden();
            }
        }

        $this->assertFileExists($this->dir.'/'.$backup);
        $this->assertCount(1, File::glob($this->dir.'/*.sql'));
        $this->assertSame('200.00', Setting::current()->water_rate);
    }

    public function test_menus_are_hidden_from_manager_and_caretaker_and_shown_to_the_owner(): void
    {
        $owner = User::factory()->role('Owner')->create();
        $this->settings($owner);

        foreach (['Manager', 'Caretaker'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get('/')->assertOk()
                ->assertDontSee('Settings &amp; Backup', false)->assertDontSee(route('settings.index'), false)
                ->assertDontSee('Audit Log')->assertDontSee(route('audit-log.index'), false);
        }

        $this->actingAs($owner)->get('/')->assertOk()
            ->assertSee(route('settings.index'), false)->assertSee('Settings &amp; Backup', false)
            ->assertSee(route('audit-log.index'), false)->assertSee('Audit Log');
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        foreach ([route('settings.index'), route('backups.index'), route('audit-log.index')] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_owner_opens_all_three_pages(): void
    {
        $owner = User::factory()->role('Owner')->create();
        $this->settings($owner);

        $this->actingAs($owner)->get(route('settings.index'))->assertOk()->assertSee('Rates and rules');
        $this->actingAs($owner)->get(route('backups.index'))->assertOk()->assertSee('Create Backup Now');
        $this->actingAs($owner)->get(route('audit-log.index'))->assertOk()->assertSee('view only');
    }
}
