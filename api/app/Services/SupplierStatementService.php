<?php

namespace App\Services;

use App\Models\Community;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Resend\Laravel\Facades\Resend;

/**
 * Builds the WeConnectU "Supplier Statements" — the per-community list (one row
 * per supplier with the balance as at "Date to"), the combined "View PDF"
 * summary, the per-supplier statement PDF (opening "Balance b/f" + transaction
 * ledger + ageing + Total Due) and the e-mail-statement flow.
 *
 * Every figure is read through {@see SupplierLedgerService} — the single source
 * of truth for the supplier (creditor) subledger — so a statement always agrees
 * with the Detailed Supplier Ledger and the Supplier Age Analysis.
 */
class SupplierStatementService extends BaseService
{
    protected SupplierLedgerService $ledger;

    public function __construct(SupplierLedgerService $ledger)
    {
        parent::__construct();
        $this->ledger = $ledger;
    }

    /**
     * Return the Supplier Statements table for a community — one row per supplier
     * (ordered by supplier code) with the ledger balance as at the "Date to".
     *
     * @param Community $community
     * @param array $data
     * @return array{rows: array, totals: array, date_to: string}
     */
    public function getStatements(Community $community, array $data): array
    {
        $dateTo       = ! empty($data['date_to']) ? $data['date_to'] : now()->toDateString();
        $hideZero     = filter_var($data['hide_zero'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hideNegative = filter_var($data['hide_negative'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $search       = trim((string) ($data['_search'] ?? ''));

        $query = Supplier::forCommunity($community->id);
        if ($search !== '') {
            $query->search($search);
        }
        $suppliers = $query->orderBy('supplier_code')->get();

        $aged = $this->ledger->agedBySupplier($suppliers->pluck('id')->all(), $dateTo, $community->id);

        $rows  = [];
        $total = 0.0;
        foreach ($suppliers as $supplier) {
            $balance = round((float) ($aged[$supplier->id]['balance'] ?? 0.0), 2);

            if ($hideZero && abs($balance) < 0.005) {
                continue;
            }
            if ($hideNegative && $balance < 0) {
                continue;
            }

            $rows[] = [
                'supplier_id'   => $supplier->id,
                'supplier_code' => $supplier->supplier_code,
                'supplier_name' => $supplier->name,
                'reference'     => $supplier->reference,
                'email'         => $supplier->email,
                'balance'       => $balance,
            ];
            $total += $balance;
        }

        return [
            'rows'    => $rows,
            'totals'  => ['balance' => round($total, 2)],
            'date_to' => $dateTo,
        ];
    }

    /**
     * Render the WeConnectU "View PDF" summary — a single document listing every
     * supplier with their reference and balance as at the date, plus a totals
     * row, sorted by supplier code (matches WeConnectU's "SuppliersStatement.pdf").
     *
     * @param Community $community
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewCombinedPdf(Community $community, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $listing = $this->getStatements($community, $data);
        $to      = $listing['date_to'];

        $organization    = $community->organization;
        $companyLogoPath = $organization?->logoFilePath();

        $pdf = Pdf::loadView('pdfs.supplier-statements-summary', [
            'community'       => $community,
            'organization'    => $organization,
            'companyLogoPath' => $companyLogoPath,
            'rows'            => $listing['rows'],
            'total'           => $listing['totals']['balance'] ?? 0.0,
            'dateLabel'       => Carbon::parse($to)->format('d F Y'),
        ])->setPaper('a4', 'landscape');

        // Enable inline PHP so the "Page X/Y" page-text script runs (DomPDF's
        // CSS counter(pages) resolves to 0).
        $pdf->getDomPDF()->getOptions()->setIsPhpEnabled(true);

        return $pdf->stream('SuppliersStatement.pdf');
    }

    /**
     * Download a single supplier's statement PDF.
     *
     * @param Community $community
     * @param Supplier $supplier
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadStatement(Community $community, Supplier $supplier, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $vars = $this->buildStatementPresentation($community, $supplier, $data['from'] ?? null, $data['to'] ?? null);

        $pdf = Pdf::loadView('pdfs.supplier-statement', $vars)->setPaper('a4', 'portrait');
        $pdf->getDomPDF()->getOptions()->setIsPhpEnabled(true);

        return $pdf->download('SupplierStatement-' . ($supplier->supplier_code ?: $supplier->id) . '.pdf');
    }

    /**
     * Assemble every presentation variable the supplier-statement blade needs:
     * header, supplier block, transaction ledger (opening "Balance b/f" + the
     * events in range with a running Cumulative), ageing buckets and Total Due.
     *
     * @param Community $community
     * @param Supplier $supplier
     * @param string|null $from
     * @param string|null $to
     * @return array<string, mixed>
     */
    public function buildStatementPresentation(Community $community, Supplier $supplier, ?string $from, ?string $to): array
    {
        $to     = $to ?: now()->toDateString();
        $events = $this->ledger->events($supplier->id, $community->id);

        // Opening = net (debit − credit) of every event strictly before "from".
        $opening = 0.0;
        if ($from) {
            $opening = (float) $events->filter(fn ($e) => ($e['date'] ?? '') < $from)
                ->sum(fn ($e) => $e['debit'] - $e['credit']);
        }

        $inRange = $events->filter(function ($e) use ($from, $to) {
            $d = $e['date'] ?? '';
            if ($from && $d < $from) {
                return false;
            }
            return $d <= $to;
        })->values();

        $cumulative = round($opening, 2);
        $rows       = [[
            'date'        => $from ?: ($inRange->first()['date'] ?? $to),
            'source'      => '',
            'description' => 'Balance b/f',
            'debit'       => $opening > 0 ? round($opening, 2) : 0.0,
            'credit'      => $opening < 0 ? round(-$opening, 2) : 0.0,
            'cumulative'  => $cumulative,
            'grv'         => null,
        ]];

        foreach ($inRange as $e) {
            $cumulative = round($cumulative + $e['debit'] - $e['credit'], 2);
            $rows[]     = [
                'date'        => $e['date'],
                'source'      => $e['source'],
                'description' => $e['description'],
                'debit'       => round((float) $e['debit'], 2),
                'credit'      => round((float) $e['credit'], 2),
                'cumulative'  => $cumulative,
                'grv'         => $e['grv'],
            ];
        }

        // Ageing as at "to". The subledger ages a net-credit (money-owed) supplier
        // as NEGATIVE amounts; a supplier statement shows the amount owed as a
        // positive figure per bucket with the signed balance in Total Due, so we
        // flip the sign for display (matches WeConnectU's SupplierStatement PDF).
        $aged   = $this->ledger->agedBySupplier([$supplier->id], $to, $community->id);
        $signed = $aged[$supplier->id]['buckets'] ?? array_fill_keys(SupplierLedgerService::BUCKETS, 0.0);
        $ageing = [
            '120_plus' => round(-(float) ($signed['120_plus'] ?? 0), 2),
            '90_days'  => round(-(float) ($signed['90_days'] ?? 0), 2),
            '60_days'  => round(-(float) ($signed['60_days'] ?? 0), 2),
            '30_days'  => round(-(float) ($signed['30_days'] ?? 0), 2),
            'current'  => round(-(float) ($signed['current'] ?? 0), 2),
        ];

        // Header + supplier presentation.
        $et          = $community->entity_type instanceof \BackedEnum ? $community->entity_type->value : $community->entity_type;
        $entityLabel = ($community->suppress_entity_type || ! $et) ? '' : ucwords(str_replace('_', ' ', (string) $et));

        $addressLines = collect([
            $supplier->address,
            $supplier->address_line_1,
            $supplier->address_line_2,
            $supplier->suburb,
            $supplier->town,
            $supplier->postal_code,
        ])->filter(fn ($l) => trim((string) $l) !== '')->values();

        $organization    = $community->organization;
        $companyLogoPath = $organization?->logoFilePath();

        return [
            'community'       => $community,
            'organization'    => $organization,
            'companyLogoPath' => $companyLogoPath,
            'supplier'        => $supplier,
            'supplierCode'    => $supplier->supplier_code,
            'entityLabel'     => $entityLabel,
            'addressLines'    => $addressLines,
            'statementDate'   => $to,
            'rows'            => $rows,
            'ageing'          => $ageing,
            'totalDue'        => $cumulative,
        ];
    }

    /**
     * E-mail a supplier's statement to the supplier's e-mail address (mirrors the
     * customer flow: suppressed/logged in local + test, sent via Resend otherwise).
     *
     * @param Community $community
     * @param Supplier $supplier
     * @param array $data
     * @return array
     */
    public function emailStatement(Community $community, Supplier $supplier, array $data): array
    {
        $to = $supplier->email;
        if (! $to) {
            return ['message' => 'No e-mail on file for this supplier.'];
        }

        $vars    = $this->buildStatementPresentation($community, $supplier, $data['from'] ?? null, $data['to'] ?? null);
        $subject = 'Statement — ' . ($supplier->supplier_code ?: $supplier->name);

        $money    = fn ($v) => number_format((float) $v, 2, '.', ' ');
        $headings = ['Date', 'Source', 'Description', 'Debit', 'Credit', 'Cumulative'];
        $rowsHtml = collect($vars['rows'])->map(function ($r) use ($money) {
            $cells = [
                $r['date'],
                $r['source'],
                $r['description'],
                $r['debit'] ? $money($r['debit']) : '',
                $r['credit'] ? $money($r['credit']) : '',
                $money($r['cumulative']),
            ];

            return '<tr>' . collect($cells)->map(fn ($c) => '<td style="padding:4px 8px;border:1px solid #ddd">' . e($c) . '</td>')->implode('') . '</tr>';
        })->implode('');

        $html = '<h3>' . e($subject) . '</h3><table style="border-collapse:collapse;font-family:sans-serif;font-size:13px"><thead><tr>'
            . collect($headings)->map(fn ($h) => '<th style="padding:4px 8px;border:1px solid #ddd;text-align:left">' . e($h) . '</th>')->implode('')
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';

        $fromAddr = config('mail.from.name') . ' <' . config('mail.from.address') . '>';
        if (app()->isLocal() || app()->runningUnitTests()) {
            Log::info("[local] Supplier statement email suppressed — would send to {$to}", ['supplier' => $supplier->supplier_code, 'subject' => $subject]);
        } else {
            Resend::emails()->send(['from' => $fromAddr, 'to' => [$to], 'subject' => $subject, 'html' => $html]);
        }

        return ['message' => 'Statement e-mailed to ' . $to];
    }
}
