<?php

declare(strict_types=1);

namespace Rekhu\Core;

use Illuminate\Support\ServiceProvider;

class RekhuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rekhu.php', 'rekhu');
        $this->app->singleton(Client::class);
    }

    public function boot(): void
    {
        if (config('rekhu.ledger.enabled')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/rekhu.php' => config_path('rekhu.php')], 'rekhu-config');
            $this->commands([Console\CoreCommand::class]);
        }
    }
}
