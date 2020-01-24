<?php

namespace Techquity\CloudCommercePro\Providers;

use Illuminate\Console\Scheduling\Schedule;

use Aero\Common\Providers\ModuleServiceProvider;
use Aero\Cart\Events\OrderSuccessful;
use Aero\Catalog\Events\ProductCreated;
use Aero\Catalog\Events\ProductUpdated;


use Techquity\CloudCommercePro\Http\Controllers\CCPController;


class CCPCoreServiceProvider extends ModuleServiceProvider
{
    /**
     * The event handler mappings for the module.
     *
     * @var array
     */
    protected $listen = [
        //
        //OrderSuccessful::class => [
        //    OrderSuccessfulListener::class,
        //],
        //ProductCreated::class => [
        //    ProductCreatedListener::class
        //],
        //ProductUpdated::class => [
        //    ProductUpdatedListener::class
        //]
    ];

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

            \Route::group(['middleware' => ['web', 'admin'], 'prefix' => 'ccp'], function() {
                \Route::get('/categories', [CCPController::class, 'categories'])->name('admin.ccp.categories');
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
        //$this->commands([
        //    RemoveDuplicate::class,
        //    UpdateStock::class,
        //    CreateOrder::class,
        //    UpdateOrder::class,
        //    CreateProduct::class,
        //    UpdateProduct::class,
        //    SyncProduct::class
        //]);
    }
}
