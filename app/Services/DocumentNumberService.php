<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * Generate document number based on settings
     *
     * @param string $type Document type: facture, devis, avoir, bc_client, bc_fournisseur, bon_livraison, bon_reception
     * @param string|null $modelClass The model class (e.g., Invoice::class)
     * @return string Generated document number
     */
    public static function generate(string $type, ?string $modelClass = null): string
    {
        // Get settings for this document type
        $nextNumber = (int) Setting::get("{$type}_next_number", 1);
        $format = Setting::get("{$type}_format", self::getDefaultFormat($type));
        $year = Setting::get("{$type}_year", date('Y'));
        $codeLength = (int) Setting::get("{$type}_code_length", 6);
        $resetPeriod = Setting::get("{$type}_reset_period", 'yearly');

        // Check if we need to reset the counter
        $shouldReset = self::shouldResetCounter($type, $resetPeriod);
        if ($shouldReset) {
            $nextNumber = 1;
            Setting::set("{$type}_next_number", 1);
            Setting::set("{$type}_year", date('Y'));
            $year = date('Y');
        }

        // Format the number with leading zeros
        $formattedNumber = str_pad($nextNumber, $codeLength, '0', STR_PAD_LEFT);

        // Replace placeholders in format
        $documentNumber = str_replace(
            ['{NUMBER}', '{YEAR}', '{MONTH}'],
            [$formattedNumber, $year, date('m')],
            $format
        );

        // Increment the next number for future documents
        Setting::set("{$type}_next_number", $nextNumber + 1);

        return $documentNumber;
    }

    /**
     * Check if counter should be reset based on period
     */
    private static function shouldResetCounter(string $type, string $resetPeriod): bool
    {
        if ($resetPeriod === 'never') {
            return false;
        }

        $savedYear = Setting::get("{$type}_year", date('Y'));
        $currentYear = date('Y');

        if ($resetPeriod === 'yearly' && $savedYear != $currentYear) {
            return true;
        }

        if ($resetPeriod === 'monthly') {
            $savedMonth = Setting::get("{$type}_month", date('m'));
            $currentMonth = date('m');
            
            if ($savedYear != $currentYear || $savedMonth != $currentMonth) {
                Setting::set("{$type}_month", $currentMonth);
                return true;
            }
        }

        return false;
    }

    /**
     * Get default format for document type
     */
    private static function getDefaultFormat(string $type): string
    {
        return match ($type) {
            'facture' => 'FA-{YEAR}/{NUMBER}',
            'devis' => 'DV-{YEAR}/{NUMBER}',
            'avoir' => 'AV-{YEAR}/{NUMBER}',
            'bc_client' => 'BC-{YEAR}/{NUMBER}',
            'bc_fournisseur' => 'BCF-{YEAR}/{NUMBER}',
            'bon_livraison' => 'BL-{YEAR}/{NUMBER}',
            'bon_reception' => 'BR-{YEAR}/{NUMBER}',
            default => '{NUMBER}',
        };
    }

    /**
     * Preview what the next document number will look like
     */
    public static function preview(string $type): string
    {
        $nextNumber = (int) Setting::get("{$type}_next_number", 1);
        $format = Setting::get("{$type}_format", self::getDefaultFormat($type));
        $year = Setting::get("{$type}_year", date('Y'));
        $codeLength = (int) Setting::get("{$type}_code_length", 6);

        $formattedNumber = str_pad($nextNumber, $codeLength, '0', STR_PAD_LEFT);

        return str_replace(
            ['{NUMBER}', '{YEAR}', '{MONTH}'],
            [$formattedNumber, $year, date('m')],
            $format
        );
    }
}
