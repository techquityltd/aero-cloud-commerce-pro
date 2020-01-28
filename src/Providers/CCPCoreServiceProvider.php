<?php

namespace Techquity\CloudCommercePro\Providers;

use Aero\Common\Providers\ModuleServiceProvider;

use Techquity\CloudCommercePro\Http\Controllers\CcpController;
use Techquity\CloudCommercePro\Console\Commands\CreateUser;

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

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->app->booted(static function () {

            \Route::middleware(['auth:api'])->group(function () {
                \Route::match(['get'], 'ccp/categories', [CcpController::class, 'categories'])->name('ccp.categories');
                \Route::match(['get'], 'ccp/listings/{sku?}', [CcpController::class, 'listings'])->name('ccp.listings');
                \Route::match(['get'], 'ccp/orders/{orderReference?}', [CcpController::class, 'orders'])->name('ccp.orders');
                \Route::match(['get'], 'ccp/csv', [CcpController::class, 'csv'])->name('ccp.csv');
                \Route::match(['post'], 'ccp/stock', [CcpController::class, 'stock'])->name('ccp.stock');
                \Route::match(['post'], 'ccp/dispatch', [CcpController::class, 'dispatch'])->name('ccp.dispatch');

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
