<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $limit = max(1, min(100, (int) $request->query('limit', 20)));

        $rows = DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->leftJoin('teams', 'posts.team_id', '=', 'teams.id')
            ->where(function ($q) use ($user) {
                $q->where('posts.audience_scope', 'all')
                  ->orWhere(function ($q2) use ($user) {
                      $q2->where('posts.audience_scope', 'team')
                         ->where('posts.team_id', $user->team_id);
                  });
            })
            ->orderBy('posts.created_at', 'desc')
            ->limit($limit)
            ->get([
                'posts.id',
                'posts.title',
                'posts.body',
                'posts.created_at',
                'posts.audience_scope',
                'posts.team_id',
                'teams.name as team_name',
                'users.first_name',
                'users.last_name',
                'users.name as user_name',
            ])
            ->map(function ($p) {
                $author = trim(($p->first_name ?? $p->user_name ?? '') . ' ' . ($p->last_name ?? ''));
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'body' => $p->body,
                    'created_at' => $p->created_at,
                    'author' => $author !== '' ? $author : 'Sistema',
                    'scope' => $p->audience_scope ?? 'all',
                    'team_id' => $p->team_id,
                    'team_name' => $p->team_name,
                ];
            })
            ->values();

        return response()->json($rows)->header('Access-Control-Allow-Origin', '*');
    }

    public function teams(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $rows = DB::table('teams')
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($rows)->header('Access-Control-Allow-Origin', '*');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'scope' => 'required|string|in:all,team',
            'team_id' => 'nullable|integer|exists:teams,id',
        ]);

        $scope = (string) $data['scope'];
        $teamId = $scope === 'team' ? (int) ($data['team_id'] ?? 0) : null;
        if ($scope === 'team' && $teamId <= 0) {
            return response()->json(['message' => 'Debes seleccionar un equipo'], 422);
        }

        $id = DB::table('posts')->insertGetId([
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'user_id' => $user->id,
            'audience_scope' => $scope,
            'team_id' => $teamId ?: null,
            'status' => 'published',
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'ok', 'id' => $id])->header('Access-Control-Allow-Origin', '*');
    }
}

