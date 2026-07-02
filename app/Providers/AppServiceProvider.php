<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\Delivery\DeliveryManager;
use App\Support\Money;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CartService::class, fn () => new CartService());
        $this->app->singleton(DeliveryManager::class, fn () => new DeliveryManager());
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        // Always render prices as an isolated left-to-right run so the currency
        // and digits keep their order even inside an Arabic (RTL) layout.
        Blade::directive('money', fn ($expr) =>
            "<?php echo '<span dir=\"ltr\" class=\"inline-block\" style=\"unicode-bidi:isolate\">'.\App\Support\Money::format($expr).'</span>'; ?>");

        // Share store-wide data with every storefront view. Guarded so the app
        // still boots before migrations have run.
        View::composer('*', function ($view) {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $view->with('storeName', \App\Models\Setting::get('store_name', 'Saidi Papetrie'));
        });
    }
}
