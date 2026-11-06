<?php

namespace App\Services;

use App\Enums\BeerStatus;
use App\Enums\PointsEventType;
use App\Models\Beer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BeerModerationService
{
    public function __construct(private readonly PointsService $points) {}

    public function approve(Beer $beer, User $admin): Beer
    {
        return DB::transaction(function () use ($beer, $admin) {
            $beer = Beer::query()->lockForUpdate()->findOrFail($beer->id);
            if ($beer->status === BeerStatus::Approved) {
                return $beer;
            }

            $beer->forceFill([
                'status' => BeerStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            // +15 to the submitter (not for admin-created beers).
            if ($beer->submitter && $beer->submitted_by !== $admin->id) {
                $this->points->award($beer->submitter, PointsEventType::BeerApproved, $beer, actor: $admin);
            }

            return $beer;
        });
    }

    public function reject(Beer $beer, User $admin, ?string $reason = null): Beer
    {
        $beer->forceFill([
            'status' => BeerStatus::Rejected,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return $beer;
    }
}
