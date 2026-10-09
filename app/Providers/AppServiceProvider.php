<?php

namespace App\Providers;

use App\Models\Inquiry;
use App\Models\Room;
use App\Models\User;
use App\Policies\InquiryPolicy;
use App\Policies\RoomPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Money display used on every screen:  @peso($bill->total_amount)  ->  ₱5,620.00
        Blade::directive('peso', fn (string $expr) => "<?php echo '₱'.number_format((float) ({$expr}), 2); ?>");

        // Only the Owner adds or edits rooms and rents (used for buttons in views; routes also use role:Owner).
        Gate::define('manage-rooms', fn (User $user) => $user->hasRole('Owner'));

        // Owner and Manager encode and view MOAs, end leases. Never the Caretaker (decision 10).
        Gate::define('manage-leases', function (User $user) {
            return in_array($user->role, ['Owner', 'Manager']);
        });

        // Owner and Manager manage billing, meter readings, and record payments. Never the Caretaker.
        Gate::define('manage-billing', function (User $user) {
            return in_array($user->role, ['Owner', 'Manager']);
        });

        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(Inquiry::class, InquiryPolicy::class);
        Gate::policy(\App\Models\Lease::class, \App\Policies\LeasePolicy::class);

        Paginator::defaultView('partials.pagination');
    }
}
