<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

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

Broadcast::channel('timeoff.reviewers', function ($user) {
    if (!$user) return false;
    if ((int) ($user->user_type_id ?? 0) === 1) return true;

    return DB::table('team_permision')
        ->join('permisions', 'team_permision.permision_id', '=', 'permisions.id')
        ->where('team_permision.team_id', (int) ($user->team_id ?? 0))
        ->where('permisions.code', 'requests.review')
        ->exists();
});
