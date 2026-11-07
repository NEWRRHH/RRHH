<?php

namespace App\Services;

use App\Models\Company;
use App\Models\EventType;
use App\Models\Plan;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserSchedule;
use App\Support\DefaultRoles;
use App\Support\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a ready-to-use company: the company row, its default roles,
 * event types and work schedule, and its first user as company Admin.
 *
 * Used by public sign-up and (later) by the super admin panel.
 */
class CompanyProvisioner
{
    /** Event types every company starts with (code = used by the app logic). */
    public const DEFAULT_EVENT_TYPES = [
        ['name' => 'General', 'code' => null],
        ['name' => 'Vacaciones', 'code' => EventType::VACATION],
        ['name' => 'Permiso', 'code' => null],
        ['name' => 'Fichaje', 'code' => EventType::ATTENDANCE_REQUEST],
        ['name' => 'Reunión', 'code' => null],
    ];

    /**
     * @param  array{name: string, email: string, password: string}  $admin
     * @return array{company: Company, admin: User}
     */
    public function provision(string $companyName, array $admin): array
    {
        return DB::transaction(function () use ($companyName, $admin) {
            $company = Company::create([
                'name' => trim($companyName),
                'slug' => $this->uniqueSlug($companyName),
                'status' => Company::STATUS_ACTIVE,
                'plan' => Plan::defaultCode(),
            ]);

            $roles = DefaultRoles::ensure($company->id);

            $user = Tenant::run($company->id, function () use ($roles, $admin) {
                foreach (self::DEFAULT_EVENT_TYPES as $type) {
                    EventType::create($type);
                }

                $schedule = Schedule::create([
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'days' => json_encode(['L', 'M', 'X', 'J', 'V']),
                ]);

                $name = trim($admin['name']);
                [$first, $last] = array_pad(explode(' ', $name, 2), 2, null);

                $user = new User([
                    'name' => $name,
                    'email' => $admin['email'],
                    'password' => Hash::make($admin['password']),
                    'first_name' => $first,
                    'last_name' => $last,
                ]);
                // privileged attributes, never mass assigned
                $user->role_id = $roles['admin'];
                $user->save();

                UserSchedule::table()->insert([
                    'user_id' => $user->id,
                    'schedule_id' => $schedule->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $user;
            });

            return ['company' => $company, 'admin' => $user];
        });
    }

    /**
     * "Acme S.A." -> "acme-sa", "acme-sa-2", ... (soft-deleted companies count too).
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'empresa';
        $slug = $base;
        $i = 2;
        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
