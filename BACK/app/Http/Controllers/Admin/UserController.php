<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Global user search and account support actions (any company).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:150',
            'company_id' => 'nullable|integer',
            'status' => 'nullable|in:active,disabled',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $users = User::query()
            ->with(['company:id,name,status', 'role:id,name,is_admin'])
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")))
            ->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when(($data['status'] ?? null) === 'disabled', fn ($q) => $q->whereNotNull('disabled_at'))
            ->when(($data['status'] ?? null) === 'active', fn ($q) => $q->whereNull('disabled_at'))
            ->orderBy('name')
            ->paginate($data['per_page'] ?? 25, ['id', 'name', 'email', 'company_id', 'role_id', 'is_super_admin', 'last_login_at', 'disabled_at', 'created_at']);

        return response()->json($users);
    }

    /**
     * Set a new password (given, or a random one returned once) and close
     * every open session of the user.
     */
    public function resetPassword(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'password' => ['nullable', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $generated = empty($data['password']);
        $password = $generated ? $this->randomPassword() : $data['password'];

        $user->password = Hash::make($password);
        $user->save();
        $user->tokens()->delete();

        Audit::log('user.password_reset', $user, ['email' => $user->email], companyId: $user->company_id);

        return response()->json([
            'status' => 'ok',
            // shown once to the super admin so they can hand it over
            'password' => $generated ? $password : null,
        ]);
    }

    public function disable(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'No puedes desactivar tu propia cuenta'], 422);
        }

        $user->disabled_at = now();
        $user->save();
        $user->tokens()->delete();
        Audit::log('user.disabled', $user, ['email' => $user->email], companyId: $user->company_id);

        return response()->json(['status' => 'ok']);
    }

    public function enable(int $id)
    {
        $user = User::findOrFail($id);
        $user->disabled_at = null;
        $user->save();
        Audit::log('user.enabled', $user, ['email' => $user->email], companyId: $user->company_id);

        return response()->json(['status' => 'ok']);
    }

    private function randomPassword(): string
    {
        // guaranteed to satisfy the password policy (upper, lower, digit, symbol)
        return Str::password(10, symbols: false) . 'A' . 'a' . random_int(0, 9) . '!';
    }
}
