<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

class UnifiedValidationAndInquiryRefactorTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->manager = User::factory()->role('Manager')->create();
        $this->settings($this->owner);
    }

    public function test_tbl_inquiry_has_first_middle_last_name_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('tbl_inquiry', 'first_name'));
        $this->assertTrue(Schema::hasColumn('tbl_inquiry', 'middle_name'));
        $this->assertTrue(Schema::hasColumn('tbl_inquiry', 'last_name'));
        $this->assertTrue(Schema::hasColumn('tbl_inquiry', 'prospect_name'));
    }

    public function test_inquiry_prospect_name_accessor_computes_full_name(): void
    {
        $inquiry1 = new Inquiry([
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
        ]);
        $this->assertSame('Juan Santos Dela Cruz', $inquiry1->prospect_name);

        $inquiry2 = new Inquiry([
            'first_name' => 'Maria',
            'middle_name' => null,
            'last_name' => 'Clara',
        ]);
        $this->assertSame('Maria Clara', $inquiry2->prospect_name);
    }

    public function test_inquiries_modal_and_form_render_split_name_fields(): void
    {
        $response = $this->actingAs($this->manager)->get(route('inquiries.index'));
        $response->assertOk()
            ->assertSee('name="first_name"', false)
            ->assertSee('name="middle_name"', false)
            ->assertSee('name="last_name"', false);
    }

    public function test_logging_inquiry_persists_split_name_and_computed_prospect_name(): void
    {
        $response = $this->actingAs($this->manager)->post(route('inquiries.store'), [
            'first_name' => 'Andres',
            'middle_name' => 'Castro',
            'last_name' => 'Bonifacio',
            'contact_no' => '09171234567',
            'preferred_type' => 'Aircon',
            'no_of_occupants' => 1,
            'channel' => 'Walk-in',
            'inquiry_date' => today()->toDateString(),
        ]);

        $response->assertRedirect(route('inquiries.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('tbl_inquiry', [
            'first_name' => 'Andres',
            'middle_name' => 'Castro',
            'last_name' => 'Bonifacio',
            'prospect_name' => 'Andres Castro Bonifacio',
            'contact_no' => '09171234567',
        ]);
    }

    public function test_seamless_moa_conversion_prefills_name_and_contact_directly(): void
    {
        $room = $this->makeRoom('101', 'Basic');
        $inquiry = Inquiry::create([
            'first_name' => 'Gabriela',
            'middle_name' => 'Cariño',
            'last_name' => 'Silang',
            'contact_no' => '09181234567',
            'preferred_type' => 'Basic',
            'no_of_occupants' => 1,
            'channel' => 'Walk-in',
            'status' => 'Waiting',
            'inquiry_date' => today(),
            'user_id' => $this->manager->user_id,
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('leases.create', ['inquiry_id' => $inquiry->inquiry_id]));

        $response->assertOk()
            ->assertSee('Gabriela')
            ->assertSee('Cariño')
            ->assertSee('Silang')
            ->assertSee('09181234567')
            ->assertSee('Inquiry-to-Lease Conversion');
    }

    public function test_universal_realtime_validation_script_is_present_and_loaded(): void
    {
        $this->assertFileExists(public_path('js/realtime-validation.js'));
        $js = file_get_contents(public_path('js/realtime-validation.js'));

        $this->assertStringContainsString('09\\d{9}|\\+639\\d{9}', $js);
        $this->assertStringContainsString('^[A-Za-z\\s\\.\\-ñÑ]*$', $js);
        $this->assertStringContainsString('Current reading cannot be lower than the previous reading', $js);
        $this->assertStringContainsString('Please enter a valid PH mobile number', $js);
        $this->assertStringContainsString('Names can only contain letters, spaces, hyphens, and periods', $js);
        $this->assertStringContainsString('Please enter a valid positive amount', $js);
        $this->assertStringContainsString('insertAdjacentElement', $js);
        $this->assertStringContainsString('blur', $js);

        $response = $this->actingAs($this->manager)->get(route('inquiries.index'));
        $response->assertOk()
            ->assertSee('realtime-validation.js');
    }

    public function test_universal_css_removes_spin_buttons_from_numeric_inputs(): void
    {
        $this->assertFileExists(public_path('css/app.css'));
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('input[type="number"]', $css);
        $this->assertStringContainsString('appearance: textfield !important', $css);
        $this->assertStringContainsString('-webkit-outer-spin-button', $css);
        $this->assertStringContainsString('-webkit-inner-spin-button', $css);
    }
}
