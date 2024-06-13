<?php

namespace Nicelizhi\OneBuy\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Shop\Http\Middleware\AuthenticateCustomer;
use Webkul\Shop\Http\Middleware\Currency;
use Webkul\Shop\Http\Middleware\Locale;
use Webkul\Shop\Http\Middleware\Theme;

class OneBuyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        
        include __DIR__ . '/../Http/routes.php';

        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'onebuy');

        /* aliases */
        $router->aliasMiddleware('currency', Currency::class);
        $router->aliasMiddleware('locale', Locale::class);
        $router->aliasMiddleware('customer', AuthenticateCustomer::class);
        $router->aliasMiddleware('theme', Theme::class);

        /* View Composers */
        //$this->composeView();

        /* Paginator */
        Paginator::defaultView('shop::partials.pagination');
        Paginator::defaultSimpleView('shop::partials.pagination');

        Blade::anonymousComponentPath(__DIR__ . '/../Resources/views/components', 'onebuy');

        /*
        $this->app->register(EventServiceProvider::class);
        */
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        /*
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/paymentmethods.php', 'payment_methods'
        );
        */
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/onebuy.php', 'onebuy'
        );
    }
}
