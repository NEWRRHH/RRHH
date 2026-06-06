<?php
require __DIR__ . '/vendor/autoload.php';
use Carbon\Carbon;
$start = Carbon::parse('2026-02-20 23:25:27');
$now = Carbon::parse('2026-02-20 23:27:45');
echo $start->diffInSeconds($now) . "\n";
