<?php

declare(strict_types=1);

namespace Rekhu\Core\Tests;

use Illuminate\Auth\GenericUser;
use Orchestra\Testbench\TestCase as Orchestra;
use Rekhu\Core\RekhuServiceProvider;

abstract class TestCase extends Orchestra
{
    protected const KEY = 'agk_acme_assistant_testsecret';

    protected function getPackageProviders($app): array
    {
        return [RekhuServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('rekhu.key', self::KEY);
        $app['config']->set('rekhu.platform', 'https://rekhu.test');
        $app['config']->set('rekhu.knowers', ['machines']);
        $app['config']->set('app.name', 'Print Portal');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function user(int $id = 7, ?string $team = 'shop-42'): GenericUser
    {
        return new GenericUser(['id' => $id, 'name' => 'Ada', 'team' => $team]);
    }

    /** What Rekhu answers for an action: the knower's result with its ref and meta. */
    protected function answer(array $result = [], int $modelCalls = 0, float $cost = 0.0): array
    {
        return ['knower' => 'machines', 'action' => 'x', 'ref' => 'k_'.str_repeat('a', 20)] + $result + ['meta' => ['model_calls' => $modelCalls, 'cost_usd' => $cost, 'ms' => 3]];
    }
}
