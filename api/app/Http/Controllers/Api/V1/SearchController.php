<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Perform a global search across estates, units, people, and invoices.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
        ]);

        $term     = $request->input('q');
        $user     = Auth::user();
        $tenantId = $user->organization_id;
        $limit    = 5;

        // Estates
        $estates = Estate::where('organization_id', $tenantId)
            ->where(fn ($q) => $q->search($term))
            ->withCount('units')
            ->limit($limit)
            ->get()
            ->map(fn (Estate $e) => [
                'id'          => $e->id,
                'name'        => $e->name,
                'type'        => $e->type?->value,
                'units_count' => $e->units_count,
            ]);

        // Units — already wraps orWhere in a nested where inside its search scope
        $units = Unit::where('units.organization_id', $tenantId)
            ->search($term)
            ->with(['estate:id,name', 'owner:id,unit_id,full_name'])
            ->limit($limit)
            ->get()
            ->map(fn (Unit $u) => [
                'id'          => $u->id,
                'unit_number' => $u->unit_number,
                'estate_id'   => $u->estate_id,
                'estate_name' => $u->estate?->name,
                'owner_name'  => $u->owner?->full_name,
            ]);

        // People — owners, unit organizations, and system users
        $owners = Owner::where('organization_id', $tenantId)
            ->where(fn ($q) => $q->search($term))
            ->with(['unit:id,unit_number,estate_id', 'unit.estate:id,name'])
            ->limit($limit)
            ->get()
            ->map(fn (Owner $o) => [
                'id'        => $o->id,
                'name'      => $o->full_name,
                'role'      => 'Owner',
                'context'   => $o->unit ? ($o->unit->unit_number . ' · ' . ($o->unit->estate?->name ?? '')) : null,
                'estate_id' => $o->unit?->estate_id,
                'unit_id'   => $o->unit_id,
            ]);

        $unitTenants = Tenant::where('organization_id', $tenantId)
            ->where(fn ($q) => $q->search($term))
            ->with(['unit:id,unit_number,estate_id', 'unit.estate:id,name'])
            ->limit($limit)
            ->get()
            ->map(fn (Tenant $t) => [
                'id'        => $t->id,
                'name'      => $t->full_name,
                'role'      => 'Organization',
                'context'   => $t->unit ? ($t->unit->unit_number . ' · ' . ($t->unit->estate?->name ?? '')) : null,
                'estate_id' => $t->unit?->estate_id,
                'unit_id'   => $t->unit_id,
            ]);

        $users = User::where('organization_id', $tenantId)
            ->where(fn ($q) => $q->search($term))
            ->limit($limit)
            ->get()
            ->map(fn (User $u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'role'    => $u->roles->first()?->name ?? 'User',
                'context' => null,
            ]);

        $people = $owners->concat($unitTenants)->concat($users)
            ->unique(fn ($p) => $p['role'] . ':' . $p['id'])
            ->take($limit)
            ->values();

        // Invoices
        $invoices = Invoice::where('organization_id', $tenantId)
            ->where(fn ($q) => $q->search($term))
            ->with(['unit:id,unit_number,estate_id', 'unit.estate:id,name'])
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $i) => [
                'id'             => $i->id,
                'invoice_number' => $i->invoice_number,
                'amount'         => $i->amount,
                'status'         => $i->status?->value,
                'unit_number'    => $i->unit?->unit_number,
                'estate_name'    => $i->unit?->estate?->name,
            ]);

        return response()->json([
            'estates'  => $estates,
            'units'    => $units,
            'people'   => $people,
            'invoices' => $invoices,
        ]);
    }
}
