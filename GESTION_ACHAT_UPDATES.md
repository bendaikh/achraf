# Gestion Achat Section - Comprehensive Updates

## Summary of Changes

This document summarizes all the comprehensive updates made to the Gestion Achat (Purchase Management) section.

## 1. Dépense Section Restructuring

### Changes Made:
- **Removed** the single "Dépenses" menu item
- **Added** two new menu items:
  - "Dépense avec facture" (Expenses with invoice)
  - "Dépense sans facture" (Expenses without invoice)

### Implementation Details:
- Updated `resources/views/layouts/sidebar.blade.php` with new menu structure
- Created separate controllers:
  - `ExpenseWithInvoiceController.php`
  - `ExpenseWithoutInvoiceController.php`
- Added database migration to add `expense_type` field to expenses table
- Added `invoice_file_path` field to expenses table for file uploads
- Created separate view directories:
  - `resources/views/purchases/expenses-with-invoice/`
  - `resources/views/purchases/expenses-without-invoice/`

### Routes Added:
```
GET    /purchases/expenses-with-invoice
POST   /purchases/expenses-with-invoice
GET    /purchases/expenses-with-invoice/create
GET    /purchases/expenses-with-invoice/{id}
PUT    /purchases/expenses-with-invoice/{id}
DELETE /purchases/expenses-with-invoice/{id}
GET    /purchases/expenses-with-invoice/{id}/edit

GET    /purchases/expenses-without-invoice
POST   /purchases/expenses-without-invoice
GET    /purchases/expenses-without-invoice/create
GET    /purchases/expenses-without-invoice/{id}
PUT    /purchases/expenses-without-invoice/{id}
DELETE /purchases/expenses-without-invoice/{id}
GET    /purchases/expenses-without-invoice/{id}/edit
```

## 2. Facture Fournisseur Creation Form Modifications

### Changes Made:

#### A. Supplier Creation Button
- Added a button next to the "Fournisseur" dropdown field
- Opens the supplier creation page in a new tab
- Users can quickly create new suppliers without leaving the invoice form

#### B. Removed Matricule Field
- Removed the "Matricule" input field from the creation form
- Updated validation rules in `SupplierInvoiceController.php`

#### C. Stock Location Dropdown
- Changed "Emplacement du stock" from text input to dropdown
- Options available:
  - "Stock magasin"
  - "Stock en ligne"

#### D. Editable Invoice Number
- Changed "Numéro de facture" from disabled/read-only to editable
- Added validation for uniqueness
- Users can now enter the supplier's invoice number manually

#### E. Invoice File Upload
- Added file upload input for supplier invoice
- Accepts: PDF, JPG, JPEG, PNG (Max: 10MB)
- Files stored in `storage/app/public/supplier_invoices/`
- Added `invoice_file_path` field to database

### Updated Files:
- `resources/views/purchases/supplier-invoices/create.blade.php`
- `app/Http/Controllers/SupplierInvoiceController.php`
- `app/Models/SupplierInvoice.php`

## 3. Facture Fournisseur Table Enhancements

### A. New Action Buttons

#### Print Button
- Opens invoice in print-friendly format
- Route: `/purchases/supplier-invoices/{id}/print`
- Opens in new tab

#### Edit/Modification Button
- Allows editing of existing invoices
- Route: `/purchases/supplier-invoices/{id}/edit`
- Full edit capability with all fields editable

#### Règlement de Paiement (Payment Regulation)
- Displays payment history for the invoice
- Allows adding new payments
- Shows payment methods used
- Supports file uploads for payment documents
- Route: `/purchases/supplier-invoices/{id}/payments`

Features:
- View total amount, amount paid, remaining balance
- Add new payment entries with:
  - Payment date
  - Amount
  - Payment method (Cash, Check, Bank Transfer, Credit Card, Other)
  - Reference number
  - Payment document upload
  - Notes
- Delete existing payments
- Track all payment history

### B. Invoice File Status Column
- New column: "Facture importée"
- Shows "Oui" (Yes) if invoice file was uploaded
- Shows "Non" (No) if no invoice file
- Visual badges (green for Yes, gray for No)

### Updated Files:
- `resources/views/purchases/supplier-invoices/index.blade.php`
- `resources/views/purchases/supplier-invoices/edit.blade.php` (created)
- `resources/views/purchases/supplier-invoices/print.blade.php` (created)
- `resources/views/purchases/supplier-invoices/payments/index.blade.php` (created)

## 4. Database Migrations

### Migration Files Created:

1. **`2026_05_16_121923_add_invoice_file_and_type_to_expenses_table.php`**
   - Adds `expense_type` enum field (with_invoice, without_invoice)
   - Adds `invoice_file_path` string field

