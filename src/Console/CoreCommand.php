<?php

declare(strict_types=1);

namespace Rekhu\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Rekhu\Core\Rekhu;

/**
 * php artisan rekhu:core                              how the app is connected, and its knowers
 * php artisan rekhu:core --install                    connects the app: key, deployment, tenancy, languages
 * php artisan rekhu:core --install=machines           adds a knower, after checking Rekhu has it for this key
 * php artisan rekhu:core --usage [--month=] [--tenant=] [--csv=]   the usage per tenant in a month
 */
class CoreCommand extends Command
{
    protected $signature = 'rekhu:core
        {--install= : connect the app, or with a name add that knower (--install=machines)}
        {--usage : the usage per tenant in a month}
        {--month= : with --usage: YYYY-MM, this month by default}
        {--tenant= : with --usage: one tenant}
        {--csv= : with --usage: also write the rows to this file}
        {--key= : the app key from the Rekhu account}
        {--deployment= : cloud (default) or private}
        {--platform= : the address of Rekhu: inside your network for private, or a test platform}
        {--tenancy= : single or multi}
        {--locales= : the languages your users read, for example en,nl,ar}';

    protected $description = 'Rekhu Core: connect the app, add knowers, see the usage';

    public function handle(): int
    {
        if ($this->option('usage')) {
            return $this->usage();
        }
        if ($this->input->hasParameterOption('--install')) {
            return $this->option('install') ? $this->addKnower((string) $this->option('install')) : $this->connect();
        }

        return $this->status();
    }

    private function status(): int
    {
        $key = Rekhu::key();
        $this->table(['', ''], [
            ['Key', $key ? $key['customer'].' · '.$key['agent'] : 'missing (php artisan rekhu:core --install)'],
            ['Deployment', (string) config('rekhu.deployment').' · '.config('rekhu.platform')],
            ['Tenancy', (string) config('rekhu.tenancy')],
            ['Languages', implode(', ', (array) config('rekhu.locales'))],
            ['Knowers', implode(', ', (array) config('rekhu.knowers')) ?: 'none yet (php artisan rekhu:core --install=machines)'],
            ['Usage table', config('rekhu.ledger.enabled') ? (string) config('rekhu.ledger.table') : 'off'],
        ]);

        return self::SUCCESS;
    }

    private function connect(): int
    {
        $key = trim((string) ($this->option('key') ?: config('rekhu.key')));
        if ($key === '') {
            $this->line('  No Rekhu account yet? Create one at https://rekhu.com/register?ref=core-laravel: it starts with $10 credit, no card needed.');
            $key = trim((string) $this->secret('The app key (Rekhu account → Projects → your app → Connection → Add key)'));
        }
        if (! preg_match('/^agk_([a-z0-9-]+)_([a-z0-9-]+)_[A-Za-z0-9_-]+$/', $key, $m)) {
            $this->error('That is not a Rekhu app key (it starts with agk_).');

            return self::FAILURE;
        }
        $mode = (string) ($this->option('deployment') ?: config('rekhu.deployment', 'cloud'));
        if (! in_array($mode, ['cloud', 'private'], true)) {
            $this->error('The mode is cloud or private.');

            return self::FAILURE;
        }
        $platform = rtrim((string) ($this->option('platform') ?: config('rekhu.platform')), '/');
        if ($mode === 'private' && (! $this->option('platform') && $platform === 'https://api.rekhu.ai')) {
            $this->error('Private mode needs --platform: the address of Rekhu inside your network.');

            return self::FAILURE;
        }
        $tenancy = (string) ($this->option('tenancy') ?: config('rekhu.tenancy', 'single'));
        if (! in_array($tenancy, ['single', 'multi'], true)) {
            $this->error('Tenancy is single or multi.');

            return self::FAILURE;
        }
        $response = Http::baseUrl($platform)->timeout(15)->acceptJson()->withHeaders(['x-api-key' => $key, 'x-agent' => $m[2]])->get('/v1/knowers');
        if (! $response->successful()) {
            $this->error('Rekhu refused the key ('.$response->status().'): '.($response->json('error.message') ?? 'no reason given'));

            return self::FAILURE;
        }

        $this->writeEnv('REKHU_APP_KEY', $key);
        $this->writeEnv('REKHU_DEPLOYMENT', $mode);
        $this->writeEnv('REKHU_TENANCY', $tenancy);
        if ($this->option('platform')) {
            $this->writeEnv('REKHU_PLATFORM', $platform);
        }
        if ($this->option('locales')) {
            $this->writeEnv('REKHU_LOCALES', implode(',', array_filter(array_map('trim', explode(',', (string) $this->option('locales'))))));
        }
        $this->callSilently('vendor:publish', ['--tag' => 'rekhu-config']);

        $this->info("Connected to Rekhu as {$m[1]} / {$m[2]} ({$mode}, {$tenancy} tenant).");
        $this->line('  Knowers you can add:');
        foreach ((array) $response->json('knowers') as $k) {
            $this->line("    {$k['knower']}  {$k['name']}: {$k['description']}");
        }
        $this->line('Next:');
        $this->line('  1. php artisan migrate   (the usage table, rekhu_usage)');
        $this->line('  2. php artisan rekhu:core --install=machines');
        if ($tenancy === 'multi') {
            $this->line('  3. In a service provider: Rekhu::tenantUsing(fn ($user) => $user->team);');
        }

        return self::SUCCESS;
    }

