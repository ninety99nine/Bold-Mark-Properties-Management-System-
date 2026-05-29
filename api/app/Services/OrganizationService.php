<?php

namespace App\Services;

use App\Models\CashbookEntry;
use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use App\Models\ComplianceItemAttachment;
use App\Models\Estate;
use App\Models\EstateChargeType;
use App\Models\Invoice;
use App\Models\InvoiceEmailEvent;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitActivity;
use App\Models\UnitChargeConfig;
use App\Models\User;
use App\Models\UserEstate;
use App\Models\UserLoginLog;
use App\Models\TableView;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\TenantResources;
use Illuminate\Support\Facades\DB;

class OrganizationService extends BaseService
{
    /**
     * Return a single tenant resource with its estates loaded.
     *
     * @param Organization $tenant
     * @return OrganizationResource
     */
    public function showTenant(Organization $tenant): OrganizationResource
    {
        $tenant->load(['estates']);

        return $this->showResource($tenant);
    }

    /**
     * Update the tenant's company settings and branding.
     *
     * @param Organization $tenant
     * @param array  $data
     * @return array
     */
    public function updateTenant(Organization $tenant, array $data): array
    {
        $updateData = collect($data)
            ->only([
                'company_name',
                'company_slogan',
                'contact_email',
                'contact_phone',
                'address',
                'country',
                'currency',
                'primary_color',
                'secondary_color',
                'copyright_name',
            ])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $tenant->update($updateData);

        return $this->showUpdatedResource($tenant);
    }

    /**
     * Flush selected data categories from the tenant in FK-safe order.
     *
     * @param Organization $tenant
     * @param array        $targets       Categories to delete (e.g. ['estates', 'invoices'])
     * @param array        $keepUserIds   User IDs to preserve when 'users' is a target
     * @return array
     */
    public function flushTenant(Organization $tenant, array $targets, array $keepUserIds = []): array
    {
        $orgId  = $tenant->id;
        $counts = [];

        DB::transaction(function () use ($orgId, $targets, $keepUserIds, &$counts) {

            // 1. Invoices (+ email events)
            if (in_array('invoices', $targets)) {
                $invoiceIds = Invoice::where('organization_id', $orgId)->pluck('id');
                InvoiceEmailEvent::whereIn('invoice_id', $invoiceIds)->delete();
                $counts['invoices'] = Invoice::where('organization_id', $orgId)->delete();
            }

            // 2. Cashbook entries
            if (in_array('cashbook_entries', $targets)) {
                $counts['cashbook_entries'] = CashbookEntry::where('organization_id', $orgId)->delete();
            }

            // 3. Tenants
            if (in_array('tenants', $targets)) {
                $counts['tenants'] = Tenant::where('organization_id', $orgId)->delete();
            }

            // 4. Owners
            if (in_array('owners', $targets)) {
                $counts['owners'] = Owner::where('organization_id', $orgId)->delete();
            }

            // 5. Estates (cascade: compliance, unit configs, units, charge type links)
            if (in_array('estates', $targets)) {
                $estateIds = Estate::where('organization_id', $orgId)->pluck('id');
                $unitIds   = Unit::where('organization_id', $orgId)->whereIn('estate_id', $estateIds)->pluck('id');

                // Compliance: checklists → items → attachments
                $checklistIds = ComplianceChecklist::where('organization_id', $orgId)
                    ->whereIn('estate_id', $estateIds)
                    ->pluck('id');
                $itemIds = ComplianceChecklistItem::whereIn('compliance_checklist_id', $checklistIds)->pluck('id');
                ComplianceItemAttachment::whereIn('compliance_checklist_item_id', $itemIds)->delete();
                ComplianceChecklistItem::whereIn('id', $itemIds)->delete();
                ComplianceChecklist::whereIn('id', $checklistIds)->delete();

                UnitChargeConfig::whereIn('unit_id', $unitIds)->delete();
                UnitActivity::whereIn('unit_id', $unitIds)->delete();
                Unit::whereIn('id', $unitIds)->delete();

                EstateChargeType::whereIn('estate_id', $estateIds)->delete();
                $counts['estates'] = Estate::whereIn('id', $estateIds)->delete();
            }

            // 6. Units (standalone — skipped if already deleted via estates)
            if (in_array('units', $targets)) {
                $unitIds = Unit::where('organization_id', $orgId)->pluck('id');
                UnitChargeConfig::whereIn('unit_id', $unitIds)->delete();
                UnitActivity::whereIn('unit_id', $unitIds)->delete();
                $counts['units'] = Unit::where('organization_id', $orgId)->delete();
            }

            // 7. Users (excluding kept ones)
            if (in_array('users', $targets)) {
                $userIds = User::where('organization_id', $orgId)
                    ->whereNotIn('id', $keepUserIds)
                    ->pluck('id');

                UserLoginLog::whereIn('user_id', $userIds)->delete();
                UserEstate::whereIn('user_id', $userIds)->delete();
                TableView::whereIn('user_id', $userIds)->delete();

                $counts['users'] = User::whereIn('id', $userIds)->delete();
            }
        });

        return [
            'message' => 'Organisation data flushed successfully.',
            'deleted' => $counts,
        ];
    }
}
