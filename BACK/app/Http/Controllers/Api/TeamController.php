<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Teams (departments) CRUD.
 */
class TeamController extends Controller
{
    /**
     * List all teams.
     */
    public function listTeams(Request $request)
    {
        $user = $request->user();
        if ($user->cannot('teams.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $teams = Team::table()
            ->whereNull('deleted_at')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'created_at'])
            ->map(function ($t) {
                $memberCount = User::table()
                    ->where('team_id', $t->id)
                    ->whereNull('deleted_at')
                    ->count();
                return [
                    'id' => (int) $t->id,
                    'name' => $t->name,
                    'members_count' => (int) $memberCount,
                    'created_at' => $t->created_at,
                ];
            })
            ->values();

        return response()->json(['teams' => $teams]);
    }

    /**
     * Create a new team.
     */
    public function createTeam(Request $request)
    {
        $user = $request->user();
        if ($user->cannot('teams.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100|unique:teams,name,NULL,id,deleted_at,NULL',
        ]);

        $id = Team::table()->insertGetId([
            'name' => trim($data['name']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $team = Team::table()->where('id', $id)->first(['id', 'name', 'created_at']);

        return response()->json([
            'status' => 'ok',
            'team' => [
                'id' => (int) $team->id,
                'name' => $team->name,
                'members_count' => 0,
                'created_at' => $team->created_at,
            ],
        ], 201);
    }

    /**
     * Update a team name.
     */
    public function updateTeam(Request $request, int $id)
    {
        $user = $request->user();
        if ($user->cannot('teams.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $team = Team::table()->where('id', $id)->whereNull('deleted_at')->first(['id']);
        if (!$team) {
            return response()->json(['message' => 'Equipo no encontrado'], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100|unique:teams,name,' . $id . ',id,deleted_at,NULL',
        ]);

        Team::table()
            ->where('id', $id)
            ->update([
                'name' => trim($data['name']),
                'updated_at' => now(),
            ]);

        $memberCount = User::table()
            ->where('team_id', $id)
            ->whereNull('deleted_at')
            ->count();

        $updated = Team::table()->where('id', $id)->first(['id', 'name', 'created_at']);

        return response()->json([
            'status' => 'ok',
            'team' => [
                'id' => (int) $updated->id,
                'name' => $updated->name,
                'members_count' => (int) $memberCount,
                'created_at' => $updated->created_at,
            ],
        ]);
    }

    /**
     * Soft-delete a team.
     */
    public function deleteTeam(Request $request, int $id)
    {
        $user = $request->user();
        if ($user->cannot('teams.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $team = Team::table()->where('id', $id)->whereNull('deleted_at')->first(['id', 'name']);
        if (!$team) {
            return response()->json(['message' => 'Equipo no encontrado'], 404);
        }

        // Check if team has members
        $memberCount = User::table()
            ->where('team_id', $id)
            ->whereNull('deleted_at')
            ->count();

        if ($memberCount > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el equipo porque tiene ' . $memberCount . ' miembro(s). Reasigna los usuarios antes de eliminar.',
            ], 422);
        }

        Team::table()
            ->where('id', $id)
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['status' => 'ok']);
    }
}
