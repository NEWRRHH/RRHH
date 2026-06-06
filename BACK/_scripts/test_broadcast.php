<?php

// Bootstrap Laravel
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "reverb.key: "  . config('broadcasting.connections.reverb.key', 'NOT-SET') . PHP_EOL;
echo "reverb.host (options): " . config('broadcasting.connections.reverb.options.host', 'NOT-SET') . PHP_EOL;

// 1) Synchronous broadcast (bypasses queue)
try {
    $manager = app(Illuminate\Broadcasting\BroadcastManager::class);
    $manager->driver('reverb')->broadcast(
        ['private-user.1'],
        'TestEvent',
        ['message' => 'hello from test']
    );
    echo "SYNC BROADCAST OK" . PHP_EOL;
} catch (\Throwable $e) {
    echo "SYNC BROADCAST ERROR: " . $e->getMessage() . PHP_EOL;
}

// 2) Queue a real MessageSent event (processed by queue:work)
$conv = (object)['receiver_id' => 1, 'sender_id' => 2, 'conversation' => []];
try {
    event(new App\Events\MessageSent($conv, 1));
    echo "EVENT QUEUED OK" . PHP_EOL;
} catch (\Throwable $e) {
    echo "EVENT QUEUE ERROR: " . $e->getMessage() . PHP_EOL;
}
