<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LAV\'FAST')</title>
    <script>
        (function () {
            try {
                document.documentElement.classList.toggle(
                    'sidebar-collapsed',
                    localStorage.getItem('sidebarCollapsed') === 'true'
                );
                var ua = navigator.userAgent || '';
                var mobile = /Android|iPhone|iPad|iPod/i.test(ua)
                    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                document.documentElement.classList.add(mobile ? 'lm-is-mobile' : 'lm-is-desktop');
            } catch (e) {}
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        [x-cloak] {
            display: none !important;
        }
        html.lm-is-desktop .lm-scan-mobile-only { display: none !important; }
        html.lm-is-mobile .lm-scan-desktop-only { display: none !important; }
        html.lm-is-mobile .lm-scan-mobile-only { display: flex !important; }
        
        /* Select2 custom styling */
        .select2-container--default .select2-selection--single {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            height: 38px;
            padding: 4px 8px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
            color: #374151;
            font-size: 0.875rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .select2-dropdown {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #3b82f6;
        }
        .select2-search--dropdown .select2-search__field {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem;
        }
    </style>
    
    <!-- jQuery (required for Select2) - Must be loaded before page scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        window.initPartySelect2 = function (selector, options) {
            options = options || {};
            var $el = $(selector);
            if (!$el.length) {
                return $el;
            }
            if ($el.hasClass('select2-hidden-accessible')) {
                try { $el.select2('destroy'); } catch (e) {}
            }
            var config = {
                placeholder: options.placeholder || 'Rechercher...',
                allowClear: options.allowClear !== false,
                width: options.width || '100%',
                minimumInputLength: options.minimumInputLength ?? 0,
                ajax: {
                    url: options.url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results,
                            pagination: { more: data.pagination && data.pagination.more }
                        };
                    }
                },
                language: {
                    noResults: function () { return options.noResults || 'Aucun résultat'; },
                    searching: function () { return 'Recherche...'; },
                    inputTooShort: function () { return 'Tapez pour rechercher'; }
                }
            };

            $el.select2(config);
            if ($el.closest('.party-select-wrap').length) {
                $el.next('.select2-container').css('width', '100%');
            }
            return $el;
        };

        window.initClientSelect2 = function (selector, options) {
            options = options || {};
            return window.initPartySelect2(selector, Object.assign({
                placeholder: 'Rechercher un client...',
                url: @json(route('clients.search')),
                noResults: 'Aucun client trouvé'
            }, options));
        };

        window.initSupplierSelect2 = function (selector, options) {
            options = options || {};
            return window.initPartySelect2(selector, Object.assign({
                placeholder: 'Rechercher un fournisseur...',
                url: @json(route('suppliers.search')),
                noResults: 'Aucun fournisseur trouvé'
            }, options));
        };

        window.initProductSelect2 = function (selector, options) {
            options = options || {};
            var $el = window.initPartySelect2(selector, Object.assign({
                placeholder: 'Rechercher un produit...',
                url: @json(route('catalog.products.search')),
                noResults: 'Aucun produit trouvé',
                width: options.width || '100%',
                minimumInputLength: options.minimumInputLength ?? 0
            }, options));

            if (typeof options.onSelect === 'function') {
                $el.off('select2:select.lmProduct').on('select2:select.lmProduct', function (event) {
                    options.onSelect(event.params.data || {});
                });
            }

            return $el;
        };
    </script>
</head>
<body class="bg-gray-50">
    @yield('content')
    <x-managed-scan-modal />
</body>
</html>
