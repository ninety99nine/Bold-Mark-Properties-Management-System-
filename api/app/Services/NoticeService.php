<?php

namespace App\Services;

use Exception;
use App\Enums\CashbookEntryType;
use App\Enums\CollectionStatus;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\NoticeBatch;
use App\Models\NoticeBatchItem;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitCollectionNote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Resend\Laravel\Facades\Resend;

/**
 * WeConnectU "Run Automatic Notices" engine.
 *
 * Escalates every overdue customer in scope one step along the collection
 * ladder (Reminder → 1st Notice → 2nd Notice → Final Notice → Letter of Demand),
 * generates a branded letter PDF for each, logs a collection note, optionally
 * emails the customer, and records the whole run as a Notice Batch so it can be
 * reviewed later via "View last notice batch".
 *
 * Terminal statuses (Handed Over, Payment Arrangement, Paid) are not auto-chased.
 */
class NoticeService extends BaseService
{
    /** Statuses that are never auto-escalated by a notice run. */
    private const TERMINAL = [
        CollectionStatus::HANDED_OVER,
        CollectionStatus::PAYMENT_ARRANGEMENT,
        CollectionStatus::PAID,
    ];

    /**
     * Preview the automatic-notice run (WeConnectU "Legal Notices" page).
     *
     * Every overdue, chaseable customer in scope grouped by the notice level
     * they are next due for, in collection-ladder order. Terminal statuses
     * (Handed Over, Payment Arrangement, Paid) are excluded.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function previewNotices(Community $community, array $data): array
    {
        $analysis = (new AgeAnalysisService())->getAgeAnalysis($community, $data);

        // Collection-ladder order for the on-screen sections.
        $order = [
            CollectionStatus::REMINDER->value,
            CollectionStatus::FIRST_NOTICE->value,
            CollectionStatus::SECOND_NOTICE->value,
            CollectionStatus::FINAL_NOTICE->value,
            CollectionStatus::LETTER_OF_DEMAND->value,
        ];

        // Balance below this is flagged (funnel icon) and excluded from the run
        // by default — WeConnectU "Balance below notice threshold".
        $threshold = (float) ($community->notice_threshold_amount ?? 0);

        $qualifying = [];
        foreach ($analysis['rows'] as $r) {
            $current   = CollectionStatus::from($r['collection_status']);
            $overdue   = $r['balance'] > 0.005;
            $chaseable = !in_array($current, self::TERMINAL, true);

            if ($overdue && $chaseable) {
                $qualifying[] = $r;
            }
        }

        // Owner contacts (all emails + phones) for the "Send to" column.
        $unitIds = array_column($qualifying, 'unit_id');
        $owners  = Unit::with('owner')
            ->whereIn('id', $unitIds)
            ->get()
            ->keyBy('id');

        $grouped = [];
        foreach ($qualifying as $r) {
            $current = CollectionStatus::from($r['collection_status']);
            $owner   = $owners->get($r['unit_id'])?->owner;

            $grouped[$current->next()->value][] = [
                'unit_id'         => $r['unit_id'],
                'customer_code'   => $r['customer_code'],
                'customer_name'   => $r['customer_name'],
                'emails'          => $this->ownerEmails($owner),
                'phones'          => $this->ownerPhones($owner),
                'balance'         => (float) $r['balance'],
                'customer_type'   => $r['person_role'],
                'below_threshold' => $threshold > 0 && $r['balance'] < $threshold,
            ];
        }

        $sections = [];
        foreach ($order as $level) {
            if (empty($grouped[$level])) {
                continue;
            }

            $status     = CollectionStatus::from($level);
            $sections[] = [
                'level' => $status->value,
                'title' => $this->sectionTitle($status),
                'rows'  => $grouped[$level],
            ];
        }

        return [
            'ageing_date' => $analysis['ageing_date'],
            'sections'    => $sections,
        ];
    }

    /**
     * Run automatic notices for the community's overdue customers.
     *
     * Honours the same filters as the age analysis, plus optional unit_ids
     * (a manual selection), send_email (global), and email_unit_ids (the
     * per-customer email selection from the Legal Notices page).
     *
     * @param Community $community
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function runNotices(Community $community, array $data): array
    {
        $user     = Auth::user();
        $analysis = (new AgeAnalysisService())->getAgeAnalysis($community, $data);

        $selected  = $data['unit_ids'] ?? null;
        $sendEmail = !empty($data['send_email']) && filter_var($data['send_email'], FILTER_VALIDATE_BOOLEAN);

        // Per-customer email selection from the Legal Notices page. When
        // present it takes precedence over the global send_email flag.
        $emailUnitIds = isset($data['email_unit_ids'])
            ? array_map('strval', (array) $data['email_unit_ids'])
            : null;

        $rows = array_filter($analysis['rows'], function ($r) use ($selected) {
            $overdue   = $r['balance'] > 0.005;
            $picked    = empty($selected) || in_array($r['unit_id'], $selected, true);
            $chaseable = !in_array(CollectionStatus::from($r['collection_status']), self::TERMINAL, true);

            return $overdue && $picked && $chaseable;
        });

        if (empty($rows)) {
            throw new Exception('No overdue customers to notice.');
        }

        $batch = NoticeBatch::create([
            'ageing_date'     => $analysis['ageing_date'],
            'community_id'    => $community->id,
            'organization_id' => $community->organization_id,
            'created_by_name' => $user?->name,
            'user_id'         => $user?->id,
            'total'           => 0,
        ]);

        $sent = 0;
        foreach ($rows as $row) {
            $unit = Unit::with(['owner', 'community'])->find($row['unit_id']);
            if (!$unit) {
                continue;
            }

            $current = $unit->collection_status instanceof CollectionStatus
                ? $unit->collection_status
                : CollectionStatus::from($unit->collection_status ?? 'none');

            $next       = $current->next();
            $levelTitle = $this->letterTitle($next);

            $unit->update(['collection_status' => $next->value]);

            $pdfContent = Pdf::loadView('pdfs.notices.notice', [
                'community'   => $unit->community,
                'unit'        => $unit,
                'owner'       => $unit->owner,
                'row'         => $row,
                'levelTitle'  => $levelTitle,
                'ageingDate'  => $analysis['ageing_date'],
            ])->setPaper('a4')->output();

            $path = "notices/{$batch->id}/{$unit->id}.pdf";
            Storage::disk('local')->put($path, $pdfContent);

            UnitCollectionNote::create([
                'unit_id'         => $unit->id,
                'organization_id' => $unit->organization_id,
                'note'            => $levelTitle . ' sent',
                'created_by_name' => $user?->name ?? 'System',
                'user_id'         => $user?->id,
            ]);

            $email       = $unit->owner?->email;
            $shouldEmail = $emailUnitIds !== null
                ? in_array((string) $unit->id, $emailUnitIds, true)
                : $sendEmail;
            $emailed = $shouldEmail && $email
                ? $this->emailNotice($unit, $email, $levelTitle, $pdfContent)
                : false;

            // "Sent to" (all emails then phones) + the notice charge for this level.
            $emails  = $this->ownerEmails($unit->owner);
            $phones  = $this->ownerPhones($unit->owner);
            $sentTo  = trim(implode(', ', $emails) . ($phones ? "\n" . implode(', ', $phones) : ''));

            NoticeBatchItem::create([
                'notice_batch_id' => $batch->id,
                'unit_id'         => $unit->id,
                'owner_id'        => $unit->owner?->id,
                'customer_code'   => $row['customer_code'],
                'customer_name'   => $row['customer_name'],
                'from_status'     => $current->value,
                'to_status'       => $next->value,
                'level'           => $levelTitle,
                'balance'         => $row['balance'],
                'charge'          => $this->noticeCharge($community, $next),
                'sent_to'         => $sentTo ?: null,
                'customer_type'   => $row['person_role'],
                'pdf_path'        => $path,
                'emailed'         => $emailed,
                'email'           => $email,
                'organization_id' => $unit->organization_id,
            ]);

            $sent++;
        }

        $batch->update(['total' => $sent]);

        return [
            'batch'   => $this->batchPayload($batch->fresh('items')),
            'message' => $sent . ($sent === 1 ? ' notice' : ' notices') . ' generated.',
        ];
    }

    /**
     * Show the most recent notice batch for the community.
     *
     * @param Community $community
     * @return array
     */
    public function showLastBatch(Community $community): array
    {
        $batch = NoticeBatch::where('community_id', $community->id)
            ->with('items')
            ->latest()
            ->first();

        return ['batch' => $batch ? $this->batchPayload($batch) : null];
    }

