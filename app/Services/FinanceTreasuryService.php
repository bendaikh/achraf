<?php

namespace App\Services;

use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\FinancialMovement;
use App\Models\FinanceOpeningBalance;
use App\Models\FinancePeriod;
use App\Support\FinanceSettings;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class FinanceTreasuryService
{
    public function __construct(
        protected FinancialMovementService $movements
    ) {}

    /**
     * Solde = solde d'ouverture (cutoff) + mouvements après cutoff jusqu'à asOf.
     */
    public function accountBalance(string $account, ?string $asOfDate = null): float
    {
        $asOf = Carbon::parse($asOfDate ?: now()->toDateString())->toDateString();
        $cutoff = FinanceSettings::cutoffDateString();

        $opening = (float) FinanceOpeningBalance::query()
            ->where('account', $account)
            ->whereDate('as_of_date', $cutoff)
            ->value('amount');

        if ($asOf < $cutoff) {
            // Before official finance go-live: movement-only legacy view.
            return $this->movements->accountBalance($account, $asOf);
        }

        $delta = (float) FinancialMovement::query()
            ->where('account', $account)
            ->where('status', '!=', FinancialMovement::STATUS_BROUILLON)
            ->whereDate('movement_date', '>=', $cutoff)
            ->whereDate('movement_date', '<=', $asOf)
            ->where('is_opening_balance', false)
            ->selectRaw('COALESCE(SUM(amount_in),0) - COALESCE(SUM(amount_out),0) as bal')
            ->value('bal');

        return round($opening + $delta, 2);
    }

    /**
     * Upsert opening balances for the official cutoff (never counts as CA).
     *
     * @param  array<string, float>  $balances  account => amount
     */
    public function saveOpeningBalances(array $balances, ?string $notes = null): void
    {
        $cutoff = FinanceSettings::cutoffDateString();

        DB::transaction(function () use ($balances, $cutoff, $notes) {
            foreach ($balances as $account => $amount) {
                FinanceOpeningBalance::query()->updateOrCreate(
                    ['account' => $account, 'as_of_date' => $cutoff],
                    [
                        'amount' => round((float) $amount, 2),
                        'currency' => 'MAD',
                        'notes' => $notes,
                        'created_by' => Auth::id(),
                    ]
                );
            }
        });
    }

    public function closePeriod(string $start, string $end, string $reason): FinancePeriod
    {
        return DB::transaction(function () use ($start, $end, $reason) {
            $period = FinancePeriod::query()->firstOrNew([
                'period_start' => $start,
                'period_end' => $end,
            ]);

            if ($period->exists && $period->isClosed()) {
                throw new RuntimeException('Cette période est déjà clôturée.');
            }

            $period->fill([
                'status' => FinancePeriod::STATUS_CLOSED,
                'close_reason' => $reason,
                'closed_by' => Auth::id(),
                'closed_at' => now(),
                'reopen_reason' => null,
                'reopened_by' => null,
                'reopened_at' => null,
            ])->save();

            return $period->fresh();
        });
    }

    public function reopenPeriod(FinancePeriod $period, string $reason): FinancePeriod
    {
        if (! $period->isClosed()) {
            throw new RuntimeException('La période n’est pas clôturée.');
        }

        $period->update([
            'status' => FinancePeriod::STATUS_OPEN,
            'reopen_reason' => $reason,
            'reopened_by' => Auth::id(),
            'reopened_at' => now(),
        ]);

        return $period->fresh();
    }

    /**
     * Create a treasury adjustment (neither sale nor expense).
     */
    public function createAdjustment(array $data): FinancialMovement
    {
        $amount = abs((float) ($data['amount'] ?? 0));
        if ($amount <= 0) {
            throw new RuntimeException('Montant d’ajustement invalide.');
        }

        $direction = ($data['direction'] ?? '+') === '-' ? '-' : '+';

        return $this->movements->createManual([
            'movement_date' => $data['date'] ?? now()->toDateString(),
            'origin' => FinancialMovement::ORIGIN_AJUSTEMENT,
            'type' => $direction === '+' ? FinancialMovement::TYPE_ENTREE : FinancialMovement::TYPE_SORTIE,
            'label' => 'Ajustement trésorerie — '.($data['motif'] ?? 'Correction'),
            'account' => $data['account'] ?? FinancialMovement::ACCOUNT_BANQUE,
            'amount_in' => $direction === '+' ? $amount : 0,
            'amount_out' => $direction === '-' ? $amount : 0,
            'notes' => trim(($data['motif'] ?? '')."\n".($data['commentaire'] ?? '')),
            'justificatif_path' => $data['justificatif_path'] ?? null,
        ], Auth::id());
    }

    public function importBankStatement(UploadedFile $file, string $account = 'banque'): BankStatementImport
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt', 'xls', 'xlsx'], true)) {
            throw new RuntimeException('Format non supporté. Utilisez CSV, TXT, XLS ou XLSX.');
        }

        $path = $file->store('bank-statements/'.now()->format('Y/m'));
        $rows = $this->parseStatementFile(Storage::path($path), $ext);

        return DB::transaction(function () use ($file, $path, $account, $rows) {
            $import = BankStatementImport::create([
                'account' => $account,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'status' => 'imported',
                'lines_count' => count($rows),
                'imported_by' => Auth::id(),
            ]);

            foreach ($rows as $row) {
                $line = BankStatementLine::create([
                    'bank_statement_import_id' => $import->id,
                    'operation_date' => $row['operation_date'],
                    'value_date' => $row['value_date'],
                    'label' => $row['label'],
                    'reference' => $row['reference'],
                    'debit' => $row['debit'],
                    'credit' => $row['credit'],
                    'balance' => $row['balance'],
                    'status' => BankStatementLine::STATUS_UNMATCHED,
                ]);

                $this->suggestMatch($line, $account);
            }

            return $import->fresh('lines');
        });
    }

    public function matchLine(BankStatementLine $line, FinancialMovement $movement): BankStatementLine
    {
        return DB::transaction(function () use ($line, $movement) {
            // Never recreate a movement — only point the existing one.
            if ($movement->status !== FinancialMovement::STATUS_POINTE) {
                $movement->update([
                    'status' => FinancialMovement::STATUS_POINTE,
                    'pointed_at' => now(),
                    'pointed_by' => Auth::id(),
                ]);
            }

            $line->update([
                'status' => BankStatementLine::STATUS_MATCHED,
                'financial_movement_id' => $movement->id,
            ]);

            return $line->fresh('movement');
        });
    }

    protected function suggestMatch(BankStatementLine $line, string $account): void
    {
        $amountIn = (float) $line->credit;
        $amountOut = (float) $line->debit;
        $date = $line->operation_date?->toDateString();
        if (! $date) {
            return;
        }

        $candidates = FinancialMovement::query()
            ->where('account', $account)
            ->whereDate('movement_date', $date)
            ->where('status', '!=', FinancialMovement::STATUS_POINTE)
            ->where('is_opening_balance', false)
            ->get()
            ->filter(function (FinancialMovement $m) use ($amountIn, $amountOut) {
                if ($amountIn > 0) {
                    return abs((float) $m->amount_in - $amountIn) < 0.02;
                }
                if ($amountOut > 0) {
                    return abs((float) $m->amount_out - $amountOut) < 0.02;
                }

                return false;
            });

        if ($candidates->count() === 1) {
            $line->update([
                'status' => BankStatementLine::STATUS_SUGGESTED,
                'financial_movement_id' => $candidates->first()->id,
            ]);
        }
    }

    /**
     * @return list<array{operation_date:?string,value_date:?string,label:?string,reference:?string,debit:float,credit:float,balance:?float}>
     */
    protected function parseStatementFile(string $absolutePath, string $ext): array
    {
        if (in_array($ext, ['xls', 'xlsx'], true)) {
            $sheet = IOFactory::load($absolutePath)->getActiveSheet()->toArray(null, true, true, false);

            return $this->rowsFromMatrix($sheet);
        }

        $content = file_get_contents($absolutePath) ?: '';
        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $matrix = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
            $matrix[] = str_getcsv($line, $delimiter);
        }

        return $this->rowsFromMatrix($matrix);
    }

    /**
     * @param  list<list<mixed>>  $matrix
     * @return list<array{operation_date:?string,value_date:?string,label:?string,reference:?string,debit:float,credit:float,balance:?float}>
     */
    protected function rowsFromMatrix(array $matrix): array
    {
        if ($matrix === []) {
            return [];
        }

        $headerIndex = $this->detectHeaderRow($matrix);
        $header = array_map(fn ($v) => $this->normalizeHeader((string) $v), $matrix[$headerIndex] ?? []);
        $map = $this->mapColumns($header);

        $rows = [];
        for ($i = $headerIndex + 1; $i < count($matrix); $i++) {
            $raw = $matrix[$i];
            if (! is_array($raw) || $this->rowIsEmpty($raw)) {
                continue;
            }
            if ($this->rowLooksLikeTotal($raw)) {
                continue;
            }

            $debit = $this->toFloat($raw[$map['debit']] ?? null);
            $credit = $this->toFloat($raw[$map['credit']] ?? null);
            if ($debit <= 0 && $credit <= 0 && isset($map['amount'])) {
                $amount = $this->toFloat($raw[$map['amount']] ?? null);
                if ($amount < 0) {
                    $debit = abs($amount);
                } else {
                    $credit = abs($amount);
                }
            }

            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $rows[] = [
                'operation_date' => $this->toDate(isset($map['date']) ? ($raw[$map['date']] ?? null) : null),
                'value_date' => $this->toDate(
                    isset($map['value_date'])
                        ? ($raw[$map['value_date']] ?? null)
                        : (isset($map['date']) ? ($raw[$map['date']] ?? null) : null)
                ),
                'label' => isset($map['label']) ? trim((string) ($raw[$map['label']] ?? '')) : null,
                'reference' => isset($map['reference']) ? trim((string) ($raw[$map['reference']] ?? '')) : null,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => isset($map['balance']) ? $this->toFloat($raw[$map['balance']] ?? null) : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<list<mixed>>  $matrix
     */
    protected function detectHeaderRow(array $matrix): int
    {
        $limit = min(15, count($matrix));
        for ($i = 0; $i < $limit; $i++) {
            $joined = strtolower(implode(' ', array_map(fn ($v) => (string) $v, $matrix[$i] ?? [])));
            if (
                str_contains($joined, 'date')
                && (str_contains($joined, 'debit') || str_contains($joined, 'débit') || str_contains($joined, 'credit') || str_contains($joined, 'crédit') || str_contains($joined, 'montant'))
            ) {
                return $i;
            }
        }

        return 0;
    }

    protected function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['é', 'è', 'ê', 'à'], ['e', 'e', 'e', 'a'], $value);

        return $value;
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    protected function mapColumns(array $header): array
    {
        $map = [];
        foreach ($header as $i => $col) {
            if ($col === '') {
                continue;
            }
            if (! isset($map['date']) && (str_contains($col, 'date operation') || $col === 'date' || str_contains($col, 'date op'))) {
                $map['date'] = $i;
            } elseif (! isset($map['value_date']) && (str_contains($col, 'date valeur') || str_contains($col, 'valeur'))) {
                $map['value_date'] = $i;
            } elseif (! isset($map['label']) && (str_contains($col, 'libelle') || str_contains($col, 'label') || str_contains($col, 'description'))) {
                $map['label'] = $i;
            } elseif (! isset($map['reference']) && (str_contains($col, 'ref') || str_contains($col, 'reference'))) {
                $map['reference'] = $i;
            } elseif (! isset($map['debit']) && (str_contains($col, 'debit') || str_contains($col, 'sortie'))) {
                $map['debit'] = $i;
            } elseif (! isset($map['credit']) && (str_contains($col, 'credit') || str_contains($col, 'entree'))) {
                $map['credit'] = $i;
            } elseif (! isset($map['amount']) && str_contains($col, 'montant')) {
                $map['amount'] = $i;
            } elseif (! isset($map['balance']) && (str_contains($col, 'solde') || str_contains($col, 'balance'))) {
                $map['balance'] = $i;
            }
        }

        return $map;
    }

    protected function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }
        $raw = str_replace([' ', "\xc2\xa0"], '', (string) $value);
        $raw = str_replace(',', '.', $raw);
        $raw = preg_replace('/[^0-9.\-]/', '', $raw) ?: '0';

        return round((float) $raw, 2);
    }

    protected function toDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value))->toDateString();
            }

            $raw = trim((string) $value);
            // Prefer European d/m/Y when ambiguous (bank statements in FR/MA).
            if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $raw, $m)) {
                $day = (int) $m[1];
                $month = (int) $m[2];
                $year = (int) $m[3];
                if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31 && checkdate($month, $day, $year)) {
                    return Carbon::create($year, $month, $day)->toDateString();
                }
            }

            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function rowLooksLikeTotal(array $row): bool
    {
        $joined = strtolower(implode(' ', array_map(fn ($v) => (string) $v, $row)));

        return str_contains($joined, 'total') || str_contains($joined, 'solde final');
    }
}
