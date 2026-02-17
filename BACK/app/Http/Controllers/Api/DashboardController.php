<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'Welcome to the API dashboard',
            'stats' => [
                'users_count' => User::count(),
            ],
        ])->header('Access-Control-Allow-Origin', '*');
    }
}
