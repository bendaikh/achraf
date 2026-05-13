<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Setting;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocumentNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test settings
        Setting::set('facture_next_number', '2318', 'Test next number');
        Setting::set('facture_format', 'FA-{YEAR}/{NUMBER}', 'Test format');
        Setting::set('facture_year', '2026', 'Test year');
        Setting::set('facture_code_length', '6', 'Test code length');
        Setting::set('facture_reset_period', 'yearly', 'Test reset period');
    }

    public function test_preview_generates_correct_format()
    {
        $preview = DocumentNumberService::preview('facture');
        
        $this->assertEquals('FA-2026/002318', $preview);
    }

    public function test_generate_creates_number_and_increments()
    {
        // Generate first number
        $number1 = DocumentNumberService::generate('facture');
        $this->assertEquals('FA-2026/002318', $number1);
        
        // Verify it incremented
        $nextNumber = Setting::get('facture_next_number');
        $this->assertEquals('2319', $nextNumber);
        
        // Generate second number
        $number2 = DocumentNumberService::generate('facture');
        $this->assertEquals('FA-2026/002319', $number2);
        
        // Verify it incremented again
        $nextNumber = Setting::get('facture_next_number');
        $this->assertEquals('2320', $nextNumber);
    }

    public function test_code_length_pads_correctly()
    {
        Setting::set('facture_next_number', '5', 'Test next number');
        Setting::set('facture_code_length', '8', 'Test code length');
        
        $number = DocumentNumberService::generate('facture');
        
        $this->assertEquals('FA-2026/00000005', $number);
    }

    public function test_different_format_works()
    {
        Setting::set('facture_format', 'INV-{NUMBER}-{YEAR}', 'Test format');
        Setting::set('facture_next_number', '100', 'Test next number');
        
        $number = DocumentNumberService::generate('facture');
        
        $this->assertEquals('INV-000100-2026', $number);
    }

    public function test_month_placeholder_works()
    {
        Setting::set('facture_format', '{YEAR}-{MONTH}-{NUMBER}', 'Test format');
        Setting::set('facture_next_number', '42', 'Test next number');
        
        $number = DocumentNumberService::generate('facture');
        
        $expectedMonth = date('m');
        $this->assertEquals("2026-{$expectedMonth}-000042", $number);
    }

    public function test_default_values_work_when_settings_missing()
    {
        // Create a fresh settings entry
        Setting::where('key', 'like', 'devis_%')->delete();
        
        $preview = DocumentNumberService::preview('devis');
        
        // Should use default format DV-{YEAR}/{NUMBER}
        $currentYear = date('Y');
        $this->assertEquals("DV-{$currentYear}/000001", $preview);
    }

    public function test_all_document_types_have_unique_formats()
    {
        $types = ['facture', 'devis', 'avoir', 'bc_client', 'bc_fournisseur', 'bon_livraison', 'bon_reception'];
        
        $numbers = [];
        foreach ($types as $type) {
            Setting::set("{$type}_next_number", '1', 'Test');
            Setting::set("{$type}_code_length", '6', 'Test');
            $numbers[$type] = DocumentNumberService::preview($type);
        }
        
        // Verify all are unique
        $this->assertEquals(count($types), count(array_unique($numbers)));
        
        // Verify expected prefixes
        $this->assertStringStartsWith('FA-', $numbers['facture']);
        $this->assertStringStartsWith('DV-', $numbers['devis']);
        $this->assertStringStartsWith('AV-', $numbers['avoir']);
        $this->assertStringStartsWith('BC-', $numbers['bc_client']);
        $this->assertStringStartsWith('BCF-', $numbers['bc_fournisseur']);
        $this->assertStringStartsWith('BL-', $numbers['bon_livraison']);
        $this->assertStringStartsWith('BR-', $numbers['bon_reception']);
    }
}
