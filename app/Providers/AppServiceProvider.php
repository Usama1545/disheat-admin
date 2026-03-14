<?php

namespace App\Providers;


use App\Models\SystemUpdate;
use App\Services\CurrencyService;
use App\Services\SettingService;
use App\Models\WalletTransaction;
use App\Observers\WalletTransactionObserver;
use App\Models\SellerStatement;
use App\Observers\SellerStatementObserver;
use App\Models\DeliveryBoyAssignment;
use App\Observers\DeliveryBoyAssignmentObserver;
use App\Models\OrderItemReturn;
use App\Observers\OrderItemReturnObserver;
use App\Models\Store;
use App\Models\Order;
use App\Observers\StoreObserver;
use App\Observers\OrderObserver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrencyService::class, function ($app) {
            return new CurrencyService($app->make(SettingService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $systemSettings = [];
        Schema::defaultStringLength(191);
        try {
            if (Schema::hasTable('settings')) {
                $settingService = app(SettingService::class);
                $resource = $settingService->getSettingByVariable('system');
                $systemSettings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];
            }
        } catch (\Exception $e) {
            Log::warning($e->getMessage());
        }
        $panel = 'admin';
        if (request()->is('seller/*') || request()->routeIs('seller.*')) {
            $panel = 'seller';
        }
        $menuSeller = config("menu.seller", []);
        $menuAdmin = config("menu.admin", []);
        $data = ['systemSettings' => $systemSettings, 'menuSeller' => $menuSeller, 'menuAdmin' => $menuAdmin, 'panel' => $panel];
        // check if the user is authenticated
        if (Auth::check()) {
            $data['user'] = Auth::user(); // Get the authenticated user
        }

        view()->share($data);
        View::composer('*', function ($view) {
            // check if the user is authenticated
            if (Auth::check()) {
                $user = Auth::user(); // Get the authenticated user
                $view->with('user', $user); // Share user details with the view
            }
        });
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Register model observers
        try {
            WalletTransaction::observe(WalletTransactionObserver::class);
        } catch (\Throwable $e) {
            // Avoid breaking boot if migrations are not yet run
            Log::warning('Failed to register WalletTransactionObserver: ' . $e->getMessage());
        }

        // Register SellerStatement observer
        try {
            SellerStatement::observe(SellerStatementObserver::class);
        } catch (\Throwable $e) {
            Log::warning('Failed to register SellerStatementObserver: ' . $e->getMessage());
        }

        // Register DeliveryBoyAssignment observer
        try {
            DeliveryBoyAssignment::observe(DeliveryBoyAssignmentObserver::class);
        } catch (\Throwable $e) {
            Log::warning('Failed to register DeliveryBoyAssignmentObserver: ' . $e->getMessage());
        }

        // Register OrderItemReturn observer
        try {
            OrderItemReturn::observe(OrderItemReturnObserver::class);
        } catch (\Throwable $e) {
            Log::warning('Failed to register OrderItemReturnObserver: ' . $e->getMessage());
        }

        // Register Store observer
        try {
            Store::observe(StoreObserver::class);
        } catch (\Throwable $e) {
            Log::warning('Failed to register StoreObserver: ' . $e->getMessage());
        }

        // Register Order observer
        try {
            Order::observe(OrderObserver::class);
        } catch (\Throwable $e) {
            Log::warning('Failed to register OrderObserver: ' . $e->getMessage());
        }
    }
}
