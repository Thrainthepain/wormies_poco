<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PocoSource;
use App\Models\Poco;
use App\Models\User;

final class PocoPolicy
{
    /**
     * ESI-sourced offices have no single owning map (they're visible on every
     * map that reaches their system), so update access for them is just
     * "signed in" - the Action layer is what actually protects their
     * structural fields from being changed by an update. Manually-reported
     * offices are scoped to the map they were reported on, same as
     * Signature::update.
     */
    public function update(User $user, Poco $poco): bool
    {
        if ($poco->source === PocoSource::Manual) {
            return $poco->map !== null && $user->can('update', $poco->map);
        }

        return true;
    }

    /**
     * ESI-sourced offices are only ever removed by the scheduled pull job -
     * a manual delete would just get silently recreated on the next run, and
     * could hide a real, currently-owned office from other map users in the
     * meantime.
     */
    public function delete(User $user, Poco $poco): bool
    {
        if ($poco->source === PocoSource::Esi) {
            return false;
        }

        return $poco->map !== null && $user->can('update', $poco->map);
    }
}
