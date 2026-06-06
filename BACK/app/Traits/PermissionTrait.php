<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait PermissionTrait
{
    /**
     * Check if a team has a specific permission code.
     */
    private function teamHasPermission(?int $teamId, string $permissionCode): bool
    {
        if (!$teamId) return false;
        return DB::table('team_permision')
            ->join('permisions', 'team_permision.permision_id', '=', 'permisions.id')
            ->where('team_permision.team_id', $teamId)
            ->where('permisions.code', $permissionCode)
            ->exists();
    }

    /**
     * Check if a user's team has a specific permission code.
     */
    private function hasPermission(?object $user, string $permissionCode): bool
    {
        if (!$user) return false;
        return $this->teamHasPermission((int) ($user->team_id ?? 0), $permissionCode);
    }

    /**
     * Check if user is admin (user_type_id === 1).
     */
    private function isAdminUser(?object $user): bool
    {
        return (int) ($user->user_type_id ?? 0) === 1;
    }

    /**
     * Check if user belongs to an HR/human-resources team.
     */
    private function isHrTeam(?object $user): bool
    {
        if (!$user || empty($user->team_id)) return false;
        $team = DB::table('teams')->where('id', (int) $user->team_id)->first(['name']);
        if (!$team || empty($team->name)) return false;
        $name = strtolower(trim((string) $team->name));
        return str_contains($name, 'rrhh')
            || str_contains($name, 'rr.hh')
            || str_contains($name, 'recursos humanos')
            || $name === 'rh';
    }

    /**
     * Check if user can manage permissions (admin only).
     */
    private function canManagePermissions(?object $user): bool
    {
        return $this->isAdminUser($user);
    }
}
