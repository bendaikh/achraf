# Document Numbering System

## Overview

The document numbering system allows you to configure custom numbering formats for all business documents in the application.

## Supported Document Types

- **Facture** (Invoice) - FA-YYYY/XXXXXX
- **Devis** (Quote) - DV-YYYY/XXXXXX
- **Avoir** (Credit Note) - AV-YYYY/XXXXXX
- **Bon de Commande Client** (Client Purchase Order) - BC-YYYY/XXXXXX
- **Bon de Commande Fournisseur** (Supplier Purchase Order) - BCF-YYYY/XXXXXX
- **Bon de Livraison** (Delivery Note) - BL-YYYY/XXXXXX
- **Bon de Réception** (Reception Note) - BR-YYYY/XXXXXX

## Configuration

### Settings Page

Navigate to **Paramètres** (Settings) to configure numbering for each document type.

### Available Settings

1. **Prochain numéro** - The next sequential number (e.g., 2318)
2. **Format de numérotation** - The numbering format pattern
3. **Longueur du code** - Number of digits with zero-padding (default: 6)
4. **Réinitialiser numérotation** - Reset period:
   - **Jamais** - Never reset
   - **Chaque Année** - Reset every year
   - **Chaque Mois** - Reset every month

### Format Placeholders

Use these placeholders in the format field:

- `{NUMBER}` - The sequential number (e.g., 002318)
- `{YEAR}` - The current year (e.g., 2026)
- `{MONTH}` - The current month (e.g., 05)

### Example Formats

- `FA-{YEAR}/{NUMBER}` → FA-2026/002318
- `{YEAR}-{MONTH}-{NUMBER}` → 2026-05-002318
- `INV-{NUMBER}` → INV-002318

## How It Works

### Initial Setup

1. Go to **Paramètres** (Settings)
2. Select the document type (e.g., Facture)
3. Set the **Prochain numéro de facture** to your desired starting number (e.g., 2318)
4. Configure the format (e.g., `FA-{YEAR}/{NUMBER}`)
5. Set the code length (e.g., 6 for 002318)
6. Click **Enregistrer** (Save)

### Creating Documents

When you create a new document:
1. The system generates the number using your configured format
2. The number is automatically incremented for the next document
3. If reset period is "yearly", the counter resets to 1 every January 1st

### Example Scenario

**Initial Settings:**
- Prochain numéro: 2318
- Format: `FA-{YEAR}/{NUMBER}`
- Code length: 6

**Document Creation Sequence:**
1. First invoice: FA-2026/002318
2. Second invoice: FA-2026/002319
3. Third invoice: FA-2026/002320
4. After New Year (if yearly reset): FA-2027/000001

## Technical Details

### Service Class

The `DocumentNumberService` class handles all number generation:

```php
use App\Services\DocumentNumberService;

// Generate and increment
$invoiceNumber = DocumentNumberService::generate('facture');

// Preview without incrementing
$preview = DocumentNumberService::preview('facture');
```

### Database Storage

Settings are stored in the `settings` table with keys like:
- `facture_next_number`
- `facture_format`
- `facture_code_length`
- `facture_reset_period`
- `facture_year`

## Migration from Old System

The old hardcoded numbering has been replaced with this flexible system. If you have existing documents, they will keep their old numbers, and new documents will use the new numbering system starting from the number you configure.

## Support

For questions or issues, please contact the development team.