    /**
     * Show a specific notice batch.
     *
     * @param Community $community
     * @param NoticeBatch $noticeBatch
     * @return array
     */
    public function showBatch(Community $community, NoticeBatch $noticeBatch): array
    {
        return ['batch' => $this->batchPayload($noticeBatch->load('items'))];
    }

    /**
     * Download the letter PDF for a single batch item.
     *
     * @param Community $community
     * @param NoticeBatch $noticeBatch
     * @param NoticeBatchItem $noticeBatchItem
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function downloadItem(Community $community, NoticeBatch $noticeBatch, NoticeBatchItem $noticeBatchItem): \Symfony\Component\HttpFoundation\Response
    {
        if (!$noticeBatchItem->pdf_path || !Storage::disk('local')->exists($noticeBatchItem->pdf_path)) {
            throw new Exception('Notice document not found.');
        }

        $name = $noticeBatchItem->level . ' - ' . ($noticeBatchItem->customer_code ?? 'customer') . '.pdf';

        return Storage::disk('local')->download($noticeBatchItem->pdf_path, $name);
    }

    /**
     * Email a notice letter (with the PDF attached) to the customer.
     *
     * @param Unit $unit
     * @param string $email
     * @param string $levelTitle
     * @param string $pdfContent
     * @return bool
     */
    private function emailNotice(Unit $unit, string $email, string $levelTitle, string $pdfContent): bool
    {
        $html = view('emails.notice', [
            'community'  => $unit->community,
            'unit'       => $unit,
            'owner'      => $unit->owner,
            'levelTitle' => $levelTitle,
        ])->render();

        $from    = config('mail.from.name') . ' <' . config('mail.from.address') . '>';
        $subject = $levelTitle . ' — ' . ($unit->community?->name ?? 'Community');

        if (app()->isLocal()) {
            Log::info("[local] Notice email suppressed — would send to {$email}", [
                'unit'    => $unit->unit_number,
                'subject' => $subject,
            ]);

            return false;
        }

        Resend::emails()->send([
            'from'        => $from,
            'to'          => [$email],
            'subject'     => $subject,
            'html'        => $html,
            'attachments' => [[
                'filename' => $levelTitle . '.pdf',
                'content'  => base64_encode($pdfContent),
            ]],
        ]);

        return true;
    }

