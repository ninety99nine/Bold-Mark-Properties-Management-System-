<?php

namespace App\Http\Requests\Journal;

use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Models\JournalBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateJournalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', JournalBatch::class);
    }

    public function rules(): array
    {
        return [
            'community_id'   => ['required', 'uuid', 'exists:communities,id'],
            'financial_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'date'           => ['required', 'date'],
            'journal_group'  => ['nullable', 'string', 'in:' . implode(',', JournalBatch::GROUPS)],

            'lines'               => ['required', 'array', 'min:2'],
            'lines.*.line_type'   => ['required', 'string', 'in:' . implode(',', JournalLineType::values())],
            'lines.*.ledger_id'   => ['nullable', 'uuid', 'exists:ledgers,id'],
            'lines.*.unit_id'     => ['nullable', 'uuid', 'exists:units,id'],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.amount'      => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'lines.*.entry_type'  => ['required', 'string', 'in:' . implode(',', JournalEntryType::values())],

            'files'   => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
        ];
    }

    /**
     * Enforce WeConnectU's rules: every line needs the right account for its
     * type, and the batch must balance (total debits === total credits, > 0).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lines = $this->input('lines', []);

            $debit  = 0.0;
            $credit = 0.0;

            foreach ($lines as $i => $line) {
                $type = $line['line_type'] ?? null;

                if (in_array($type, [JournalLineType::GENERAL->value, JournalLineType::RESERVE_FUND->value], true)
                    && empty($line['ledger_id'])) {
                    $validator->errors()->add("lines.$i.ledger_id", 'Select an account for this line.');
                }

                if ($type === JournalLineType::CUSTOMER->value && empty($line['unit_id'])) {
                    $validator->errors()->add("lines.$i.unit_id", 'Select a customer for this line.');
                }

                $amount = (float) ($line['amount'] ?? 0);
                if (($line['entry_type'] ?? null) === JournalEntryType::DEBIT->value) {
                    $debit += $amount;
                } else {
                    $credit += $amount;
                }
            }

            if (round($debit, 2) <= 0) {
                $validator->errors()->add('lines', 'The batch must have a total debit and credit greater than zero.');
            } elseif (round($debit, 2) !== round($credit, 2)) {
                $validator->errors()->add('lines', 'Total debits must equal total credits (the difference must be 0.00).');
            }
        });
    }
}
