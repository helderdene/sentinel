<?php

namespace App\Database;

use Illuminate\Database\Query\Grammars\PostgresGrammar as BaseGrammar;

/**
 * pgsql query grammar that pins the date format to `Y-m-d H:i:s.uP` so
 * Eloquent writes Carbons with an explicit TZ offset (`+08:00`). Makes
 * TIMESTAMPTZ writes unambiguous regardless of the PG session timezone — fix
 * for the FRAS Events "Just now" bug where offset-less serialization caused
 * Manila wall times to be stamped as UTC, landing 8h in the future.
 *
 * Wired in AppServiceProvider::boot via Connection::setQueryGrammar.
 */
class PostgresGrammar extends BaseGrammar
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.uP';
    }
}
