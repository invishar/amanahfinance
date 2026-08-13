<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

// Dipicu harian lewat scheduler (routes/console.php), bukan worker/daemon --
// selaras dengan pola burst di hPanel (lihat CLAUDE.md "Perintah"). Query
// lintas-family langsung: BelongsToFamily hanya scope query saat CurrentFamily
// ada isinya, dan artisan command tidak pernah lewat ResolveFamily.
class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'amana:expire-subscriptions';

    protected $description = 'Nonaktifkan langganan yang sudah melewati masa aktif (ends_at)';

    public function handle(): int
    {
        $expired = Subscription::query()
            ->where('status', 'active')
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("{$expired} langganan ditandai expired.");

        return self::SUCCESS;
    }
}
