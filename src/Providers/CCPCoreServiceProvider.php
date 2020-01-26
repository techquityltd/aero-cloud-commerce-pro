<?php

namespace Techquity\CloudCommercePro\Providers;

use Aero\Common\Providers\ModuleServiceProvider;

use Techquity\CloudCommercePro\Http\Controllers\CcpController;

class CcpCoreServiceProvider extends ModuleServiceProvider
{
    /**
     * The event handler mappings for the module.
     *
     * @var array
     */
    protected $listen = [];

    /**
     * Bootstrap any module services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        $this->app->booted(static function () {

            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

            \Route::middleware(['auth:api'])->group(function () {
                \Route::match(['get'], 'ccp/categories', [CcpController::class, 'categories'])->name('ccp.categories');
                \Route::match(['get'], 'ccp/listings', [CcpController::class, 'listings'])->name('ccp.listings');
                \Route::match(['get'], 'ccp/orders', [CcpController::class, 'orders'])->name('ccp.orders');
                \Route::match(['post'], 'ccp/stock', [CcpController::class, 'stock'])->name('ccp.stock');
                
            });

        });

    }

    /**
     * Register any module services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            CreateUser::class
        ]);
    }
}
