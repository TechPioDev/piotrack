<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The platform's own dependency checks — shared by the /health endpoint
 * (OBS-003) and the admin alerter (OBS-004) so both always agree on what
 * "healthy" means.
 */
class HealthCheckService
{
    /**
     * @return array<string, bool>
     */
    public function checks(): array
    {
        return [
            'database' => $this->passes(fn () => DB::select('select 1') !== []),
            'cache' => $this->passes(function (): bool {
                Cache::put('health-check-probe', 'ok', 10);

                return Cache::get('health-check-probe') === 'ok';
            }),
            'queue' => $this->passes(function (): bool {
                // Reaching both tables (throws if unavailable) proves the queue
                // backend is wired; passes() converts any failure to false.
                DB::table('jobs')->count();
                DB::table('failed_jobs')->count();

                return true;
            }),
            'storage' => $this->passes(function (): bool {
                $probe = 'health/'.Str::random(8).'.txt';
                Storage::disk('local')->put($probe, 'ok');
                $ok = Storage::disk('local')->get($probe) === 'ok';
                Storage::disk('local')->delete($probe);

                return $ok;
            }),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function metrics(): array
    {
        return [
            'queue_pending' => $this->safeCount('jobs'),
            'queue_failed' => $this->safeCount('failed_jobs'),
        ];
    }

    /**
     * @param  callable(): bool  $probe
     */
    private function passes(callable $probe): bool
    {
        try {
            return $probe();
        } catch (Throwable) {
            return false;
        }
    }

    private function safeCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (Throwable) {
            return -1;
        }
    }
}
