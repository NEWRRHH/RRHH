<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel per user: only the authenticated user can listen to their own channel.
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Everyone in ONE company: "someone clocked in/out" pings for the dashboard counters.
Broadcast::channel('company.{companyId}.attendance', function ($user, $companyId) {
    if (!$user || $user->isSuperAdmin()) return false;

    return (int) $user->company_id === (int) $companyId;
});

// Request reviewers of ONE company (multi-tenant: never a global channel).
Broadcast::channel('company.{companyId}.timeoff.reviewers', function ($user, $companyId) {
    if (!$user || $user->isSuperAdmin()) return false;

    return (int) $user->company_id === (int) $companyId
        && $user->hasPermission('requests.review');
});
