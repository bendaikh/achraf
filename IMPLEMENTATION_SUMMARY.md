# Document Numbering System - Implementation Summary

## What Was Implemented

A flexible, configurable document numbering system that allows users to:
1. Set custom starting numbers for all document types
2. Define custom numbering formats
3. Configure automatic increment behavior
4. Set periodic reset rules (yearly, monthly, never)
5. Preview document numbers before creating them

## Files Created

### 1. `app/Services/DocumentNumberService.php`
- Core service handling document number generation
- Methods:
  - `generate($type)` - Generate and increment document number
  - `preview($type)` - Preview next number without incrementing
  - `shouldResetCounter($type, $resetPeriod)` - Handle period-based resets
  - `getDefaultFormat($type)` - Provide default formats

### 2. `database/seeders/DocumentSettingsSeeder.php`
- Initializes default settings for all document types
- Sets sensible defaults (FA-{YEAR}/{NUMBER}, 6-digit padding, yearly reset)
- Run with: `php artisan db:seed --class=DocumentSettingsSeeder`

### 3. Documentation Files
- `DOCUMENT_NUMBERING.md` - Technical documentation
- `EXAMPLE_USAGE.md` - User guide with examples
- `IMPLEMENTATION_SUMMARY.md` - This file

## Files Modified

### Controllers Updated

1. **InvoiceController.php** (Facture)
   - `create()` - Uses `DocumentNumberService::preview('facture')`
   - `store()` - Uses `DocumentNumberService::generate('facture')`

2. **QuoteController.php** (Devis)
   - `create()` - Uses `DocumentNumberService::preview('devis')`
   - `store()` - Uses `DocumentNumberService::generate('devis')`

3. **CreditNoteController.php** (Avoir)
   - `create()` - Uses `DocumentNumberService::preview('avoir')`
   - `store()` - Uses `DocumentNumberService::generate('avoir')`

4. **PurchaseOrderController.php** (Bon de commande client)
   - `create()` - Uses `DocumentNumberService::preview('bc_client')`
   - `store()` - Uses `DocumentNumberService::generate('bc_client')`

5. **SupplierPurchaseOrderController.php** (Bon de commande fournisseur)
   - `create()` - Uses `DocumentNumberService::preview('bc_fournisseur')`
   - `store()` - Uses `DocumentNumberService::generate('bc_fournisseur')`

6. **ReceptionController.php** (Bon de réception)
   - `create()` - Uses `DocumentNumberService::preview('bon_reception')`
   - `store()` - Uses `DocumentNumberService::generate('bon_reception')`

7. **OrderController.php** (Document conversion)
   - `createQuote()` - Uses `DocumentNumberService::generate('devis')`
   - `createInvoice()` - Uses `DocumentNumberService::generate('facture')`
   - `createPurchaseOrder()` - Uses `DocumentNumberService::generate('bon_livraison')`

8. **SettingsController.php**
   - Added `getPreviewNumbers()` method
   - Modified `index()` to pass preview numbers to view
   - Added import for `DocumentNumberService`

### Views Updated

**resources/views/settings/index.blade.php**
- Added preview boxes for all document types showing the next number
- Preview boxes appear at the top of each settings section
- Show format: "Aperçu du prochain numéro: FA-2026/002318"

## Document Types Supported

| Type | Code | Default Format | Example |
|------|------|----------------|---------|
| Facture | `facture` | FA-{YEAR}/{NUMBER} | FA-2026/002318 |
| Devis | `devis` | DV-{YEAR}/{NUMBER} | DV-2026/002318 |
| Avoir | `avoir` | AV-{YEAR}/{NUMBER} | AV-2026/002318 |
| BC Client | `bc_client` | BC-{YEAR}/{NUMBER} | BC-2026/002318 |
| BC Fournisseur | `bc_fournisseur` | BCF-{YEAR}/{NUMBER} | BCF-2026/002318 |
| Bon Livraison | `bon_livraison` | BL-{YEAR}/{NUMBER} | BL-2026/002318 |
| Bon Réception | `bon_reception` | BR-{YEAR}/{NUMBER} | BR-2026/002318 |

## Settings Structure

Each document type has these settings in the database:

| Setting Key | Example Value | Description |
|-------------|---------------|-------------|
| `{type}_next_number` | `2318` | Next sequential number |
| `{type}_format` | `FA-{YEAR}/{NUMBER}` | Format pattern |
| `{type}_code_length` | `6` | Number padding length |
| `{type}_reset_period` | `yearly` | When to reset counter |
| `{type}_year` | `2026` | Current year for tracking |
| `{type}_apply_to_old` | `0` | Apply to old documents (future) |

## How It Works

### Number Generation Flow

1. **User creates a document** (e.g., Invoice)
2. **System calls** `DocumentNumberService::generate('facture')`
3. **Service retrieves settings**:
   - Next number: 2318
   - Format: FA-{YEAR}/{NUMBER}
   - Code length: 6
   - Reset period: yearly
4. **Service checks reset conditions**:
   - If yearly reset and new year → reset to 1
   - Otherwise use current number
5. **Service formats the number**:
   - Pad number: 2318 → 002318
   - Replace placeholders: FA-{YEAR}/{NUMBER} → FA-2026/002318
6. **Service increments counter**: 2318 → 2319
7. **Returns formatted number**: FA-2026/002318

### Preview Flow

1. **User opens document creation form**
2. **System calls** `DocumentNumberService::preview('facture')`
3. **Service generates preview** (same as above but without incrementing)
4. **Preview shown to user**: "Next invoice will be FA-2026/002318"

## Testing Checklist

- [x] Service class created and working
- [x] All controllers updated
- [x] Settings view updated with previews
- [x] Seeder created and run
- [x] No linter errors
- [x] No hardcoded numbering remains
- [x] Documentation created

## Usage Example

```php
// In a controller
use App\Services\DocumentNumberService;

// Preview the next number (doesn't increment)
$preview = DocumentNumberService::preview('facture');
// Returns: "FA-2026/002318"

// Generate and increment
$invoiceNumber = DocumentNumberService::generate('facture');
// Returns: "FA-2026/002318"
// Next call will return: "FA-2026/002319"
```

## Database Changes

**No migrations needed** - Uses existing `settings` table with dynamic keys.

Settings are created on-demand or via seeder.

## Backward Compatibility

✅ **Fully backward compatible**
- Existing documents keep their old numbers
- New documents use the new system
- If settings don't exist, system uses sensible defaults

## Future Enhancements

Potential improvements (not implemented):
1. Bulk renumber old documents based on new format
2. Custom prefixes per client/supplier
3. Support for more placeholders (quarter, week, etc.)
4. Number range validation and warnings
5. Audit log for number generation

## Support & Maintenance

The system is fully self-contained and requires no ongoing maintenance beyond:
- Setting initial numbers when going live
- Adjusting formats if business rules change
- Monitoring for number conflicts (rare)

## Conclusion

The implementation is complete and fully functional. Users can now:
- Start document numbering at any number (e.g., 2318)
- Use custom formats for all document types
- Have automatic incrementing with optional yearly/monthly resets
- Preview numbers before creating documents

All requested features have been implemented as specified.
