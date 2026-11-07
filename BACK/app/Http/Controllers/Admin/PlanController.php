<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Plans and their limits (super admin).
 */
class PlanController extends Controller
{
    public function index()
    {
        return response()->json(
            Plan::withCount('companies')->orderBy('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $plan = DB::transaction(function () use ($data) {
            if (! empty($data['is_default'])) {
                Plan::query()->update(['is_default' => false]);
            }

            return Plan::create($data);
        });
        Audit::log('plan.created', $plan, ['code' => $plan->code], companyId: null);

        return response()->json(['status' => 'ok', 'plan' => $plan], 201);
    }

    public function update(Request $request, int $id)
    {
        $plan = Plan::findOrFail($id);
        $data = $this->validated($request, $plan);
        unset($data['code']); // companies reference the code: it never changes

        DB::transaction(function () use ($plan, $data) {
            if (! empty($data['is_default'])) {
                Plan::whereKeyNot($plan->id)->update(['is_default' => false]);
            }
            $plan->update($data);
        });
        Audit::log('plan.updated', $plan, $plan->only(['name', 'max_employees', 'max_storage_mb', 'is_default']), companyId: null);

        return response()->json(['status' => 'ok', 'plan' => $plan->fresh()]);
    }

    public function destroy(int $id)
    {
        $plan = Plan::withCount('companies')->findOrFail($id);
        if ($plan->companies_count > 0) {
            return response()->json(['message' => "No se puede eliminar: {$plan->companies_count} empresa(s) usan este plan."], 422);
        }
        if ($plan->is_default) {
            return response()->json(['message' => 'No se puede eliminar el plan por defecto.'], 422);
        }

        $plan->delete();
        Audit::log('plan.deleted', $plan, ['code' => $plan->code], companyId: null);

        return response()->json(['status' => 'ok']);
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'code' => [$plan ? 'nullable' : 'required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', Rule::unique('plans', 'code')->ignore($plan?->id)],
            'name' => 'required|string|max:100',
            'max_employees' => 'nullable|integer|min:1|max:1000000',
            'max_storage_mb' => 'nullable|integer|min:1|max:10000000',
            'is_default' => 'nullable|boolean',
        ]);
    }
}
