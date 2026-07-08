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

        $this->app->bind(
            \App\Modules\Rendering\Contracts\TranslationRepositoryInterface::class,
            \App\Modules\Rendering\Repositories\TranslationRepository::class
        );

        $this->app->bind(
            \App\Modules\Rendering\Contracts\TranslationProviderInterface::class,
            \App\Modules\Rendering\Providers\SahihInternationalTranslationProvider::class
        );

        // Dataset Validation & Normalization Pipeline
        $this->app->bind(
            \App\Modules\Dataset\Validation\DatasetValidatorInterface::class,
            \App\Modules\Dataset\Validation\GlyphDatasetValidator::class
        );

        $this->app->singleton(\App\Modules\Dataset\Validation\GlyphCorrectionRegistry::class, function ($app) {
            $registry = new \App\Modules\Dataset\Validation\GlyphCorrectionRegistry();
            $registry->register($app->make(\App\Modules\Dataset\Validation\Rules\PageWrapCorrectionRule::class));
            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
