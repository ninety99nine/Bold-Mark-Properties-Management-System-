<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Occupant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Perform a global search across communities, units, people, and invoices.
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
        $organizationId = $user->organization_id;
        $limit    = 5;

        // Communities
        $communities = Community::where('organization_id', $organizationId)
            ->where(fn ($q) => $q->search($term))
            ->withCount('units')
            ->limit($limit)
            ->get()
            ->map(fn (Community $e) => [
                'id'          => $e->id,
                'name'        => $e->name,
                'entity_type' => $e->entity_type?->value,
                'units_count' => $e->units_count,
            ]);

        // Units — already wraps orWhere in a nested where inside its search scope
        $units = Unit::where('units.organization_id', $organizationId)
            ->search($term)
            ->with(['community:id,name', 'owner:id,unit_id,full_name'])
            ->limit($limit)
            ->get()
            ->map(fn (Unit $u) => [
                'id'          => $u->id,
                'unit_number' => $u->unit_number,
                'community_id'   => $u->community_id,
                'community_name' => $u->community?->name,
                'owner_name'  => $u->owner?->full_name,
            ]);

        // People — owners, unit organizations, and system users
        $owners = Owner::where('organization_id', $organizationId)
            ->where(fn ($q) => $q->search($term))
            ->with(['unit:id,unit_number,community_id', 'unit.community:id,name'])
            ->limit($limit)
            ->get()
            ->map(fn (Owner $o) => [
                'id'        => $o->id,
                'name'      => $o->full_name,
                'role'      => 'Owner',
                'context'   => $o->unit ? ($o->unit->unit_number . ' · ' . ($o->unit->community?->name ?? '')) : null,
                'community_id' => $o->unit?->community_id,
                'unit_id'   => $o->unit_id,
            ]);

        $unitOccupants = Occupant::where('organization_id', $organizationId)
            ->where(fn ($q) => $q->search($term))
            ->with(['unit:id,unit_number,community_id', 'unit.community:id,name'])
            ->limit($limit)
            ->get()
            ->map(fn (Occupant $t) => [
                'id'        => $t->id,
                'name'      => $t->full_name,
                'role'      => 'Organization',
                'context'   => $t->unit ? ($t->unit->unit_number . ' · ' . ($t->unit->community?->name ?? '')) : null,
                'community_id' => $t->unit?->community_id,
                'unit_id'   => $t->unit_id,
            ]);

        $users = User::where('organization_id', $organizationId)
            ->where(fn ($q) => $q->search($term))
            ->limit($limit)
            ->get()
            ->map(fn (User $u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'role'    => $u->roles->first()?->name ?? 'User',
                'context' => null,
            ]);

        $people = $owners->concat($unitOccupants)->concat($users)
            ->unique(fn ($p) => $p['role'] . ':' . $p['id'])
            ->take($limit)
            ->values();

        // Invoices
        $invoices = Invoice::where('organization_id', $organizationId)
            ->where(fn ($q) => $q->search($term))
            ->with(['unit:id,unit_number,community_id', 'unit.community:id,name'])
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $i) => [
                'id'             => $i->id,
                'invoice_number' => $i->invoice_number,
                'amount'         => $i->amount,
                'status'         => $i->status?->value,
                'unit_number'    => $i->unit?->unit_number,
                'community_name'    => $i->unit?->community?->name,
            ]);

        return response()->json([
            'communities'  => $communities,
            'units'    => $units,
            'people'   => $people,
            'invoices' => $invoices,
        ]);
    }
}
