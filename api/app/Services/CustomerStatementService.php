<?php

namespace App\Services;

use App\Models\Community;
use Barryvdh\DomPDF\Facade\Pdf;

class CustomerStatementService extends BaseService
{
    protected AgeAnalysisService $ageService;

    public function __construct(AgeAnalysisService $ageService)
    {
        parent::__construct();
        $this->ageService = $ageService;
    }

    /**
     * Return the WeConnectU Customer Statements table for a community — one row
     * per customer with the account balance as at the "Date to". Reuses the age
     * analysis balance engine (ageing at date_to) so balances stay consistent.
     *
     * @param Community $community
     * @param array $data
     * @return array{rows: array, totals: array, date_to: string}
     */
    public function getStatements(Community $community, array $data): array
    {
        $dateTo = ! empty($data['date_to']) ? $data['date_to'] : now()->toDateString();

        $age = $this->ageService->getAgeAnalysis($community, [
            'ageing_date'   => $dateTo,
            'hide_zero'     => $data['hide_zero']     ?? null,
            'hide_negative' => $data['hide_negative'] ?? null,
            '_search'       => $data['_search']       ?? null,
        ]);

        $rows = array_map(fn (array $r) => [
            'unit_id'                 => $r['unit_id'],
            'unit_number'             => $r['unit_number'],
            'unit_no'                 => $r['unit_no'],
            'customer_code'           => $r['customer_code'],
            'customer_name'           => $r['customer_name'],
            'customer_email'          => $r['customer_email'] ?? null,
            'collection_status'       => $r['collection_status'],
            'collection_status_label' => $r['collection_status_label'],
            'transfer_active'         => $r['transfer_active'] ?? false,
            'debit_order'             => $r['debit_order'] ?? false,
            'is_sold'                 => $r['is_sold'] ?? false,
            'balance'                 => $r['balance'],
        ], $age['rows']);

        return [
            'rows'    => $rows,
            'totals'  => ['balance' => $age['totals']['balance'] ?? 0.0],
            'date_to' => $dateTo,
        ];
    }

    /**
     * Render the WeConnectU "View PDF" summary — a single portrait document
     * listing every customer with their reference (Unit No) and balance as at
     * the date, sorted alphabetically by customer code (matches WeConnectU's
     * "CustomersStatement.pdf").
     *
     * @param Community $community
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewCombinedPdf(Community $community, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $listing = $this->getStatements($community, $data);
        $to      = $listing['date_to'];

        $rows = $listing['rows'];
        usort($rows, fn ($a, $b) => strcmp((string) ($a['customer_code'] ?? ''), (string) ($b['customer_code'] ?? '')));

        // Match WeConnectU's header: community name + entity label ("… Body Corporate").
        $et          = $community->entity_type instanceof \BackedEnum ? $community->entity_type->value : $community->entity_type;
        $entityLabel = ($community->suppress_entity_type || ! $et) ? '' : ucwords(str_replace('_', ' ', (string) $et));

        $organization    = $community->organization;
        $companyLogoPath = $organization?->logoFilePath();

        $pdf = Pdf::loadView('pdfs.customer-statements-summary', [
            'community'       => $community,
            'entityLabel'     => $entityLabel,
            'organization'    => $organization,
            'companyLogoPath' => $companyLogoPath,
            'rows'            => $rows,
            'dateLabel'       => \Illuminate\Support\Carbon::parse($to)->format('d F Y'),
        ])->setPaper('a4', 'portrait');

        // Enable inline PHP so the "Page X/Y" page-text script runs (DomPDF's
        // CSS counter(pages) resolves to 0).
        $pdf->getDomPDF()->getOptions()->setIsPhpEnabled(true);

        return $pdf->stream('CustomersStatement.pdf');
    }
}
