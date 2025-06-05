<?php

namespace Limetis\FilamentActivityLogPlugin\Providers;

use Illuminate\Support\ServiceProvider;

class FilamentActivityLogPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $configPath = __DIR__ . '/../../config/filament-activitylog.php';

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'filament-activitylog');
        }
    }

    public function boot(): void
    {
        $viewsPath = __DIR__ . '/../../resources/views';
        $langPath = __DIR__ . '/../../resources/lang';
        $configPath = __DIR__ . '/../../config/filament-activitylog.php';

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'activitylog');
        }

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'activitylog');
        }

        if (file_exists($configPath)) {
            $this->publishes([
                $configPath => config_path('filament-activitylog.php'),
            ], 'filament-activity-log-config');
        }
    }
}