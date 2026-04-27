<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill for the TIMESTAMPTZ timezone-shift bug fixed in
 * `App\Database\PostgresGrammar`. Subtracts a TZ offset (default 8 hours,
 * Asia/Manila vs UTC) from rows written under the old PG-session=UTC
 * configuration where Eloquent serialized Manila Carbons without an offset
 * — PG stamped them as UTC, leaving every TIMESTAMPTZ value 8h in the
 * future.
 *
 * Idempotent guard: only touches rows whose `created_at` is at or before
 * the cutoff captured when the command starts, so any inserts that arrive
 * mid-run are NOT double-shifted.
 *
 * Defaults to dry-run. Pass --force to apply.
 */
class BackfillTimezoneShift extends Command
{
    protected $signature = 'fras:backfill-timezone
                            {--force : Actually apply the update (otherwise dry-run)}
                            {--hours=8 : Hours to subtract from each TIMESTAMPTZ value}';

    protected $description = 'Backfill the TIMESTAMPTZ rows that were stored 8h in the future under the old PG-session=UTC config (FRAS Events Just-now bug).';

    /**
     * Tables → list of TIMESTAMPTZ columns that need shifting. Confined to the
     * 13 columns enumerated against the live schema. If a future migration
     * adds another TIMESTAMPTZ column it must be added here.
     */
    private const TARGETS = [
        'camera_enrollments' => ['enrolled_at'],
        'cameras' => ['decommissioned_at', 'last_seen_at'],
        'fras_access_log' => ['accessed_at'],
        'fras_legal_signoffs' => ['signed_at'],
        'fras_purge_runs' => ['finished_at', 'started_at'],
        'personnel' => ['decommissioned_at', 'expires_at'],
        'recognition_events' => ['acknowledged_at', 'captured_at', 'dismissed_at', 'received_at'],
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $hours = (int) $this->option('hours');

        if ($hours <= 0) {
            $this->error('--hours must be a positive integer.');

            return self::FAILURE;
        }

        // Idempotency guard: rows inserted after this instant are NOT touched.
        $cutoff = Carbon::now();
        $this->line('Cutoff (rows with created_at <= this are eligible): '.$cutoff->toIso8601String());
        $this->line('Shift: -'.$hours.' hours');
        $this->line('Mode:  '.($force ? 'APPLY' : 'DRY-RUN'));
        $this->newLine();

        $totalRows = 0;
        $headers = ['Table', 'Column', 'Eligible rows'];
        $report = [];

        foreach (self::TARGETS as $table => $columns) {
            if (! $this->tableHasCreatedAt($table)) {
                $this->warn("Skipping {$table}: no created_at column (cannot guard against re-runs).");

                continue;
            }

            $eligible = DB::table($table)
                ->where('created_at', '<=', $cutoff)
                ->count();

            if ($eligible === 0) {
                $report[] = [$table, '— (no rows)', 0];

                continue;
            }

            foreach ($columns as $column) {
                $report[] = [$table, $column, $eligible];
                $totalRows += $eligible;
            }
        }

        $this->table($headers, $report);
        $this->line('Total column-updates: '.$totalRows);
        $this->newLine();

        if (! $force) {
            $this->warn('Dry-run only. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        $this->line('Applying...');

        DB::transaction(function () use ($cutoff, $hours): void {
            foreach (self::TARGETS as $table => $columns) {
                if (! $this->tableHasCreatedAt($table)) {
                    continue;
                }

                $sets = collect($columns)
                    ->map(fn (string $col): string => "{$col} = {$col} - interval '{$hours} hours'")
                    ->implode(', ');

                $affected = DB::update(
                    "UPDATE {$table} SET {$sets} WHERE created_at <= ?",
                    [$cutoff->format('Y-m-d H:i:s.uP')],
                );

                $this->line(sprintf('  %s: %d rows', $table, $affected));
            }
        });

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function tableHasCreatedAt(string $table): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, 'created_at');
    }
}
