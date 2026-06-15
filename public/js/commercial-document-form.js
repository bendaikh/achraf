(function () {
    'use strict';

    function config() {
        return window.commercialDocConfig || {
            pricesAreTtc: false,
            priceMode: 'sale',
            products: [],
        };
    }

    function vatRateFromCategory(label) {
        if (!label) return 20;
        var match = String(label).match(/(\d+(?:[.,]\d+)?)\s*%/);
        return match ? parseFloat(match[1].replace(',', '.')) : 20;
    }

    window.getCommercialUnitPrice = function (product, taxRate) {
        var cfg = config();
        var priceHt = parseFloat(product.sale_price_ht || product.cost_price_ht || 0) || 0;
        var priceTtc = parseFloat(product.sale_price || 0) || 0;

        if (cfg.priceMode === 'purchase') {
            priceHt = parseFloat(product.cost_price_ht || product.sale_price_ht || 0) || 0;
            if (priceHt === 0 && priceTtc > 0) {
                priceHt = priceTtc / (1 + taxRate / 100);
            }
        } else if (cfg.pricesAreTtc) {
            if (priceTtc > 0) return priceTtc;
            if (priceHt > 0) return priceHt * (1 + taxRate / 100);
            return 0;
        } else {
            if (priceHt > 0) return priceHt;
            if (priceTtc > 0) return priceTtc / (1 + taxRate / 100);
            return 0;
        }

        return cfg.pricesAreTtc
            ? (priceTtc > 0 ? priceTtc : priceHt * (1 + taxRate / 100))
            : priceHt;
    };

    window.fillCommercialProductDetails = function (selectElement, index) {
        var cfg = config();
        var selectedOption = selectElement.options[selectElement.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        var ref = selectedOption.getAttribute('data-ref') || '';
        var name = selectedOption.getAttribute('data-name') || '';
        var vatCategory = selectedOption.getAttribute('data-vat') || '';
        var taxRate = vatRateFromCategory(vatCategory);

        var product = cfg.products.find(function (p) {
            return String(p.id) === String(selectedOption.value);
        }) || {
            sale_price_ht: selectedOption.getAttribute('data-price-ht'),
            sale_price: selectedOption.getAttribute('data-price-ttc'),
            cost_price_ht: selectedOption.getAttribute('data-cost-ht'),
        };

        var unitPrice = window.getCommercialUnitPrice(product, taxRate);

        var refEl = document.getElementById('ref_' + index);
        var designationEl = document.getElementById('designation_' + index);
        var priceEl = document.getElementById('price_' + index);
        var taxEl = document.querySelector('[name="items[' + index + '][tax_rate]"]');

        if (refEl) refEl.value = ref;
        if (designationEl) designationEl.value = name;
        if (taxEl && vatCategory) taxEl.value = taxRate.toFixed(2);
        if (priceEl) priceEl.value = unitPrice.toFixed(2);

        if (typeof window.calculateCommercialTotal === 'function') {
            window.calculateCommercialTotal();
        }
    };

    window.discountRowHtml = function (index, data) {
        return window.discountRowHtmlWithData(index, data || {});
    };

    window.discountRowHtmlWithData = function (index, data) {
        data = data || {};
        var discountType = data.discount_type === 'percent' ? 'percent' : 'fixed';
        var discount = data.discount != null ? data.discount : 0;

        return '' +
            '<div class="flex items-center gap-1">' +
            '<input type="number" step="0.01" name="items[' + index + '][discount]" value="' + discount + '" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateCommercialTotal()">' +
            '<select name="items[' + index + '][discount_type]" class="w-14 px-1 py-1 border border-gray-300 rounded text-xs" onchange="calculateCommercialTotal()">' +
            '<option value="fixed"' + (discountType === 'fixed' ? ' selected' : '') + '>DH</option>' +
            '<option value="percent"' + (discountType === 'percent' ? ' selected' : '') + '>%</option>' +
            '</select>' +
            '</div>';
    };

    window.calculateCommercialTotal = function () {
        var rows = document.querySelectorAll('#itemsBody tr');
        var totalHT = 0;
        var totalDiscount = 0;
        var totalTax = 0;
        var cfg = config();

        rows.forEach(function (row) {
            var quantity = parseFloat(row.querySelector('[name*="[quantity]"]')?.value) || 0;
            var unitPrice = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value) || 0;
            var taxRate = parseFloat(row.querySelector('[name*="[tax_rate]"]')?.value) || 0;
            var discountInput = parseFloat(row.querySelector('[name*="[discount]"]')?.value) || 0;
            var discountType = row.querySelector('[name*="[discount_type]"]')?.value || 'fixed';

            var lineBase = quantity * unitPrice;
            var discountAmount = discountType === 'percent'
                ? lineBase * (discountInput / 100)
                : discountInput;

            var lineHT;
            var lineTax;

            if (cfg.pricesAreTtc && cfg.priceMode === 'sale') {
                var lineTtc = lineBase - discountAmount;
                lineHT = lineTtc / (1 + taxRate / 100);
                lineTax = lineTtc - lineHT;
            } else {
                lineHT = Math.max(0, lineBase - discountAmount);
                lineTax = lineHT * (taxRate / 100);
            }

            totalHT += lineHT;
            totalDiscount += discountAmount;
            totalTax += lineTax;
        });

        var totalTTC = totalHT + totalTax;

        var subtotalEl = document.getElementById('subtotal');
        var discountEl = document.getElementById('discount');
        var taxEl = document.getElementById('taxAmount');
        var totalEl = document.getElementById('total');

        if (subtotalEl) subtotalEl.textContent = totalHT.toFixed(2);
        if (discountEl) discountEl.textContent = totalDiscount.toFixed(2);
        if (taxEl) taxEl.textContent = totalTax.toFixed(2);
        if (totalEl) totalEl.textContent = totalTTC.toFixed(2);
    };

    window.filterInvoicesByParty = function (partyId, invoiceSelectId, fetchUrl) {
        var select = document.getElementById(invoiceSelectId);
        if (!select) return;

        select.innerHTML = '<option value="">AUCUNE SELECTION</option>';

        if (!partyId) return;

        fetch(fetchUrl.replace('__PARTY__', partyId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                (data.invoices || []).forEach(function (inv) {
                    var opt = document.createElement('option');
                    opt.value = inv.id;
                    opt.textContent = inv.label;
                    select.appendChild(opt);
                });
            });
    };

    window.calculateTotal = window.calculateCommercialTotal;
})();
