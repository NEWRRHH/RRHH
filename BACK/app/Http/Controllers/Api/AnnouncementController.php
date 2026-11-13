<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostRead;
use App\Models\Team;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    private function canAccessAnnouncements(?object $user): bool
    {
        if (!$user) return false;
        return $user->hasPermission('announcements.manage');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        $canSeeAll = $this->canAccessAnnouncements($user);

        $query = Post::table()
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->leftJoin('teams', 'posts.team_id', '=', 'teams.id')
            ->leftJoin('post_reads', function ($join) use ($user) {
                $join->on('post_reads.post_id', '=', 'posts.id')
                     ->where('post_reads.user_id', '=', $user->id);
            });

        if (!$canSeeAll) {
            $query->where(function ($q) use ($user) {
                $q->where('posts.audience_scope', 'all')
                  ->orWhere(function ($q2) use ($user) {
                      $q2->where('posts.audience_scope', 'team')
                         ->where('posts.team_id', $user->team_id);
                  });
            });
        }

        $rows = $query
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
                'post_reads.read_at',
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
                    'is_read' => !is_null($p->read_at),
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function teams(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);
        if (!$this->canAccessAnnouncements($user)) return response()->json(['message' => 'Forbidden'], 403);

        $rows = Team::table()
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);
        if (!$this->canAccessAnnouncements($user)) return response()->json(['message' => 'Forbidden'], 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'scope' => 'required|string|in:all,team',
            'team_id' => ['nullable', 'integer', TenantRule::exists('teams', 'id', true)],
        ]);

        $scope = (string) $data['scope'];
        $teamId = $scope === 'team' ? (int) ($data['team_id'] ?? 0) : null;
        if ($scope === 'team' && $teamId <= 0) {
            return response()->json(['message' => 'Debes seleccionar un equipo'], 422);
        }

        $id = Post::table()->insertGetId([
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'user_id' => $user->id,
            'audience_scope' => $scope,
            'team_id' => $teamId ?: null,
            'status' => 'published',
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'ok', 'id' => $id]);
    }

    public function markRead(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $exists = Post::table()->where('id', $id)->exists();
        if (!$exists) return response()->json(['message' => 'No encontrado'], 404);

        PostRead::table()->insertOrIgnore([
            'user_id' => $user->id,
            'post_id' => $id,
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
