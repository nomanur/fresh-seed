<?php

namespace Nomanurrahman\FreshSeed;

use Illuminate\Support\ServiceProvider;

class FreshSeedServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'fresh-seed');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'fresh-seed');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('fresh-seed.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/fresh-seed'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/fresh-seed'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/fresh-seed'),
            ], 'lang');*/

            // Registering package commands.
            $this->commands([
                Commands\FreshSeedCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'fresh-seed');

        // Register the main class to use with the facade
        $this->app->singleton('fresh-seed', function () {
            return new FreshSeed;
        });
    }
}
