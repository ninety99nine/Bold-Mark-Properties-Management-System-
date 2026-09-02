<?php

namespace App\Jobs;

use App\Models\CashbookEntry;
use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use App\Models\ComplianceItemAttachment;
use App\Models\Community;
use App\Models\CommunityLedger;
use App\Models\FlushJob;
use App\Models\Invoice;
use App\Models\InvoiceEmailEvent;
use App\Models\Owner;
use App\Models\TableView;
use App\Models\Occupant;
use App\Models\Unit;
use App\Models\UnitActivity;
use App\Models\UnitChargeConfig;
use App\Models\User;
use App\Models\UserCommunity;
use App\Models\UserLoginLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class FlushOrganizationJob implements ShouldQueue
{
    use Queueable;

    // Step definitions in FK-safe deletion order
    private const STEP_ORDER = [
        'invoices'         => 'Invoices',
        'cashbook_entries' => 'Cashbook Entries',
        'occupants'          => 'Occupants',
        'owners'           => 'Owners',
        'communities'          => 'Communities & Compliance',
        'units'            => 'Units',
        'users'            => 'Users',
    ];

    public function __construct(
        private string $flushJobId,
        private string $organizationId,
        private array  $targets,
        private array  $keepUserIds,
    ) {}

    public function handle(): void
    {
        $record = FlushJob::find($this->flushJobId);
        if (!$record) return;

        $record->update(['status' => 'running']);

        $steps = $record->steps;

        foreach ($steps as &$step) {
            $step['status'] = 'running';
            $record->steps  = $steps;
            $record->save();

            $count = $this->processStep($step['id']);

            $step['status'] = 'completed';
            $step['count']  = $count;
            $record->steps  = $steps;
            $record->save();
        }

        $record->update(['status' => 'completed']);
    }

    public function failed(Throwable $e): void
    {
        $record = FlushJob::find($this->flushJobId);
        if (!$record) return;

        $steps = $record->steps;
        foreach ($steps as &$step) {
            if ($step['status'] === 'running') {
                $step['status'] = 'failed';
            }
        }

        $record->update([
            'status' => 'failed',
            'steps'  => $steps,
            'error'  => $e->getMessage(),
        ]);
    }

    private function processStep(string $target): int
    {
        $orgId = $this->organizationId;

        return match ($target) {
            'invoices' => $this->deleteInvoices($orgId),
            'cashbook_entries' => CashbookEntry::where('organization_id', $orgId)->delete(),
            'occupants' => Occupant::where('organization_id', $orgId)->delete(),
            'owners' => Owner::where('organization_id', $orgId)->delete(),
            'communities' => $this->deleteCommunities($orgId),
            'units' => $this->deleteUnits($orgId),
            'users' => $this->deleteUsers($orgId),
            default => 0,
        };
    }

    private function deleteInvoices(string $orgId): int
    {
        $invoiceIds = Invoice::where('organization_id', $orgId)->pluck('id');
        InvoiceEmailEvent::whereIn('invoice_id', $invoiceIds)->delete();
        return Invoice::where('organization_id', $orgId)->delete();
    }

    private function deleteCommunities(string $orgId): int
    {
        $communityIds    = Community::where('organization_id', $orgId)->pluck('id');
        $unitIds      = Unit::where('organization_id', $orgId)->whereIn('community_id', $communityIds)->pluck('id');
        $checklistIds = ComplianceChecklist::where('organization_id', $orgId)->whereIn('community_id', $communityIds)->pluck('id');
        $itemIds      = ComplianceChecklistItem::whereIn('compliance_checklist_id', $checklistIds)->pluck('id');

        ComplianceItemAttachment::whereIn('compliance_checklist_item_id', $itemIds)->delete();
        ComplianceChecklistItem::whereIn('id', $itemIds)->delete();
        ComplianceChecklist::whereIn('id', $checklistIds)->delete();
        UnitChargeConfig::whereIn('unit_id', $unitIds)->delete();
        UnitActivity::whereIn('unit_id', $unitIds)->delete();
        Unit::whereIn('id', $unitIds)->delete();
        CommunityLedger::whereIn('community_id', $communityIds)->delete();

        return Community::whereIn('id', $communityIds)->delete();
    }

    private function deleteUnits(string $orgId): int
    {
        $unitIds = Unit::where('organization_id', $orgId)->pluck('id');
        UnitChargeConfig::whereIn('unit_id', $unitIds)->delete();
        UnitActivity::whereIn('unit_id', $unitIds)->delete();
        return Unit::where('organization_id', $orgId)->delete();
    }

    private function deleteUsers(string $orgId): int
    {
        $userIds = User::where('organization_id', $orgId)
            ->whereNotIn('id', $this->keepUserIds)
            ->pluck('id');

        UserLoginLog::whereIn('user_id', $userIds)->delete();
        UserCommunity::whereIn('user_id', $userIds)->delete();
        TableView::whereIn('user_id', $userIds)->delete();

        return User::whereIn('id', $userIds)->delete();
    }

    /**
     * Build an ordered steps array from the selected targets.
     */
    public static function buildSteps(array $targets): array
    {
        $steps = [];
        foreach (self::STEP_ORDER as $id => $label) {
            if (in_array($id, $targets)) {
                $steps[] = ['id' => $id, 'label' => $label, 'status' => 'pending', 'count' => null];
            }
        }
        return $steps;
    }
}
