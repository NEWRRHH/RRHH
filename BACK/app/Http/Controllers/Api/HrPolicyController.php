<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\CompanySettings;
use App\Support\FileStorage;
use Illuminate\Http\Request;

/**
 * Company HR policies (Configuración > Políticas): how many days of next
 * year can be requested as vacation and whether the monthly attendance
 * summary is also emailed.
 */
class HrPolicyController extends Controller
{
    private function canManage(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('holidays.manage');
    }

    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'settings' => CompanySettings::for($user->company_id),
            'max_next_year_days' => CompanySettings::MAX_NEXT_YEAR_DAYS,
            'can_edit' => $this->canManage($user),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if (! $this->canManage($user) || ! $user->company_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'vacation_next_year_days' => 'required|integer|min:0|max:' . CompanySettings::MAX_NEXT_YEAR_DAYS,
            'attendance_summary_email' => 'required|boolean',
        ]);

        return response()->json(['settings' => CompanySettings::save((int) $user->company_id, $data)]);
    }

    /** Company admin replaces the logo (sidebar + browser tab icon). */
    public function uploadLogo(Request $request)
    {
        $user = $request->user();
        if (! $user->isAdmin() || ! $user->company) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $request->validate(['logo' => 'required|file|max:1024']);

        try {
            $url = FileStorage::storeCompanyLogo((string) file_get_contents($request->file('logo')->getRealPath()), $user->company_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $company = $user->company;
        FileStorage::deleteCompanyLogo($company->logo);
        $company->forceFill(['logo' => $url])->save();
        Audit::log('company.logo_updated', $company);

        return response()->json(['logo' => $url]);
    }

    public function deleteLogo(Request $request)
    {
        $user = $request->user();
        if (! $user->isAdmin() || ! $user->company) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $company = $user->company;
        FileStorage::deleteCompanyLogo($company->logo);
        $company->forceFill(['logo' => null])->save();
        Audit::log('company.logo_removed', $company);

        return response()->json(['logo' => null]);
    }
}
