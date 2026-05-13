# Example: Setting Custom Starting Numbers

## Your Scenario

You want invoices to start at FA-2026/002318 instead of FA-2026/000001.

## Step-by-Step Guide

### 1. Navigate to Settings

Go to **Paramètres** → **Facture**

### 2. Configure the Settings

You'll see the current preview at the top showing what the next invoice number will look like.

**Set these values:**

| Field | Value | Explanation |
|-------|-------|-------------|
| Prochain numéro de facture | `2318` | Start numbering at 2318 |
| Format de numérotation | `FA-{YEAR}/{NUMBER}` | Creates FA-2026/XXXXXX format |
| Longueur du code | `6` | Pads numbers to 6 digits (002318) |
| Réinitialiser numérotation | `Chaque Année` | Resets to 1 every year |

### 3. Preview & Save

- Check the preview box at the top - it should show: **FA-2026/002318**
- Click **Enregistrer** (Save)

### 4. Create Your First Invoice

When you create your first invoice, it will be numbered: **FA-2026/002318**

### 5. Subsequent Invoices

Each new invoice increments by 1:
- 2nd invoice: **FA-2026/002319**
- 3rd invoice: **FA-2026/002320**
- 4th invoice: **FA-2026/002321**
- ...and so on

## Applying to Other Document Types

### Devis (Quotes)
- Prochain numéro: `2318`
- Format: `DV-{YEAR}/{NUMBER}`
- Result: DV-2026/002318, DV-2026/002319, ...

### Avoir (Credit Notes)
- Prochain numéro: `2318`
- Format: `AV-{YEAR}/{NUMBER}`
- Result: AV-2026/002318, AV-2026/002319, ...

### Bon de Commande Client (Client Purchase Order)
- Prochain numéro: `2318`
- Format: `BC-{YEAR}/{NUMBER}`
- Result: BC-2026/002318, BC-2026/002319, ...

### Bon de Commande Fournisseur (Supplier Purchase Order)
- Prochain numéro: `2318`
- Format: `BCF-{YEAR}/{NUMBER}`
- Result: BCF-2026/002318, BCF-2026/002319, ...

### Bon de Livraison (Delivery Note)
- Prochain numéro: `2318`
- Format: `BL-{YEAR}/{NUMBER}`
- Result: BL-2026/002318, BL-2026/002319, ...

### Bon de Réception (Reception Note)
- Prochain numéro: `2318`
- Format: `BR-{YEAR}/{NUMBER}`
- Result: BR-2026/002318, BR-2026/002319, ...

## Important Notes

1. **The "Prochain numéro" field is just the NUMBER part**, not the full document number. So if you want FA-2026/002318, you enter `2318` in the field, not the full FA-2026/002318.

2. **The format is separate from the number**. You configure:
   - The format pattern (e.g., `FA-{YEAR}/{NUMBER}`)
   - The starting number (e.g., `2318`)
   - The system combines them automatically

3. **Auto-increment happens automatically**. You don't need to manually update the "Prochain numéro" field after each document. The system does it for you.

4. **Preview before saving**. Always check the preview box at the top to ensure your numbering looks correct before saving.

## Testing Your Setup

1. Go to Settings → Facture
2. Set Prochain numéro to `2318`
3. Verify preview shows `FA-2026/002318`
4. Save settings
5. Create a test invoice
6. Verify it's numbered `FA-2026/002318`
7. Create another test invoice
8. Verify it's numbered `FA-2026/002319`

✅ If both numbers are correct, your setup is working perfectly!
