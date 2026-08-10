<?php

namespace App\Support;

use App\Exceptions\ConflictException;
use Illuminate\Database\QueryException;

// wallet_id/source_id/goal_id/account_id on transactions use restrictOnDelete
// (see 2026_01_01_001000_create_transactions_table.php) so the DB itself
// blocks deleting a row still referenced by a transaction. This turns that
// FK violation into a clean 409 instead of a raw 500.
class DeletesSafely
{
    public static function run(callable $callback, string $conflictMessage): void
    {
        try {
            $callback();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new ConflictException($conflictMessage);
            }

            throw $e;
        }
    }
}
