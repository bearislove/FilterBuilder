<?php

namespace AnhTT\FilterBuilder;

use Illuminate\Support\ServiceProvider;

class FilterBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filter-builder.php',
            'filter-builder'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/filter-builder.php' => config_path('filter-builder.php'),
            ], 'filter-builder-config');
        }
    }
}
