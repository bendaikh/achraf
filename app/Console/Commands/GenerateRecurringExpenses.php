<?php

namespace App\Console\Commands;

use App\Services\RecurringExpenseService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring {--through= : Générer les échéances jusqu’à cette date (AAAA-MM-JJ)}';

    protected $description = 'Génère les occurrences dues des dépenses récurrentes';

    public function handle(RecurringExpenseService $recurringExpenses): int
    {
        $through = $this->option('through')
            ? CarbonImmutable::parse((string) $this->option('through'))->startOfDay()
            : CarbonImmutable::today();

        $count = $recurringExpenses->generateDueOccurrences($through);
        $this->info("{$count} dépense(s) récurrente(s) générée(s).");

        return self::SUCCESS;
    }
}
