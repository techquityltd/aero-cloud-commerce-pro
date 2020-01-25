<?php

namespace Techquity\CloudCommercePro\Providers;

use Aero\Catalog\Models\Category;
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
                \Route::match(['get'], 'ccp/categories', [CcpController::class, 'categories'])->name('ccp.categories');
                \Route::match(['get'], 'ccp/listings', [CcpController::class, 'listings'])->name('ccp.listings');
                \Route::match(['get'], 'ccp/orders', [CcpController::class, 'orders'])->name('ccp.orders');
                \Route::match(['post'], 'ccp/stock', [CcpController::class, 'stock'])->name('ccp.stock');
                
            });


            Category::macro('getTranslatedNestedList', function($column, $key = null, $seperator = ' ')
            {
                $instance = new static;

                $key = $key ?: $instance->getKeyName();
                $depthColumn = $instance->getDepthColumnName();

                $nodes = $instance->newQuery()->get()->toArray();

                return array_combine(array_map(function ($node) use ($key) {
                    return $node[$key];
                }, $nodes), array_map(function ($node) use ($seperator, $depthColumn, $column) {
                    return str_repeat($seperator, $node[$depthColumn]) . $node[$column][request()->store()->language];
                }, $nodes));
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
