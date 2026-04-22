<?php

namespace App\Console\Commands;

use App\Models\FrasLegalSignoff as FrasLegalSignoffModel;
use Illuminate\Console\Command;

class FrasLegalSignoff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fras:legal-signoff {--signed-by= : Signatory name} {--contact= : Email or phone} {--notes= : Optional free-text notes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Record CDRRMO legal sign-off for Phase 22 DPA compliance (DPA-07 milestone-close gate)';

    /**
     * Execute the console command.
     *
     * Writes an append-only row to fras_legal_signoffs (T-22-09-04 mitigation:
     * audit row persists via DB backups) and appends a confirmation line to
     * the Phase 22 VALIDATION.md so the milestone-close gate can detect that
     * CDRRMO legal has reviewed Phase 22. The VALIDATION.md target path is
     * resolved via config('fras.signoff.validation_path') with a hardcoded
     * base_path() fallback (T-22-09-05 mitigation: path is server-local and
     * not user-influenced).
     */
    public function handle(): int
    {
        $signedBy = $this->option('signed-by');
        $contact = $this->option('contact');
        $notes = $this->option('notes');

        if (! $signedBy || ! $contact) {
            $this->error('--signed-by and --contact are required.');

            return self::INVALID;
        }

        $signoff = FrasLegalSignoffModel::create([
            'signed_by_name' => $signedBy,
            'contact' => $contact,
            'signed_at' => now(),
            'notes' => $notes,
        ]);

        $validationPath = config(
            'fras.signoff.validation_path',
            base_path('.planning/phases/22-alert-feed-event-history-responder-context-dpa-compliance/22-VALIDATION.md'),
        );

        if (file_exists($validationPath)) {
            $iso = $signoff->signed_at->toIso8601String();
            $line = "\n- [x] CDRRMO legal sign-off recorded via `php artisan fras:legal-signoff` (DPA-07) — signed by {$signedBy} on {$iso}\n";
            file_put_contents($validationPath, $line, FILE_APPEND);
        }

        $this->info("Legal sign-off recorded: {$signedBy} at {$signoff->signed_at->toIso8601String()}");

        return self::SUCCESS;
    }
}
