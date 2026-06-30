<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Modules\Segmentation\Constraints\SegmentConstraint::class,
            \App\Modules\Segmentation\Constraints\ReelSingleLineConstraint::class
        );

        $this->app->bind(
            \App\Modules\Dataset\Providers\DatasetProvider::class,
            \App\Modules\Dataset\Providers\QuranComDatasetProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
