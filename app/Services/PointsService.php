<?php

namespace App\Services;

use App\Enums\PointsEventType;
use App\Models\PointsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only code path allowed to change users.points.
 *
 * Every change writes an append-only points_events row and updates the
 * denormalised total in the same transaction, with the user row locked
 * (SELECT ... FOR UPDATE) so concurrent awards serialise correctly.
 * Awards are idempotent per (user, event_type, reference).
 */
class PointsService
{
    public function award(
        User $user,
        PointsEventType $type,
        ?Model $reference = null,
        ?int $points = null,
        ?User $actor = null,
        ?string $note = null,
    ): ?PointsEvent {
        $points ??= $type->points();
        if ($points === 0) {
            return null;
        }

        $refType = $reference?->getMorphClass();
        $refId = $reference?->getKey();

        try {
            return DB::transaction(function () use ($user, $type, $points, $refType, $refId, $actor, $note) {
                /** @var User $locked */
                $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

                $alreadyAwarded = $refId !== null && PointsEvent::query()
                    ->where('user_id', $locked->id)
                    ->where('event_type', $type)
                    ->where('reference_type', $refType)
                    ->where('reference_id', $refId)
                    ->exists();

                if ($alreadyAwarded) {
                    return null;
                }

                $event = PointsEvent::forceCreate([
                    'user_id' => $locked->id,
                    'event_type' => $type,
                    'points_awarded' => $points,
                    'reference_type' => $refType,
                    'reference_id' => $refId,
                    'created_by' => $actor?->id,
                    'note' => $note,
                ]);

                // Row is locked, so read-modify-write is safe; totals never go negative.
                $newTotal = max(0, $locked->points + $points);
                $locked->forceFill(['points' => $newTotal])->save();
                $user->setRawAttributes(array_merge($user->getAttributes(), ['points' => $newTotal]), true);

                return $event;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            // Lost a race against an identical award: the idempotency index did its job.
            return null;
        }
    }

    /**
     * Claw back everything awarded for a reference (e.g. a price post removed by
     * a moderator). Recorded as a single negative ledger entry.
     */
    public function reverse(Model $reference, ?User $actor = null, ?string $note = null): int
    {
        $awarded = PointsEvent::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->selectRaw('user_id, SUM(points_awarded) AS total')
            ->groupBy('user_id')
            ->get();

        $reversed = 0;
        foreach ($awarded as $row) {
            if ((int) $row->total <= 0) {
                continue;
            }
            $user = User::find($row->user_id);
            if ($user && $this->award($user, PointsEventType::ModerationReversal, $reference, -1 * (int) $row->total, $actor, $note)) {
                $reversed += (int) $row->total;
            }
        }

        return $reversed;
    }
}
