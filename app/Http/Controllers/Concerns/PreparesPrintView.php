<?php

namespace App\Http\Controllers\Concerns;

use App\Support\CompanyInfo;
use App\Support\DocumentTaxBreakdown;
use Illuminate\Support\Collection;

trait PreparesPrintView
{
  /**
   * @return array{company: array<string, mixed>, taxes: array<string, float>}
   */
  protected function printViewData(object $document, Collection $items): array
  {
    return [
      'company' => CompanyInfo::all(),
      'taxes' => DocumentTaxBreakdown::fromDocument($document, $items),
    ];
  }
}
