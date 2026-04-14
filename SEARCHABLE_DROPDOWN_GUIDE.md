# Searchable Product Dropdown Implementation Guide

## What Was Implemented

Added a **searchable product dropdown** using Select2 to all "Ajouter un article" sections.

### Features:
- ✅ Type to search products by name or reference
- ✅ Keyboard navigation
- ✅ Clear button to reset selection
- ✅ Auto-fills product details (ref, name, price)
- ✅ French language support

## Files Updated

### 1. Global Assets (✅ DONE)
- `resources/views/layouts/app.blade.php` - Added Select2 CSS/JS globally

### 2. Invoices (✅ DONE)
- `resources/views/sales/invoices/create.blade.php` - Added searchable dropdown

### 3. Remaining Files to Update

You need to apply the same changes to these files:

- `resources/views/sales/quotes/create.blade.php`
- `resources/views/sales/credit-notes/create.blade.php`
- `resources/views/purchases/supplier-invoices/create.blade.php`
- `resources/views/purchases/supplier-purchase-orders/create.blade.php`
- `resources/views/purchases/receptions/create.blade.php`
- `resources/views/purchases/supplier-credit-notes/create.blade.php`

## How to Update Each File

### Step 1: Update the select element in addItem() function

Change FROM:
```javascript
<select name="items[${itemIndex}][product_id]" onchange="fillProductDetails(this, ${itemIndex})" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
    <option value="">Sélectionner un produit</option>
    ${products.map(p => `<option value="${p.id}" data-ref="${p.ref || ''}" data-name="${p.name}" data-price="${p.sale_price || 0}">${p.name} (${p.ref || 'Sans réf'})</option>`).join('')}
</select>
```

Change TO:
```javascript
<select name="items[${itemIndex}][product_id]" onchange="fillProductDetails(this, ${itemIndex})" class="product-select w-full px-2 py-1 border border-gray-300 rounded text-sm" id="product_select_${itemIndex}">
    <option value="">Rechercher un produit...</option>
    ${products.map(p => `<option value="${p.id}" data-ref="${p.ref || ''}" data-name="${p.name}" data-price="${p.sale_price || 0}">${p.name} ${p.ref ? '(' + p.ref + ')' : ''}</option>`).join('')}
</select>
```

### Step 2: Add Select2 initialization

Add this AFTER `tbody.appendChild(row);` and BEFORE `itemIndex++;`:

```javascript
// Initialize Select2 on the newly added dropdown
$('#product_select_' + itemIndex).select2({
    placeholder: 'Rechercher un produit...',
    allowClear: true,
    width: '100%',
    language: {
        noResults: function() {
            return "Aucun produit trouvé";
        },
        searching: function() {
            return "Recherche...";
        }
    }
});
```

## Testing

After updating, test each form:

1. Click "+ Ajouter" button
2. Click on the product dropdown
3. Type to search for a product
4. Select a product and verify it auto-fills the details

## Example: Complete Updated Script

See `resources/views/sales/invoices/create.blade.php` for the complete working example.

The key changes are:
1. Added `id="product_select_${itemIndex}"` to the select element
2. Changed placeholder text to "Rechercher un produit..."
3. Added Select2 initialization after adding the row
4. Improved product display format in options
