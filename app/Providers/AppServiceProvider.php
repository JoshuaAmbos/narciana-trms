<?php

namespace App\Providers;

use App\Models\User;
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
    }
}
