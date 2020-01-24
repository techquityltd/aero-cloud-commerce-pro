<?php

namespace Techquity\CloudCommercePro\Providers;

use Illuminate\Console\Scheduling\Schedule;

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

            $schedule = app(Schedule::class);

            $log = storage_path('logs/scheduler.log');

            \Route::middleware(['api'])->group(function () {
                \Route::match(['get', 'post'], 'ccp/categories', [CcpController::class, 'categories'])->name('ccp.categories');
                \Route::match(['get', 'post'], 'ccp/listings', [CcpController::class, 'listings'])->name('ccp.listings');
                \Route::match(['get', 'post'], 'ccp/orders', [CcpController::class, 'orders'])->name('ccp.orders');
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
        
    }
}