    private function addKnower(string $knower): int
    {
        $key = Rekhu::key();
        if (! $key) {
            $this->error('Connect the app first: php artisan rekhu:core --install');

            return self::FAILURE;
        }
        $response = Http::baseUrl(Rekhu::platform())->timeout(15)->acceptJson()->withHeaders(['x-api-key' => $key['key'], 'x-agent' => $key['agent']])->get('/v1/knowers');
        if (! $response->successful()) {
            $this->error('Rekhu could not be asked ('.$response->status().'): '.($response->json('error.message') ?? 'no reason given'));

            return self::FAILURE;
        }
        $found = collect((array) $response->json('knowers'))->firstWhere('knower', $knower);
        if (! $found) {
            $this->error("Rekhu has no knower {$knower}. It has: ".collect((array) $response->json('knowers'))->pluck('knower')->implode(', '));

            return self::FAILURE;
        }
        $installed = array_values(array_unique([...(array) config('rekhu.knowers', []), $knower]));
        $this->writeEnv('REKHU_KNOWERS', implode(',', $installed));
        config(['rekhu.knowers' => $installed]);

        $this->info("{$found['name']} is installed.");
        foreach ((array) $found['actions'] as $a) {
            $this->line("    {$a['action']}: {$a['description']}");
        }
        $this->line('  Use it: Rekhu::'.$knower.'()->identify(…). Your limits a day: limits.'.$knower.' in config/rekhu.php. Your own price per call: Rekhu::priceUsing().');

        return self::SUCCESS;
    }

    private function writeEnv(string $name, string $value): void
    {
        $path = base_path('.env');
        $env = is_file($path) ? (string) file_get_contents($path) : '';
        $line = $name.'='.$value;
        $env = preg_match('/^'.preg_quote($name, '/').'=.*$/m', $env)
            ? preg_replace('/^'.preg_quote($name, '/').'=.*$/m', $line, $env)
            : rtrim($env)."\n".$line."\n";
        file_put_contents($path, $env);
    }

    private function usage(): int
    {
        $month = (string) ($this->option('month') ?: now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('The month is YYYY-MM.');

            return self::FAILURE;
        }
        $from = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $rows = DB::connection(config('rekhu.ledger.connection'))->table((string) config('rekhu.ledger.table', 'rekhu_usage'))
            ->where('created_at', '>=', $from)->where('created_at', '<', $from->copy()->addMonth())
            ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
            ->groupBy('tenant_id', 'tenant_name', 'knower', 'action')->orderBy('tenant_id')->orderBy('knower')->orderBy('action')
            ->selectRaw('tenant_id, tenant_name, knower, action, count(*) as calls, sum(model_calls) as model_calls, sum(cost_usd) as cost_usd, sum(price) as price')
            ->get()
            ->map(fn ($r) => [$r->tenant_id, $r->tenant_name, $r->knower, $r->action, (int) $r->calls, (int) $r->model_calls, number_format((float) $r->cost_usd, 4, '.', ''), $r->price === null ? '' : number_format((float) $r->price, 2, '.', '')])
            ->all();
        $head = ['tenant', 'name', 'knower', 'action', 'calls', 'model calls', 'Rekhu cost (USD)', 'your price'];
        if (! $rows) {
            $this->line("No usage in {$month}.");

            return self::SUCCESS;
        }
        $this->table($head, $rows);
        if ($path = $this->option('csv')) {
            $file = fopen(str_starts_with($path, '/') ? $path : base_path($path), 'w');
            fputcsv($file, $head, ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($file, $row, ',', '"', '\\');
            }
            fclose($file);
            $this->info("Written to {$path}.");
        }

        return self::SUCCESS;
    }
}