    /**
     * All of an owner's email addresses (primary + secondary + contacts), unique.
     *
     * @param Owner|null $owner
     * @return array<string>
     */
    private function ownerEmails(?Owner $owner): array
    {
        if (!$owner) {
            return [];
        }

        $emails = array_merge(
            [$owner->email],
            (array) ($owner->secondary_emails ?? []),
            [$owner->contact2_email ?? null, $owner->alt_email ?? null],
        );

        return array_values(array_unique(array_filter(array_map(fn ($e) => trim((string) $e), $emails))));
    }

    /**
     * All of an owner's phone numbers (primary + contacts), unique.
     *
     * @param Owner|null $owner
     * @return array<string>
     */
    private function ownerPhones(?Owner $owner): array
    {
        if (!$owner) {
            return [];
        }

        $phones = [$owner->phone ?? null, $owner->contact2_phone ?? null, $owner->alt_phone ?? null];

        return array_values(array_unique(array_filter(array_map(fn ($p) => trim((string) $p), $phones))));
    }

    /**
     * The Legal Notices section heading for a collection status.
     *
     * @param CollectionStatus $status
     * @return string
     */
    private function sectionTitle(CollectionStatus $status): string
    {
        return match ($status) {
            CollectionStatus::REMINDER         => 'Reminders',
            CollectionStatus::FIRST_NOTICE     => '1st Notices',
            CollectionStatus::SECOND_NOTICE    => '2nd Notices',
            CollectionStatus::FINAL_NOTICE     => 'Final Notices',
            CollectionStatus::LETTER_OF_DEMAND => 'Letters of Demand',
            default                            => $status->label() ?: 'Notices',
        };
    }

    /**
     * The human letter title for a collection status.
     *
     * @param CollectionStatus $status
     * @return string
     */
    private function letterTitle(CollectionStatus $status): string
    {
        return match ($status) {
            CollectionStatus::REMINDER         => 'Reminder',
            CollectionStatus::FIRST_NOTICE     => '1st Notice',
            CollectionStatus::SECOND_NOTICE    => '2nd Notice',
            CollectionStatus::FINAL_NOTICE     => 'Final Notice',
            CollectionStatus::LETTER_OF_DEMAND => 'Letter of Demand',
            default                            => $status->label() ?: 'Notice',
        };
    }

