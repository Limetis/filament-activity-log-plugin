<?php

namespace Limetis\FilamentActivityLogPlugin\Providers;

use Illuminate\Support\ServiceProvider;

class FilamentActivityLogPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/filament-activitylog.php', 'filament-activitylog'
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'activitylog');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'activitylog');
        $this->publishes([
            __DIR__.'/../../config/filament-activitylog.php' => config_path('filament-activitylog.php'),
        ], 'filament-activity-log-config');
    }
}