<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
    }
}
