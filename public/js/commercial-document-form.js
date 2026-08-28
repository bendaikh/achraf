(function () {
    'use strict';

    function config() {
        return window.commercialDocConfig || {
            pricesAreTtc: false,
            priceMode: 'sale',
            products: [],
        };
    }

    function ensureProductCache() {
        var cfg = config();
        if (!Array.isArray(cfg.products)) {
            cfg.products = [];
        }
        window.commercialDocConfig = cfg;
        return cfg.products;
    }

    function cacheCommercialProduct(data) {
        if (!data || data.id == null) {
            return;
        }
        var products = ensureProductCache();
        var product = {
            id: data.id,
            product_id: data.product_id != null ? data.product_id : data.id,
            product_variant_id: data.product_variant_id != null ? data.product_variant_id : null,
            name: data.name || '',
            text: data.text || data.name || '',
            ref: data.ref || '',
            variant: data.variant || null,
            vat_category: data.vat_category || '',
            sale_price_ht: data.sale_price_ht != null ? data.sale_price_ht : 0,
            sale_price: data.sale_price != null ? data.sale_price : 0,
            cost_price_ht: data.cost_price_ht != null ? data.cost_price_ht : 0,
            cost_price_ttc: data.cost_price_ttc != null ? data.cost_price_ttc : 0,
            last_purchase_price: data.last_purchase_price != null ? data.last_purchase_price : null,
        };
        var index = products.findIndex(function (item) {
            return String(item.id) === String(product.id);
        });
        if (index >= 0) {
            products[index] = product;
        } else {
            products.push(product);
        }
    }

    function applyProductDataToOption(option, data) {
        if (!option || !data) {
            return;
        }
        option.setAttribute('data-ref', data.ref || '');
        option.setAttribute('data-name', data.name || '');
        option.setAttribute('data-text', data.text || data.name || '');
        option.setAttribute('data-product-id', data.product_id != null ? data.product_id : (data.id || ''));
        option.setAttribute('data-product-variant-id', data.product_variant_id != null ? data.product_variant_id : '');
        option.setAttribute('data-vat', data.vat_category || '');
        option.setAttribute('data-price-ht', data.sale_price_ht != null ? data.sale_price_ht : (data.cost_price_ht || 0));
        option.setAttribute('data-price-ttc', data.sale_price != null ? data.sale_price : 0);
        option.setAttribute('data-cost-ht', data.cost_price_ht != null ? data.cost_price_ht : 0);
        option.setAttribute('data-cost-ttc', data.cost_price_ttc != null ? data.cost_price_ttc : 0);
        option.setAttribute(
            'data-last-purchase',
            data.last_purchase_price != null && data.last_purchase_price !== '' ? data.last_purchase_price : ''
        );
    }

    function selectedProductFromItem(data) {
        if (!data || !data.product_id) {
            return null;
        }
        return {
            id: data.product_id || data.id,
            product_id: data.product_id || data.id,
            product_variant_id: data.product_variant_id || null,
            name: data.designation || data.name || '',
            text: data.text || data.designation || data.name || '',
            ref: data.ref || '',
            vat_category: data.vat_category || '',
            sale_price_ht: data.sale_price_ht,
            sale_price: data.sale_price,
            cost_price_ht: data.cost_price_ht,
            cost_price_ttc: data.cost_price_ttc,
            last_purchase_price: data.last_purchase_price,
        };
    }

    window.commercialProductSelectHtml = function (index, selected) {
        var selectedHtml = '';
        if (selected && selected.id) {
            var label = selected.text
                || ((selected.name || '') + (selected.ref ? ' (' + selected.ref + ')' : ''));
            selectedHtml = '<option value="' + selected.id + '" selected>' + String(label)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;') + '</option>';
        }

        return '' +
            '<select name="items[' + index + '][product_id]" ' +
            'onchange="fillCommercialProductDetails(this, ' + index + ')" ' +
            'class="product-select w-full px-2 py-1 border border-gray-300 rounded text-sm" ' +
            'id="product_select_' + index + '">' +
            '<option value="">Rechercher un produit...</option>' +
            selectedHtml +
            '</select>' +
            '<input type="hidden" name="items[' + index + '][product_variant_id]" id="product_variant_id_' + index + '" value="' +
            (selected && selected.product_variant_id ? selected.product_variant_id : '') + '">';
    };

    window.initCommercialProductSelect = function (selector, index, selected) {
        if (typeof window.$ === 'undefined' || !window.$.fn || !window.$.fn.select2) {
            return;
        }

        var $el = window.$(selector);
        if (!$el.length) {
            return;
        }

        if (selected && selected.id) {
            var existing = $el.find('option[value="' + selected.id + '"]');
            if (!existing.length) {
                var label = selected.text
                    || ((selected.name || '') + (selected.ref ? ' (' + selected.ref + ')' : ''));
                var option = new Option(label, selected.id, true, true);
                applyProductDataToOption(option, selected);
                $el.append(option);
            } else {
                applyProductDataToOption(existing.get(0), selected);
                existing.prop('selected', true);
            }
            cacheCommercialProduct(selected);
        }

        if (typeof window.initProductSelect2 === 'function') {
            window.initProductSelect2($el, {
                width: '15rem',
                onSelect: function (data) {
                    var option = $el.find('option:selected').get(0);
                    applyProductDataToOption(option, data);
                    cacheCommercialProduct(data);
                    window.fillCommercialProductDetails($el.get(0), index);
                }
            });
            return;
        }

        $el.select2({
            placeholder: 'Rechercher un produit...',
            allowClear: true,
            width: '15rem',
            language: {
                noResults: function () { return 'Aucun produit trouvé'; },
                searching: function () { return 'Recherche...'; }
            }
        });
    };

    window.selectedCommercialProduct = selectedProductFromItem;

    function vatRateFromCategory(label) {
        if (!label) return 20;
        var match = String(label).match(/(\d+(?:[.,]\d+)?)\s*%/);
        return match ? parseFloat(match[1].replace(',', '.')) : 20;
    }

    function purchaseUnitPriceTtc(product, taxRate) {
        var lastPurchase = parseFloat(product.last_purchase_price || 0) || 0;
        if (lastPurchase > 0) {
            return lastPurchase;
        }

        var priceTtc = parseFloat(product.cost_price_ttc || 0) || 0;
        if (priceTtc > 0) {
            return priceTtc;
        }

        var priceHt = parseFloat(product.cost_price_ht || product.sale_price_ht || 0) || 0;
        if (priceHt > 0) {
            return priceHt * (1 + taxRate / 100);
        }

        priceTtc = parseFloat(product.sale_price || 0) || 0;
        if (priceTtc > 0) {
            return priceTtc;
        }

        return 0;
    }

    window.getCommercialUnitPrice = function (product, taxRate) {
        var cfg = config();
        var priceHt = parseFloat(product.sale_price_ht || product.cost_price_ht || 0) || 0;
        var priceTtc = parseFloat(product.sale_price || 0) || 0;

        if (cfg.priceMode === 'purchase') {
            return purchaseUnitPriceTtc(product, taxRate);
        }

        if (priceHt > 0) {
            return priceHt;
        }

        if (priceTtc > 0) {
            return priceTtc / (1 + taxRate / 100);
        }

        return 0;
    };

    window.fillCommercialProductDetails = function (selectElement, index) {
        var cfg = config();
        var selectedOption = selectElement.options[selectElement.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        var ref = selectedOption.getAttribute('data-ref') || '';
        var name = selectedOption.getAttribute('data-text') || selectedOption.getAttribute('data-name') || '';
        var productId = selectedOption.getAttribute('data-product-id') || selectedOption.value;
        var variantId = selectedOption.getAttribute('data-product-variant-id') || '';
        var vatCategory = selectedOption.getAttribute('data-vat') || '';
        var taxRate = vatRateFromCategory(vatCategory);

        var product = cfg.products.find(function (p) {
            return String(p.id) === String(selectedOption.value);
        }) || {
            sale_price_ht: selectedOption.getAttribute('data-price-ht'),
            sale_price: selectedOption.getAttribute('data-price-ttc'),
            cost_price_ht: selectedOption.getAttribute('data-cost-ht'),
            cost_price_ttc: selectedOption.getAttribute('data-cost-ttc'),
            last_purchase_price: selectedOption.getAttribute('data-last-purchase'),
        };

        var unitPrice = window.getCommercialUnitPrice(product, taxRate);

        var refEl = document.getElementById('ref_' + index);
        var designationEl = document.getElementById('designation_' + index);
        var priceEl = document.getElementById('price_' + index);
        var taxEl = document.querySelector('[name="items[' + index + '][tax_rate]"]');
        var variantEl = document.getElementById('product_variant_id_' + index);
        var productIdEl = document.querySelector('[name="items[' + index + '][product_id]"]');

        if (refEl) refEl.value = ref;
        if (designationEl) designationEl.value = name;
        if (variantEl) variantEl.value = variantId;
        if (productIdEl && productId) productIdEl.value = productId;
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

    function lineBreakdown(quantity, unitPrice, taxRate, discountInput, discountType, priceMode) {
        var lineBase = quantity * unitPrice;
        var discountAmount = discountType === 'percent'
            ? lineBase * (discountInput / 100)
            : discountInput;

        if (priceMode === 'purchase') {
            var lineTtc = Math.max(0, lineBase - discountAmount);
            var lineHt = taxRate > 0 ? lineTtc / (1 + taxRate / 100) : lineTtc;
            var lineTax = lineTtc - lineHt;

            return {
                lineHt: lineHt,
                lineTax: lineTax,
                discountAmount: discountAmount,
            };
        }

        var lineHt = Math.max(0, lineBase - discountAmount);
        var lineTax = lineHt * (taxRate / 100);

        return {
            lineHt: lineHt,
            lineTax: lineTax,
            discountAmount: discountAmount,
        };
    }

    window.calculateCommercialTotal = function () {
        var rows = document.querySelectorAll('#itemsBody tr');
        var totalHT = 0;
        var totalDiscount = 0;
        var totalTax = 0;
        var cfg = config();

        rows.forEach(function (row) {
            var quantityEl = row.querySelector('[name*="[quantity]"]');
            var unitPriceEl = row.querySelector('[name*="[unit_price]"]');
            var taxRateEl = row.querySelector('[name*="[tax_rate]"]');
            var discountEl = row.querySelector('[name*="[discount]"]');
            var discountTypeEl = row.querySelector('[name*="[discount_type]"]');
            var quantity = parseFloat(quantityEl && quantityEl.value) || 0;
            var unitPrice = parseFloat(unitPriceEl && unitPriceEl.value) || 0;
            var taxRate = parseFloat(taxRateEl && taxRateEl.value) || 0;
            var discountInput = parseFloat(discountEl && discountEl.value) || 0;
            var discountType = (discountTypeEl && discountTypeEl.value) || 'fixed';

            var breakdown = lineBreakdown(
                quantity,
                unitPrice,
                taxRate,
                discountInput,
                discountType,
                cfg.priceMode
            );

            totalHT += breakdown.lineHt;
            totalDiscount += breakdown.discountAmount;
            totalTax += breakdown.lineTax;
        });

        var itemsTtc = totalHT + totalTax;
        var adj = window.sumInvoiceAdjustments ? window.sumInvoiceAdjustments() : { positive: 0, negative: 0 };
        var totalTTC = itemsTtc + adj.positive - adj.negative;

        var subtotalEl = document.getElementById('subtotal');
        var discountEl = document.getElementById('discount');
        var taxEl = document.getElementById('taxAmount');
        var itemsTtcEl = document.getElementById('itemsTtc');
        var posEl = document.getElementById('adjustmentsPositive');
        var negEl = document.getElementById('adjustmentsNegative');
        var totalEl = document.getElementById('total');

        if (subtotalEl) subtotalEl.textContent = totalHT.toFixed(2);
        if (discountEl) discountEl.textContent = totalDiscount.toFixed(2);
        if (taxEl) taxEl.textContent = totalTax.toFixed(2);
        if (itemsTtcEl) itemsTtcEl.textContent = itemsTtc.toFixed(2);
        if (posEl) posEl.textContent = adj.positive.toFixed(2);
        if (negEl) negEl.textContent = adj.negative.toFixed(2);
        if (totalEl) totalEl.textContent = totalTTC.toFixed(2);
    };

    var adjustmentIndex = 0;
    var adjustmentsBooted = false;

    function adjustmentLineImpact(row) {
        var amount = Math.abs(parseFloat(row.querySelector('[name*="[amount]"]') && row.querySelector('[name*="[amount]"]').value) || 0);
        var taxable = (row.querySelector('[name*="[is_taxable]"]') && row.querySelector('[name*="[is_taxable]"]').value) === '1';
        var rate = parseFloat(row.querySelector('[name*="[tax_rate]"]') && row.querySelector('[name*="[tax_rate]"]').value) || 0;
        var tax = taxable ? amount * (rate / 100) : 0;
        return Math.round((amount + tax) * 100) / 100;
    }

    window.sumInvoiceAdjustments = function () {
        var positive = 0;
        var negative = 0;
        document.querySelectorAll('#adjustmentsBody tr').forEach(function (row) {
            var typeEl = row.querySelector('[name*="[type]"]');
            var impact = adjustmentLineImpact(row);
            if ((typeEl && typeEl.value) === 'deduct') {
                negative += impact;
            } else {
                positive += impact;
            }
        });
        return { positive: positive, negative: negative };
    };

    window.toggleAdjustmentTaxRate = function (select) {
        var row = select.closest('tr');
        var rateWrap = row.querySelector('.adjustment-tax-rate');
        if (rateWrap) {
            rateWrap.style.display = select.value === '1' ? '' : 'none';
        }
        window.calculateCommercialTotal();
    };

    window.removeInvoiceAdjustment = function (button) {
        button.closest('tr').remove();
        window.calculateCommercialTotal();
    };

    window.addInvoiceAdjustment = function (data) {
        var tbody = document.getElementById('adjustmentsBody');
        if (!tbody) return;
        data = data || {};
        var index = adjustmentIndex++;
        var type = data.type === 'deduct' ? 'deduct' : 'add';
        var taxable = data.is_taxable ? '1' : '0';
        var taxRate = data.tax_rate != null ? data.tax_rate : 20;
        var row = document.createElement('tr');
        row.className = 'border-b border-gray-200';
        row.innerHTML =
            '<td class="px-4 py-3"><input type="text" name="adjustments[' + index + '][label]" value="' + (data.label || '').replace(/"/g, '&quot;') + '" required class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Libellé libre"></td>' +
            '<td class="px-4 py-3"><select name="adjustments[' + index + '][type]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateCommercialTotal()">' +
                '<option value="add"' + (type === 'add' ? ' selected' : '') + '>+ Ajouter au total</option>' +
                '<option value="deduct"' + (type === 'deduct' ? ' selected' : '') + '>− Déduire du total</option>' +
            '</select></td>' +
            '<td class="px-4 py-3"><input type="number" step="0.01" min="0" name="adjustments[' + index + '][amount]" value="' + (data.amount != null ? data.amount : 0) + '" required class="w-28 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateCommercialTotal()"></td>' +
            '<td class="px-4 py-3">' +
                '<select name="adjustments[' + index + '][is_taxable]" class="px-2 py-1 border border-gray-300 rounded text-sm" onchange="toggleAdjustmentTaxRate(this)">' +
                    '<option value="0"' + (taxable === '0' ? ' selected' : '') + '>Non</option>' +
                    '<option value="1"' + (taxable === '1' ? ' selected' : '') + '>Oui</option>' +
                '</select>' +
                '<input type="number" step="0.01" min="0" name="adjustments[' + index + '][tax_rate]" value="' + taxRate + '" class="adjustment-tax-rate mt-1 w-20 px-2 py-1 border border-gray-300 rounded text-sm" style="display:' + (taxable === '1' ? '' : 'none') + '" onchange="calculateCommercialTotal()">' +
            '</td>' +
            '<td class="px-4 py-3"><button type="button" onclick="removeInvoiceAdjustment(this)" class="text-red-600 hover:text-red-800">Supprimer</button></td>';
        tbody.appendChild(row);
        window.calculateCommercialTotal();
    };

    function bootInvoiceAdjustments() {
        if (adjustmentsBooted) return;
        var section = document.getElementById('adjustmentsSection');
        if (!section) return;
        adjustmentsBooted = true;
        var addBtn = document.getElementById('addAdjustmentBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                window.addInvoiceAdjustment({});
            });
        }
        var existing = window.existingInvoiceAdjustments || [];
        existing.forEach(function (row) {
            window.addInvoiceAdjustment(row);
        });
    }

    if (window.SoftNav && typeof SoftNav.whenReady === 'function') {
        SoftNav.whenReady(bootInvoiceAdjustments);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootInvoiceAdjustments);
    } else {
        bootInvoiceAdjustments();
    }

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