2. **`2026_05_16_121923_add_invoice_file_to_supplier_invoices_table.php`**
   - Adds `invoice_file_path` string field

3. **`2026_05_16_121924_create_supplier_invoice_payments_table.php`**
   - Creates new table for payment regulations
   - Fields:
     - `id`
     - `supplier_invoice_id` (foreign key)
     - `payment_date`
     - `amount`
     - `payment_method`
     - `payment_reference`
     - `payment_file_path`
     - `notes`
     - `timestamps`

## 5. Models Updated

### Expense Model
- Added fillable fields: `expense_type`, `invoice_file_path`

### SupplierInvoice Model
- Added fillable field: `invoice_file_path`
- Added relationship: `payments()` hasMany
- Added computed attributes:
  - `total_paid` - sum of all payments
  - `remaining_balance` - total minus total_paid

### SupplierInvoicePayment Model (New)
- Created new model for payment tracking
- Relationships with SupplierInvoice

## 6. Controllers

### New Controllers:
1. **ExpenseWithInvoiceController**
   - Manages expenses with invoices
   - Handles file uploads for invoice documents

2. **ExpenseWithoutInvoiceController**
   - Manages expenses without invoices
   - Simplified workflow without file uploads

3. **SupplierInvoicePaymentController**
   - Manages payment regulations
   - Handles payment document uploads

### Updated Controllers:
1. **SupplierInvoiceController**
   - Added `edit()` method
   - Added `update()` method
   - Added `print()` method
   - Updated `store()` to handle file uploads and editable invoice numbers
   - Updated `destroy()` to delete associated files

## 7. Views Structure

```
resources/views/purchases/
├── expenses-with-invoice/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   └── show.blade.php
├── expenses-without-invoice/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   └── show.blade.php
└── supplier-invoices/
    ├── index.blade.php
    ├── create.blade.php
    ├── edit.blade.php (NEW)
    ├── show.blade.php
    ├── print.blade.php (NEW)
    └── payments/
        └── index.blade.php (NEW)
```

## 8. File Storage Structure

```
storage/app/public/
├── expenses/
│   └── invoices/          (Expense invoice files)
├── supplier_invoices/      (Supplier invoice files)
└── supplier_invoice_payments/  (Payment document files)
```

## Testing Checklist

- [ ] Navigate to Gestion Achat menu
- [ ] Verify "Dépense avec facture" and "Dépense sans facture" menu items exist
- [ ] Create expense with invoice (with file upload)
- [ ] Create expense without invoice
- [ ] Create new supplier invoice with:
  - [ ] Custom invoice number
  - [ ] Stock location dropdown works
  - [ ] Supplier creation button opens in new tab
  - [ ] File upload works
- [ ] View supplier invoices list
- [ ] Verify "Facture importée" column shows correctly
- [ ] Test Print button
- [ ] Test Edit button and update invoice
- [ ] Test Payment Regulation:
  - [ ] Add payment with document
  - [ ] View payment history
  - [ ] Delete payment
  - [ ] Verify totals calculate correctly

## Files Modified

### Controllers:
- `app/Http/Controllers/SupplierInvoiceController.php`
- `app/Http/Controllers/ExpenseWithInvoiceController.php` (NEW)
- `app/Http/Controllers/ExpenseWithoutInvoiceController.php` (NEW)
- `app/Http/Controllers/SupplierInvoicePaymentController.php` (NEW)

### Models:
- `app/Models/Expense.php`
- `app/Models/SupplierInvoice.php`
- `app/Models/SupplierInvoicePayment.php` (NEW)

### Views:
- `resources/views/layouts/sidebar.blade.php`
- `resources/views/purchases/supplier-invoices/create.blade.php`
- `resources/views/purchases/supplier-invoices/index.blade.php`
- `resources/views/purchases/supplier-invoices/edit.blade.php` (NEW)
- `resources/views/purchases/supplier-invoices/print.blade.php` (NEW)
- `resources/views/purchases/supplier-invoices/payments/index.blade.php` (NEW)
- All views in `resources/views/purchases/expenses-with-invoice/` (NEW)
- All views in `resources/views/purchases/expenses-without-invoice/` (NEW)

### Routes:
- `routes/web.php`

### Migrations:
- `database/migrations/2026_05_16_121923_add_invoice_file_and_type_to_expenses_table.php`
- `database/migrations/2026_05_16_121923_add_invoice_file_to_supplier_invoices_table.php`
- `database/migrations/2026_05_16_121924_create_supplier_invoice_payments_table.php`

## Notes

1. All file uploads are validated for type (PDF, JPG, JPEG, PNG) and size (max 10MB)
2. Files are stored in the public disk and accessible via the `/storage` URL
3. The storage link must be created: `php artisan storage:link`
4. All migrations have been run successfully
5. The system maintains backward compatibility with existing expenses data
