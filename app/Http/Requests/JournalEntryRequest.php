<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class JournalEntryRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        $lines = array_values(array_filter((array) $this->input('lines', []), fn (array $line): bool => filled($line['account_id'] ?? null) || filled($line['debit'] ?? null) || filled($line['credit'] ?? null) || filled($line['description'] ?? null)));
        $this->merge(['reference' => filled($this->input('reference')) ? trim((string) $this->input('reference')) : null, 'description' => trim((string) $this->input('description')), 'lines' => $lines]);
    }

    public function rules(): array { return ['transaction_date' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:100'], 'description' => ['required', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:2'], 'lines.*.account_id' => ['required', 'integer', 'exists:accounting_accounts,id'], 'lines.*.description' => ['nullable', 'string', 'max:1000'], 'lines.*.debit' => ['nullable', 'decimal:0,2', 'gte:0'], 'lines.*.credit' => ['nullable', 'decimal:0,2', 'gte:0']]; }
    public function after(): array { return [function (Validator $validator): void { $debits = 0; $credits = 0; foreach ((array) $this->input('lines', []) as $index => $line) { $debit = $this->cents($line['debit'] ?? 0); $credit = $this->cents($line['credit'] ?? 0); if (($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0)) $validator->errors()->add("lines.$index", 'Each line needs either one positive debit or one positive credit amount.'); $debits += $debit; $credits += $credit; } if ($debits !== $credits) $validator->errors()->add('lines', 'Total debits must equal total credits before the journal can be saved.'); }]; }
    private function cents(mixed $amount): int { $value = trim((string) $amount); if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) return 0; return ((int) $m[1] * 100) + (int) str_pad($m[2] ?? '', 2, '0'); }
}
