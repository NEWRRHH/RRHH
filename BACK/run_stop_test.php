<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// create a dummy attendance record
$now = \Carbon\Carbon::now();
$attId = DB::table('attendances')->insertGetId([
    'user_id' => 1,
    'session_token' => null,
    'date' => $now->format('Y-m-d'),
    'start_time' => $now->format('H:i:s'),
    'status' => 'en_trabajo',
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "created attendance id $attId\n";

// wait a few seconds to simulate passage
echo "sleeping...\n";
sleep(2);

// call stopAttendance on controller
$user = User::find(1);
$request = Request::create('/api/attendance/stop', 'POST');
$request->setUserResolver(function() use ($user) { return $user; });

$ctrl = new AuthController();
$response = $ctrl->stopAttendance($request);
echo "response: ";
print_r($response->getContent());

// show row
$row = DB::table('attendances')->where('id', $attId)->first();
print_r($row);
