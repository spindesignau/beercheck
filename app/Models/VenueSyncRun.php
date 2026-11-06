<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueSyncRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function appendError(string $message): void
    {
        $this->error_log = ltrim(($this->error_log ?? '')."\n".'['.now()->toDateTimeString().'] '.$message);
    }
}
