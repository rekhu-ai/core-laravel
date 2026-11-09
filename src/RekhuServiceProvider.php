<?php

declare(strict_types=1);

namespace Rekhu\Core;

use Illuminate\Support\Facades\Log;
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
        } elseif (collect((array) config('rekhu.limits'))->flatten()->filter(fn ($v) => $v !== null)->isNotEmpty()) {
            Log::warning('Rekhu: limits are set in config/rekhu.php, but they are counted in the usage table and REKHU_LEDGER is off, so they do not apply.');
        }
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/rekhu.php' => config_path('rekhu.php')], 'rekhu-config');
            $this->commands([Console\CoreCommand::class]);
        }
    }
}
