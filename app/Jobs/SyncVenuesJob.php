<?php

namespace App\Jobs;

use App\Enums\VenueSource;
use App\Models\User;
use App\Services\Sync\VenueSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Admin-triggered sync, processed by the cron-driven queue worker. */
class SyncVenuesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(public VenueSource $source, public ?int $triggeredBy = null) {}

    public function uniqueId(): string
    {
        return $this->source->value;
    }

    public function handle(VenueSyncService $sync): void
    {
        $sync->run($this->source, triggeredBy: $this->triggeredBy ? User::find($this->triggeredBy) : null);
    }
}
