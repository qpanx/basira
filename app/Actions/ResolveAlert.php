<?php

namespace App\Actions;

use App\Enums\AlertResolution;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ResolveAlert
{
    public function handle(Alert $alert, User $actor, ?string $notes = null): Alert
    {
        return DB::transaction(function () use ($alert, $actor, $notes): Alert {
            $lockedAlert = Alert::query()->lockForUpdate()->findOrFail($alert->getKey());
            Gate::forUser($actor)->authorize('resolve', $lockedAlert);

            $lockedAlert->forceFill([
                'resolution' => AlertResolution::Confirmed,
                'resolution_notes' => $notes,
                'resolved_at' => now(),
                'resolved_by' => $actor->getKey(),
            ])->save();

            return $lockedAlert->fresh(['resolver']);
        });
    }
}
