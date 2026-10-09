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
            ->assertSeeInOrder(['icon-door-open', 'Rooms', 'icon-clipboard-list', 'Inquiries'], false)->assertDontSee('Rooms &amp; Inquiries', false)
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
        foreach (collect(config('narciana.nav'))->flatten(1) as [, $label, , $icon]) {
            $svg = Blade::render('<x-icon :name="$icon" />', ['icon' => $icon]);
            $this->assertMatchesRegularExpression('/<svg[^>]*>\s*<(path|rect|circle|polyline)/', $svg, "Icon '{$icon}' for {$label} is missing");
        }
    }

    public function test_active_menu_item_is_highlighted(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')->assertOk()->assertSee('class="on"', false)->assertSee('icon-layout-dashboard', false);
    }

    /** Module 10: for each role, every sidebar link opens (no "#", no 403/404/500) and other roles' items are hidden. */
    public function test_every_sidebar_link_works_for_each_role(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);

        foreach (['owner' => 'Owner', 'manager' => 'Manager', 'caretaker' => 'Caretaker'] as $username => $role) {
            $user = User::where('username', $username)->firstOrFail();
            $html = $this->actingAs($user)->get('/')->assertOk()->getContent();
            preg_match('#<nav class="nav">(.*?)</nav>#s', $html, $nav);
            preg_match_all('#<a href="([^"]*)"#', $nav[1], $links);

            $expected = collect(config('narciana.nav'))->flatten(1)->filter(fn ($item) => in_array($role, $item[2], true))
                ->map(fn ($item) => route($item[0]))->values()->all();
            $this->assertSame($expected, $links[1], "{$role} sees exactly their menu items");

            foreach ($links[1] as $href) {
                $this->assertNotSame('#', $href);
                $this->actingAs($user)->get($href)->assertOk();
            }
        }
    }

    /** Decision 19: grouped by section and ordered from most to least used; each role sees its own items in it. */
    public function test_sidebar_order_for_each_role(): void
    {
        $order = ['Dashboard', 'Billing', 'Payments', 'Concerns', 'Tenants', 'Rooms', 'Inquiries',
            'Leases', 'Reports', 'User Accounts', 'Settings &amp; Backup', 'Audit Log'];
        $expected = [
            'Owner' => $order,
            'Manager' => array_slice($order, 0, 9),
            'Caretaker' => ['Dashboard', 'Concerns', 'Tenants', 'Rooms', 'Inquiries'],
        ];

        foreach ($expected as $role => $labels) {
            $html = $this->actingAs(User::factory()->role($role)->create())->get('/')->getContent();
            preg_match('#<nav class="nav">(.*?)</nav>#s', $html, $nav);
            preg_match_all('#</svg>([^<]+)</a>#', $nav[1], $items);
            $this->assertSame($labels, array_map('trim', $items[1]), "{$role} menu order");
        }
    }

    public function test_sidebar_groups_for_each_role(): void
    {
        $ownerHtml = $this->actingAs(User::factory()->role('Owner')->create())->get('/')->getContent();
        $this->assertStringContainsString('<div class="nav-heading">Navigation</div>', $ownerHtml);
        $this->assertStringContainsString('<div class="nav-heading">Operation</div>', $ownerHtml);
        $this->assertStringContainsString('<div class="nav-heading">Setting</div>', $ownerHtml);

        $managerHtml = $this->actingAs(User::factory()->role('Manager')->create())->get('/')->getContent();
        $this->assertStringContainsString('<div class="nav-heading">Navigation</div>', $managerHtml);
        $this->assertStringContainsString('<div class="nav-heading">Operation</div>', $managerHtml);
        $this->assertStringNotContainsString('<div class="nav-heading">Setting</div>', $managerHtml);

        $caretakerHtml = $this->actingAs(User::factory()->role('Caretaker')->create())->get('/')->getContent();
        $this->assertStringContainsString('<div class="nav-heading">Navigation</div>', $caretakerHtml);
        $this->assertStringContainsString('<div class="nav-heading">Operation</div>', $caretakerHtml);
        $this->assertStringNotContainsString('<div class="nav-heading">Setting</div>', $caretakerHtml);
    }

    public function test_app_runs_in_manila_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
    }
}
