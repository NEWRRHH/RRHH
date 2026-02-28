<?php
// Quick test: fire a MessageSent event and check it reaches Reverb without errors.
// Run: docker compose exec app php test_broadcast_now.php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$n = \App\Models\Notification::latest()->first();
if (!$n) {
    echo "No notifications in DB — send a message via the UI first.\n";
    exit(1);
}

echo "Broadcasting for notification #{$n->id} (target={$n->receiver_id})...\n";
event(new \App\Events\MessageSent($n, (int) $n->receiver_id));
echo "Done — no exception means broadcast reached Reverb successfully.\n";
