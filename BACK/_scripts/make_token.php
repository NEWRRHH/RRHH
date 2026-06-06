<?php
// bootstrap Laravel so we can use Eloquent
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$u = User::find(1);
if (! $u) {
    echo "no user\n";
    exit(1);
}
$t = $u->createToken('test')->plainTextToken;
echo $t . "\n";
