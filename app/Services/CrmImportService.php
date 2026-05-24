<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmImportService
{
  public const CLIENT_COLUMNS = [
    'nom' => 'name',
    'email' => 'email',
    'telephone' => 'phone',
    'adresse' => 'address',
    'ville' => 'ville',
    'code_postal' => 'postal_code',
    'pays' => 'country',
    'ice' => 'ice',
    'type_client' => 'client_type',
  ];

  public const SUPPLIER_COLUMNS = [
    'nom' => 'name',
    'email' => 'email',
    'telephone' => 'phone',
    'adresse' => 'address',
    'ville' => 'ville',
    'code_postal' => 'postal_code',
    'pays' => 'country',
    'ice' => 'ice',
  ];

  public function downloadClientTemplate(): StreamedResponse
  {
    return $this->downloadTemplate('clients', array_keys(self::CLIENT_COLUMNS), [
      'nom' => 'Société ABC',
      'email' => 'contact@abc.ma',
      'telephone' => '0612345678',
      'adresse' => '12 Rue Example',
      'ville' => 'Casablanca',
      'code_postal' => '20000',
      'pays' => 'Maroc',
      'ice' => '001234567890123',
      'type_client' => 'entreprise',
    ]);
  }

  public function downloadSupplierTemplate(): StreamedResponse
  {
    return $this->downloadTemplate('fournisseurs', array_keys(self::SUPPLIER_COLUMNS), [
      'nom' => 'Fournisseur XYZ',
      'email' => 'achats@xyz.ma',
      'telephone' => '0522123456',
      'adresse' => 'Zone industrielle',
      'ville' => 'Rabat',
      'code_postal' => '10000',
      'pays' => 'Maroc',
      'ice' => '001234567890124',
    ]);
  }

  /**
   * @return array{created: int, skipped: int, errors: list<string>}
   */
  public function importClients(UploadedFile $file): array
  {
    return $this->importEntities($file, 'client', Client::class, self::CLIENT_COLUMNS);
  }

  /**
   * @return array{created: int, skipped: int, errors: list<string>}
   */
  public function importSuppliers(UploadedFile $file): array
  {
    return $this->importEntities($file, 'supplier', Supplier::class, self::SUPPLIER_COLUMNS);
  }

  /**
   * @param  class-string<Client|Supplier>  $modelClass
   * @param  array<string, string>  $columnMap
   * @return array{created: int, skipped: int, errors: list<string>}
   */
  private function importEntities(UploadedFile $file, string $type, string $modelClass, array $columnMap): array
  {
    $rows = $this->parseRows($file, array_keys($columnMap));

    if (empty($rows)) {
      return [
        'created' => 0,
        'skipped' => 0,
        'errors' => ['Le fichier ne contient aucune ligne de données.'],
      ];
    }

    $created = 0;
    $skipped = 0;
    $errors = [];
    $line = 1;

    foreach ($rows as $row) {
      $line++;
      $phone = $this->normalizePhone($row['telephone'] ?? null);

      if (! $phone) {
        $errors[] = "Ligne {$line}: le numéro de téléphone est obligatoire pour éviter les doublons.";

        continue;
      }

      if ($this->findByPhone($modelClass, $phone)) {
        $skipped++;

        continue;
      }

      $name = trim((string) ($row['nom'] ?? ''));
      if ($name === '') {
        $errors[] = "Ligne {$line}: le nom est obligatoire.";

        continue;
      }

      $data = ['name' => $name, 'phone' => $row['telephone']];

      foreach ($columnMap as $header => $field) {
        if (in_array($field, ['name', 'phone'], true)) {
          continue;
        }
        $value = isset($row[$header]) ? trim((string) $row[$header]) : '';
        if ($value !== '') {
          $data[$field] = $value;
        }
      }

      if ($type === 'client') {
        $data['client_type'] = in_array($data['client_type'] ?? '', ['entreprise', 'particulier'], true)
          ? $data['client_type']
          : 'entreprise';
        $data['code'] = $this->generateCode(Client::class, 'CLT');
        if (empty($data['email'])) {
          $data['email'] = $this->uniquePlaceholderEmail(Client::class, $name);
        }
      } else {
        $data['code'] = $this->generateCode(Supplier::class, 'FRN');
        if (empty($data['email'])) {
          $data['email'] = $this->uniquePlaceholderEmail(Supplier::class, $name);
        }
      }

      try {
        $modelClass::create($data);
        $created++;
      } catch (\Throwable $e) {
        $errors[] = "Ligne {$line}: {$e->getMessage()}";
      }
    }

    return compact('created', 'skipped', 'errors');
  }

  /**
   * @param  list<string>  $headers
   * @param  array<string, string>  $example
   */
  private function downloadTemplate(string $basename, array $headers, array $example): StreamedResponse
  {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Import');

    foreach ($headers as $index => $header) {
      $col = $this->columnLetter($index + 1);
      $sheet->setCellValue("{$col}1", $header);
      $sheet->getStyle("{$col}1")->getFont()->setBold(true);
      $sheet->setCellValue("{$col}2", $example[$header] ?? '');
      $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $instructions = $spreadsheet->createSheet();
    $instructions->setTitle('Instructions');
    $instructions->setCellValue('A1', 'Instructions');
    $instructions->getStyle('A1')->getFont()->setBold(true);
    $instructions->setCellValue('A3', '1. Le téléphone est obligatoire et sert à détecter les doublons.');
    $instructions->setCellValue('A4', '2. Si un client/fournisseur avec le même téléphone existe déjà, la ligne sera ignorée.');
    $instructions->setCellValue('A5', '3. Supprimez la ligne d\'exemple avant l\'import.');
    $instructions->getColumnDimension('A')->setWidth(70);

    $writer = new Xlsx($spreadsheet);

    return response()->streamDownload(function () use ($writer) {
      $writer->save('php://output');
    }, "modele-import-{$basename}.xlsx", [
      'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
  }

  /**
   * @param  list<string>  $expectedHeaders
   * @return list<array<string, string>>
   */
  private function parseRows(UploadedFile $file, array $expectedHeaders): array
  {
    $spreadsheet = IOFactory::load($file->getRealPath());
    $sheet = $spreadsheet->getActiveSheet();
    $raw = $sheet->toArray(null, true, true, true);

    if (count($raw) < 2) {
      return [];
    }

    $headerRow = array_shift($raw);
    $map = [];
    foreach ($headerRow as $col => $label) {
      $key = Str::slug(strtolower(trim((string) $label)), '_');
      if ($key !== '') {
        $map[$col] = $key;
      }
    }

    $rows = [];
    foreach ($raw as $row) {
      if ($this->rowIsEmpty($row)) {
        continue;
      }

      $parsed = [];
      foreach ($map as $col => $key) {
        $parsed[$key] = trim((string) ($row[$col] ?? ''));
      }

      if (trim((string) ($parsed['nom'] ?? '')) === '' && trim((string) ($parsed['telephone'] ?? '')) === '') {
        continue;
      }

      $rows[] = $parsed;
    }

    return $rows;
  }

  private function rowIsEmpty(array $row): bool
  {
    foreach ($row as $value) {
      if (trim((string) $value) !== '') {
        return false;
      }
    }

    return true;
  }

  private function normalizePhone(?string $phone): ?string
  {
    if ($phone === null || trim($phone) === '') {
      return null;
    }

    $digits = preg_replace('/\D+/', '', $phone);

    return $digits !== '' ? $digits : null;
  }

  /**
   * @param  class-string<Client|Supplier>  $modelClass
   */
  private function findByPhone(string $modelClass, string $normalizedPhone): bool
  {
    return $modelClass::query()
      ->whereNotNull('phone')
      ->get(['phone'])
      ->contains(fn ($record) => $this->normalizePhone($record->phone) === $normalizedPhone);
  }

  /**
   * @param  class-string<Client|Supplier>  $modelClass
   */
  private function generateCode(string $modelClass, string $prefix): string
  {
    $year = date('Y');
    $last = $modelClass::where('code', 'like', $prefix.$year.'%')
      ->orderByDesc('code')
      ->value('code');

    if ($last && preg_match('/'.preg_quote($prefix.$year, '/').'(\d+)/', $last, $matches)) {
      $next = (int) $matches[1] + 1;
    } else {
      $next = 1;
    }

    return $prefix.$year.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
  }

  /**
   * @param  class-string<Client|Supplier>  $modelClass
   */
  private function uniquePlaceholderEmail(string $modelClass, string $name): string
  {
    $base = Str::slug($name) ?: 'import';
    $email = "{$base}@import.local";
    $suffix = 1;

    while ($modelClass::where('email', $email)->exists()) {
      $email = "{$base}-{$suffix}@import.local";
      $suffix++;
    }

    return $email;
  }

  private function columnLetter(int $index): string
  {
    $letter = '';
    while ($index > 0) {
      $index--;
      $letter = chr(65 + ($index % 26)).$letter;
      $index = intdiv($index, 26);
    }

    return $letter;
  }
}
