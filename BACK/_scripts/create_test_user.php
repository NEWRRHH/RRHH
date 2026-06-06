<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->handle(
    new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput
);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$u = User::create(['name' => 'cli', 'email' => 'cli@local.test', 'password' => Hash::make('secret')]);
echo $u->createToken('cli')->plainTextToken.PHP_EOL;
