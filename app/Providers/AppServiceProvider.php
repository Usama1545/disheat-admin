<?php

namespace App\Providers;


use App\Services\CurrencyService;
use App\Services\SettingService;
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
    }
}
