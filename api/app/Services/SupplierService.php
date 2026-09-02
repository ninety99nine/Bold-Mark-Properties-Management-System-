<?php

namespace App\Services;

use Exception;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Enums\SupplierAccountType;
use App\Enums\SupplierBank;
use App\Enums\SupplierPaymentType;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Exports\SupplierExport;
use App\Exports\SupplierUploadTemplateExport;
use App\Imports\SupplierImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\SupplierResources;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupplierService extends BaseService
{
    /**
     * Return a paginated, filtered list of suppliers for the authenticated
     * user's organization. Optionally scoped to a single community.
     *
     * Filters:
     *   community_id      → community-specific + org-shared suppliers (forCommunity scope)
     *   supplier_group_id → single group
     *   status            → verification status
     *   is_active         → true/false (defaults to active only when omitted)
     *   search            → matches name, supplier_code, reference, account_number, email
     *
     * @param array $data
     * @return SupplierResources|array
     */
    public function showSuppliers(array $data): SupplierResources|array
    {
        $query = $this->buildSupplierQuery($data);

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Return the option sets required to render the Add/Edit supplier form and
     * the Supplier Groups tab, scoped to the authenticated user's organization
     * (and optionally a community for the groups list).
     *
     * @param string|null $communityId
     * @return array
     */
    public function supplierOptions(?string $communityId): array
    {
        $user = Auth::user();

        $groupsQuery = \App\Models\SupplierGroup::where('organization_id', $user->organization_id);

        if (!empty($communityId)) {
            $groupsQuery->forCommunity($communityId);
        }

        $groups = $groupsQuery->orderBy('name')->get(['id', 'name'])
            ->map(fn ($g): array => ['id' => $g->id, 'name' => $g->name])
            ->all();

        return [
            'supplier_types' => SupplierType::options(),
            'payment_types'  => SupplierPaymentType::options(),
            'account_types'  => SupplierAccountType::options(),
            'banks'          => SupplierBank::options(),
            'statuses'       => SupplierStatus::options(),
            'groups'         => $groups,
        ];
    }

    /**
     * Create a supplier for the authenticated user's organization.
     * Auto-generates a supplier_code when one is not supplied.
     *
     * @param array $data
     * @return array
     */
    public function createSupplier(array $data): array
    {
        $user = Auth::user();

        if (empty($data['supplier_code'])) {
            $data['supplier_code'] = $this->generateSupplierCode($data['name'], $user->organization_id);
        }

        $data['organization_id'] = $user->organization_id;

        $supplier = Supplier::create($data);

        return $this->showCreatedResource($supplier);
    }

    /**
     * Import suppliers from an uploaded WeConnectU-format spreadsheet. Upserts
     * by Code within the organization (and optional community): existing codes
     * are updated, missing/blank codes are created (auto-generating a code when
     * blank). Bank Name and Account Type are mapped onto their enums by label.
     *
     * @param UploadedFile $file
     * @param string|null $communityId
     * @return array{created:int,updated:int,errors:array<array{row:int,message:string}>}
     */
    public function importSuppliers(UploadedFile $file, ?string $communityId): array
    {
        $user   = Auth::user();
        $sheets = Excel::toArray(new SupplierImport(), $file);
        $rows   = $sheets[0] ?? [];

        $created = 0;
        $updated = 0;
        $errors  = [];

        foreach ($rows as $index => $row) {
            // Row 1 (index 0) is the heading row.
            if ($index === 0) {
                continue;
            }

            // Skip fully blank rows.
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $rowNumber = $index + 1;
            $name      = trim((string) ($row[1] ?? ''));

            if ($name === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Supplier Name is required.'];
                continue;
            }

            $code       = trim((string) ($row[0] ?? ''));
            $bankName   = $this->matchBankLabel(trim((string) ($row[4] ?? '')));
            $accountType = $this->matchAccountType(trim((string) ($row[5] ?? '')));

            $attributes = [
                'name'            => $name,
                'account_number'  => trim((string) ($row[2] ?? '')) ?: null,
                'branch_code'     => trim((string) ($row[3] ?? '')) ?: null,
                'bank_name'       => $bankName,
                'account_type'    => $accountType,
                'email'           => trim((string) ($row[6] ?? '')) ?: null,
                'phone'           => trim((string) ($row[7] ?? '')) ?: null,
                'vat_number'      => trim((string) ($row[8] ?? '')) ?: null,
            ];

            $existing = null;
            if ($code !== '') {
                $existing = Supplier::where('organization_id', $user->organization_id)
                    ->where('supplier_code', $code)
                    ->first();
            }

            if ($existing) {
                $existing->update($attributes);
                $updated++;
                continue;
            }

            $attributes['supplier_code']   = $code !== ''
                ? $code
                : $this->generateSupplierCode($name, $user->organization_id);
            $attributes['organization_id'] = $user->organization_id;
            $attributes['community_id']    = $communityId ?: null;

            Supplier::create($attributes);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Bulk delete suppliers by an array of IDs.
     *
     * @param array $supplierIds
     * @return array
     * @throws Exception
     */
    public function deleteSuppliers(array $supplierIds): array
    {
        $user      = Auth::user();
        $suppliers = Supplier::whereIn('id', $supplierIds)
            ->where('organization_id', $user->organization_id)
            ->get();

        $total = $suppliers->count();

        if ($total === 0) {
            throw new Exception('No Suppliers deleted');
        }

        foreach ($suppliers as $supplier) {
            $this->deleteSupplier($supplier);
        }

        $label = $total === 1 ? 'Supplier' : 'Suppliers';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Stream the filtered supplier list as an xlsx download (Export Suppliers).
     *
     * @param array $filters
     * @return BinaryFileResponse
     */
    public function exportSuppliers(array $filters): BinaryFileResponse
    {
        $suppliers = $this->buildSupplierQuery($filters)->orderBy('name')->get();

        return Excel::download(new SupplierExport($suppliers), 'supplier export-.xlsx');
    }

    /**
     * Stream the blank supplier-upload template as an xlsx download.
     *
     * @return BinaryFileResponse
     */
    public function downloadUploadTemplate(): BinaryFileResponse
    {
        return Excel::download(new SupplierUploadTemplateExport(), 'supplier upload template-.xlsx');
    }

    /**
     * Return a single supplier resource.
     *
     * @param Supplier $supplier
     * @return SupplierResource
     */
    public function showSupplier(Supplier $supplier): SupplierResource
    {
        return $this->showResource($supplier);
    }

    /**
     * Update a supplier's details.
     *
     * @param Supplier $supplier
     * @param array $data
     * @return array
     */
    public function updateSupplier(Supplier $supplier, array $data): array
    {
        $supplier->update($data);

        return $this->showUpdatedResource($supplier);
    }

    /**
     * Delete a single supplier.
     *
     * @param Supplier $supplier
     * @return array
     */
    public function deleteSupplier(Supplier $supplier): array
    {
        $deleted = $supplier->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Supplier deleted' : 'Supplier delete unsuccessful',
        ];
    }

    /**
     * Return the documents attached to a supplier.
     *
     * @param Supplier $supplier
     * @return array
     */
    public function showDocuments(Supplier $supplier): array
    {
        $documents = $supplier->documents()->latest()->get()->map(fn (SupplierDocument $doc): array => [
            'id'         => $doc->id,
            'name'       => $doc->name,
            'url'        => Storage::disk('public')->url($doc->path),
            'created_at' => $doc->created_at?->toDateTimeString(),
        ])->all();

        return ['data' => $documents];
    }

    /**
     * Upload and attach a document to a supplier.
     *
     * @param Supplier $supplier
     * @param array{name:?string,file:UploadedFile} $data
     * @return array
     */
    public function uploadDocument(Supplier $supplier, array $data): array
    {
        $user = Auth::user();

        /** @var UploadedFile $file */
        $file = $data['file'];

        $path = $file->store("suppliers/documents/{$user->organization_id}", 'public');

        $document = SupplierDocument::create([
            'name'            => $data['name'] ?? $file->getClientOriginalName(),
            'path'            => $path,
            'supplier_id'     => $supplier->id,
            'organization_id' => $user->organization_id,
        ]);

        return [
            'data'    => [
                'id'         => $document->id,
                'name'       => $document->name,
                'url'        => Storage::disk('public')->url($document->path),
                'created_at' => $document->created_at?->toDateTimeString(),
            ],
            'message' => 'Document uploaded',
        ];
    }

    /**
     * Delete a supplier document (and its stored file).
     *
     * @param SupplierDocument $supplierDocument
     * @return array
     */
    public function deleteDocument(SupplierDocument $supplierDocument): array
    {
        if ($supplierDocument->path) {
            Storage::disk('public')->delete($supplierDocument->path);
        }

        $deleted = $supplierDocument->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Document deleted' : 'Document delete unsuccessful',
        ];
    }

    /**
     * Build the base (filtered, unsorted) supplier query for the authenticated
     * user's organization. Shared by list and export.
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildSupplierQuery(array $data): \Illuminate\Database\Eloquent\Builder
    {
        $user  = Auth::user();
        $query = Supplier::where('organization_id', $user->organization_id);

        if (!empty($data['community_id'])) {
            $query->forCommunity($data['community_id']);
        }

        if (!empty($data['supplier_group_id'])) {
            $query->where('supplier_group_id', $data['supplier_group_id']);
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (array_key_exists('is_active', $data)) {
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        } else {
            $query->active();
        }

        if (!empty($data['search'])) {
            $query->search($data['search']);
        }

        return $query;
    }

    /**
     * Match a free-text bank name against the SupplierBank labels (case-insensitive).
     * Returns the canonical label, or the original trimmed string when non-empty
     * but unmatched, or null when blank.
     *
     * @param string $value
     * @return string|null
     */
    protected function matchBankLabel(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        foreach (SupplierBank::cases() as $case) {
            if (strcasecmp($case->value, $value) === 0) {
                return $case->value;
            }
        }

        return $value;
    }

    /**
     * Match a free-text account type against the SupplierAccountType labels
     * (case-insensitive). Returns the enum value, or null when blank/unmatched.
     *
     * @param string $value
     * @return string|null
     */
    protected function matchAccountType(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        foreach (SupplierAccountType::cases() as $case) {
            if (strcasecmp($case->label(), $value) === 0 || strcasecmp($case->value, $value) === 0) {
                return $case->value;
            }
        }

        return null;
    }

    /**
     * Generate a unique supplier code for an organization.
     * First 3 letters of the name uppercased + zero-padded sequence (e.g. "ACC001").
     *
     * @param string $name
     * @param string $organizationId
     * @return string
     */
    protected function generateSupplierCode(string $name, string $organizationId): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) . 'XXX', 0, 3));

        $sequence = Supplier::where('organization_id', $organizationId)
            ->where('supplier_code', 'like', $prefix . '%')
            ->count();

        do {
            $sequence++;
            $code = $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        } while (
            Supplier::where('organization_id', $organizationId)
                ->where('supplier_code', $code)
                ->exists()
        );

        return $code;
    }
}
