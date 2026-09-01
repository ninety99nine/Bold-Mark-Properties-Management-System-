<?php

namespace App\Services;

use App\Models\CashbookEntry;
use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use App\Models\ComplianceItemAttachment;
use App\Models\Community;
use App\Models\CommunityLedger;
use App\Models\Invoice;
use App\Models\InvoiceEmailEvent;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\Occupant;
use App\Models\Unit;
use App\Models\UnitActivity;
use App\Models\UnitChargeConfig;
use App\Models\User;
use App\Models\UserCommunity;
use App\Models\UserLoginLog;
use App\Models\TableView;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\OccupantResources;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrganizationService extends BaseService
{
    /**
     * Return a single organization resource with its communities loaded.
     *
     * @param Organization $organization
     * @return OrganizationResource
     */
    public function showOrganization(Organization $organization): OrganizationResource
    {
        $organization->load(['communities']);

        return $this->showResource($organization);
    }

    /**
     * Update the organization's company settings and branding.
     *
     * @param Organization $organization
     * @param array  $data
     * @return array
     */
    public function updateOrganization(Organization $organization, array $data): array
    {
        $updateData = collect($data)
            ->only([
                'company_name',
                'company_slogan',
                'company_reg_no',
                'transfer_clearance_fee',
                'contact_email',
                'outgoing_email',
                'contact_phone',
                'address',
                'country',
                'currency',
                'bank_account_holder',
                'bank_name',
                'bank_account_type',
                'bank_account_number',
                'bank_branch_code',
                'bank_branch_name',
                'primary_color',
                'secondary_color',
                'copyright_name',
            ])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        // Image uploads (Company Details logos + default email header/footer).
        $uploads = [
            'logo'         => 'logo_url',
            'icon'         => 'icon_url',
            'email_header' => 'email_header_url',
            'email_footer' => 'email_footer_url',
        ];
        foreach ($uploads as $field => $column) {
            $file = $this->request->file($field);
            if ($file instanceof UploadedFile) {
                $this->deleteStoredImage($organization->{$column});
                $path = $file->store("organization/{$organization->id}", 'public');
                $updateData[$column] = Storage::disk('public')->url($path);
            }
        }

        // Explicit removals.
        $removals = [
            'remove_logo'         => 'logo_url',
            'remove_icon'         => 'icon_url',
            'remove_email_header' => 'email_header_url',
            'remove_email_footer' => 'email_footer_url',
        ];
        foreach ($removals as $flag => $column) {
            if ($this->request->boolean($flag)) {
                $this->deleteStoredImage($organization->{$column});
                $updateData[$column] = null;
            }
        }

        $organization->update($updateData);

        return $this->showUpdatedResource($organization);
    }

    /**
     * Delete a previously-stored public image given its full URL.
     *
     * @param string|null $url
     * @return void
     */
    private function deleteStoredImage(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = str_replace(Storage::disk('public')->url(''), '', $url);
        Storage::disk('public')->delete($path);
    }

    /**
     * Flush selected data categories from the organization in FK-safe order.
     *
     * @param Organization $organization
     * @param array        $targets       Categories to delete (e.g. ['communities', 'invoices'])
     * @param array        $keepUserIds   User IDs to preserve when 'users' is a target
     * @return array
     */
    public function flushOrganization(Organization $organization, array $targets, array $keepUserIds = []): array
    {
        $orgId  = $organization->id;
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

            // 3. Organizations
            if (in_array('occupants', $targets)) {
                $counts['occupants'] = Occupant::where('organization_id', $orgId)->delete();
            }

            // 4. Owners
            if (in_array('owners', $targets)) {
                $counts['owners'] = Owner::where('organization_id', $orgId)->delete();
            }

            // 5. Communities (cascade: compliance, unit configs, units, ledger links)
            if (in_array('communities', $targets)) {
                $communityIds = Community::where('organization_id', $orgId)->pluck('id');
                $unitIds   = Unit::where('organization_id', $orgId)->whereIn('community_id', $communityIds)->pluck('id');

                // Compliance: checklists → items → attachments
                $checklistIds = ComplianceChecklist::where('organization_id', $orgId)
                    ->whereIn('community_id', $communityIds)
                    ->pluck('id');
                $itemIds = ComplianceChecklistItem::whereIn('compliance_checklist_id', $checklistIds)->pluck('id');
                ComplianceItemAttachment::whereIn('compliance_checklist_item_id', $itemIds)->delete();
                ComplianceChecklistItem::whereIn('id', $itemIds)->delete();
                ComplianceChecklist::whereIn('id', $checklistIds)->delete();

                UnitChargeConfig::whereIn('unit_id', $unitIds)->delete();
                UnitActivity::whereIn('unit_id', $unitIds)->delete();
                Unit::whereIn('id', $unitIds)->delete();

                CommunityLedger::whereIn('community_id', $communityIds)->delete();
                $counts['communities'] = Community::whereIn('id', $communityIds)->delete();
            }

            // 6. Units (standalone — skipped if already deleted via communities)
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
                UserCommunity::whereIn('user_id', $userIds)->delete();
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