    /**
     * Serialise a notice batch for the WeConnectU "Legal Notices" view — items
     * grouped into sections by notice level, plus the run date and a link to the
     * previous batch.
     *
     * @param NoticeBatch $batch
     * @return array
     */
    private function batchPayload(NoticeBatch $batch): array
    {
        $order = ['Reminder', '1st Notice', '2nd Notice', 'Final Notice', 'Letter of Demand'];
        $titles = [
            'Reminder'         => 'Reminders',
            '1st Notice'       => '1st Notices',
            '2nd Notice'       => '2nd Notices',
            'Final Notice'     => 'Final Notices',
            'Letter of Demand' => 'Letters of Demand',
        ];

        $grouped  = $batch->items->groupBy('level');
        $sections = [];
        foreach ($order as $level) {
            $group = $grouped->get($level);
            if (!$group || $group->isEmpty()) {
                continue;
            }

            $sections[] = [
                'level' => $level,
                'title' => $titles[$level] ?? $level,
                'rows'  => $group->map(fn ($item) => [
                    'id'            => $item->id,
                    'unit_id'       => $item->unit_id,
                    'customer_code' => $item->customer_code,
                    'customer_name' => $item->customer_name,
                    'sent_to'       => $item->sent_to,
                    'charge'        => (float) $item->charge,
                    'balance'       => (float) $item->balance,
                    'customer_type' => $item->customer_type,
                    'credited'      => $item->credited_at !== null,
                ])->values(),
            ];
        }

        return [
            'id'                => $batch->id,
            'ageing_date'       => $batch->ageing_date?->toDateString(),
            'run_on'            => $batch->created_at?->toDateString(),
            'created_by_name'   => $batch->created_by_name,
            'total'             => $batch->total,
            'previous_batch_id' => $this->previousBatchId($batch),
            'sections'          => $sections,
        ];
    }

    /**
     * The id of the batch run immediately before this one, for "View previous notice".
     *
     * @param NoticeBatch $batch
     * @return string|null
     */
    private function previousBatchId(NoticeBatch $batch): ?string
    {
        return NoticeBatch::where('community_id', $batch->community_id)
            ->where('created_at', '<', $batch->created_at)
            ->latest()
            ->value('id');
    }

    /**
     * The notice charge for a collection-ladder level, from the community's
     * configured notice_charges matrix (email + sms charge).
     *
     * @param Community $community
     * @param CollectionStatus $level
     * @return float
     */
    private function noticeCharge(Community $community, CollectionStatus $level): float
    {
        $charges = (array) ($community->notice_charges ?? []);

        $key = match ($level) {
            CollectionStatus::FIRST_NOTICE     => 'first',
            CollectionStatus::SECOND_NOTICE    => 'second',
            CollectionStatus::FINAL_NOTICE     => 'second',
            CollectionStatus::LETTER_OF_DEMAND => 'letter_of_demand',
            default                            => 'first',
        };

        $cfg = (array) ($charges[$key] ?? []);

        return round((float) ($cfg['email_charge'] ?? 0) + (float) ($cfg['sms_charge'] ?? 0), 2);
    }

    /**
     * Create a credit note for a notice charge (WeConnectU "[CREDIT]" action).
     *
     * Records a credit against the customer's account for the notice charge,
     * dated either today or the notice run date, and marks the item credited.
     *
     * @param Community $community
     * @param NoticeBatch $noticeBatch
     * @param NoticeBatchItem $noticeBatchItem
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function creditNoticeItem(Community $community, NoticeBatch $noticeBatch, NoticeBatchItem $noticeBatchItem, array $data): array
    {
        if ($noticeBatchItem->credited_at) {
            throw new Exception('This notice charge has already been credited.');
        }

        $user = Auth::user();
        $date = ($data['date_option'] ?? 'invoice') === 'today'
            ? now()->toDateString()
            : ($noticeBatch->created_at?->toDateString() ?? now()->toDateString());

        CashbookEntry::create([
            'community_id'      => $community->id,
            'organization_id'   => $community->organization_id,
            'unit_id'           => $noticeBatchItem->unit_id,
            'invoice_id'        => $noticeBatchItem->invoice_id,
            'type'              => CashbookEntryType::CREDIT->value,
            'date'              => $date,
            'amount'            => (float) $noticeBatchItem->charge,
            'description'       => 'Credit note — ' . $noticeBatchItem->level . ' charge',
            'notes'             => $data['reason'] ?? null,
            'allocated_by_name' => $user?->name,
            'allocated_at'      => now(),
        ]);

        $noticeBatchItem->update(['credited_at' => now()]);

        return ['message' => 'Credit note created.'];
    }
}
