<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Setting;
use App\Models\Supplier;

trait LoadsExpenseFormOptions
{
    protected function expenseFormOptions(): array
    {
        return [
            'expenseCategories' => Setting::getList('expense_categories'),
            'accounts' => Setting::getList('expense_accounts'),
            'paymentMethods' => Setting::getList('expense_payment_methods'),
            'suppliers' => Supplier::orderBy('name')->get(),
        ];
    }
}
