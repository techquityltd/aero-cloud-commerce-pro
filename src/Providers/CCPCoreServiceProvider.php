<?php

namespace Techquity\CloudCommercePro\Providers;

use Aero\Common\Providers\ModuleServiceProvider;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

use Techquity\CloudCommercePro\Console\Commands\ImportProducts;
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

        $this->app->booted(function () {

            $schedule = $this->app->make(Schedule::class);
            $schedule->command('ccp:import:products')->everyMinute();

            \Route::middleware(['auth:api'])->group(function () {
                \Route::match(['get'], 'ccp/categories', [CcpController::class, 'categories'])->name('ccp.categories');
                \Route::match(['get'], 'ccp/listings/{sku?}', [CcpController::class, 'listings'])->name('ccp.listings');
                \Route::match(['get'], 'ccp/orders/{orderReference?}', [CcpController::class, 'orders'])->name('ccp.orders');
                \Route::match(['get'], 'ccp/csv', [CcpController::class, 'csv'])->name('ccp.csv');
                \Route::match(['post'], 'ccp/stock', [CcpController::class, 'stock'])->name('ccp.stock');
                \Route::match(['post'], 'ccp/dispatch', [CcpController::class, 'dispatch'])->name('ccp.dispatch');
                \Route::match(['post', 'put', 'delete'], 'ccp/product', [CcpController::class, 'product'])->name('ccp.product');

            });

            Builder::macro('jsonPaginate', function (int $maxResults = null, int $defaultSize = null) {
                $maxResults = $maxResults ?? 100;
                $defaultSize = $defaultSize ?? 100;
                $numberParameter = 'number';
                $sizeParameter = 'size';
                $paginationParameter = 'page';

                $size = (int) request()->input($paginationParameter.'.'.$sizeParameter, $defaultSize);

                $size = $size > $maxResults ? $maxResults : $size;

                $paginator = $this
                    ->paginate($size, ['*'], $paginationParameter.'.'.$numberParameter)
                    ->setPageName($paginationParameter.'['.$numberParameter.']')
                    ->appends(Arr::except(request()->input(), $paginationParameter.'.'.$numberParameter));

                //if (! is_null(config('json-api-paginate.base_url'))) {
                //    $paginator->setPath(config('json-api-paginate.base_url'));
                //}

                return $paginator;
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
            CreateUser::class,
            ImportProducts::class
        ]);
    }
}
