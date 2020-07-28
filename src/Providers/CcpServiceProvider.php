<?php

namespace Techquity\CloudCommercePro\Providers;

use Aero\Common\Providers\ModuleServiceProvider;

class CcpServiceProvider extends ModuleServiceProvider
{

    /**
     * Bootstrap any module services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Register any module services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'aero.cloudcommercepro');

        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('aero/cloudcommercepro.php'),
        ], 'config');

        if (config('aero.cloudcommercepro.enabled') === true) {
            $this->app->register(CcpCoreServiceProvider::class);
        }
    }
}
