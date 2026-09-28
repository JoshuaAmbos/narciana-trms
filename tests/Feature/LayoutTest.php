<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_loads_with_sidebar_menu(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('Narciana House')
            ->assertSee('Rooms &amp; Inquiries', false)
            ->assertSee('Settings &amp; Backup', false)
            ->assertSee('Present');
    }

    public function test_dashboard_lists_sample_rooms_with_peso_amounts(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('208')
            ->assertSee('₱3,600.00');
    }

    public function test_peso_directive_formats_money(): void
    {
        $this->assertSame('₱5,620.00', Blade::render('@peso(5620)'));
        $this->assertSame('₱0.00', Blade::render('@peso(null)'));
        $this->assertSame('₱1,234,567.89', Blade::render('@peso("1234567.89")'));
    }

    public function test_app_runs_in_manila_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
    }
}
