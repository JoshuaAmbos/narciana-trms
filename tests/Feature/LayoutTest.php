<?php

namespace Tests\Feature;

use App\Models\User;
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

        $this->actingAs(User::where('username', 'owner')->first())
            ->get('/')
            ->assertOk()
            ->assertSee('Narciana House')
            ->assertSee('Rooms &amp; Inquiries', false)
            ->assertSee('Settings &amp; Backup', false)
            ->assertSee('Due and overdue this week')
            ->assertDontSee('Setup check');
    }

    public function test_dashboard_shows_sample_bills_with_peso_amounts(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->actingAs(User::factory()->role('Manager')->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Overdue')
            ->assertSeeInOrder(['Due and overdue this week', '₱']);
    }

    public function test_peso_directive_formats_money(): void
    {
        $this->assertSame('₱5,620.00', Blade::render('@peso(5620)'));
        $this->assertSame('₱0.00', Blade::render('@peso(null)'));
        $this->assertSame('₱1,234,567.89', Blade::render('@peso("1234567.89")'));
    }

    public function test_every_menu_item_has_an_inline_lucide_icon(): void
    {
        foreach (config('narciana.nav') as [, $label, , $icon]) {
            $svg = Blade::render('<x-icon :name="$icon" />', ['icon' => $icon]);
            $this->assertMatchesRegularExpression('/<svg[^>]*>\s*<(path|rect|circle|polyline)/', $svg, "Icon '{$icon}' for {$label} is missing");
        }
    }

    public function test_active_menu_item_is_highlighted(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')->assertOk()->assertSee('class="on"', false)->assertSee('icon-layout-dashboard', false);
    }

    public function test_app_runs_in_manila_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
    }
}
