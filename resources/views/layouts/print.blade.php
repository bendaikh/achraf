<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('print_title', 'Document')</title>
    <style>
        :root {
            --brand-primary: #fdb819;
            --brand-primary-dark: #e5a617;
            --brand-text: #111827;
            --brand-muted: #6b7280;
            --brand-border: #e5e7eb;
            --brand-bg-soft: #fffbeb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            background: #fff;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: var(--brand-text);
            padding: 24px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .print-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
        }

        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 28px;
            padding-bottom: 18px;
            border-bottom: 3px solid var(--brand-primary);
        }

        .company-block {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            flex: 1;
            min-width: 0;
        }

        .company-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .company-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--brand-text);
            margin-bottom: 6px;
        }

        .company-details,
        .company-legal {
            font-size: 11px;
            color: var(--brand-muted);
            white-space: pre-line;
        }

        .company-legal {
            margin-top: 6px;
        }

        .document-meta {
            text-align: right;
            flex-shrink: 0;
        }

        .document-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--brand-primary-dark);
            margin-bottom: 8px;
            letter-spacing: 0.02em;
        }

        .document-number {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .document-date {
            font-size: 11px;
            color: var(--brand-muted);
        }

        .party-box {
            background: var(--brand-bg-soft);
            border: 1px solid #fde68a;
            border-left: 4px solid var(--brand-primary);
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 24px;
        }

        .party-box h3 {
            font-size: 11px;
            font-weight: 700;
            color: var(--brand-primary-dark);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 16px;
        }

        .info-label {
            font-size: 10px;
            color: var(--brand-muted);
            font-weight: 600;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 11px;
            color: var(--brand-text);
            margin-top: 2px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            table-layout: fixed;
        }

        .items-table thead {
            background: var(--brand-primary);
            color: #1f2937;
        }

        .items-table th {
            padding: 10px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .items-table th.text-right,
        .items-table td.text-right {
            text-align: right;
        }

        .items-table tbody tr {
            border-bottom: 1px solid var(--brand-border);
        }

        .items-table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .items-table td {
            padding: 9px 8px;
            font-size: 11px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .totals-wrap {
            margin-top: 8px;
            margin-left: auto;
            width: min(100%, 320px);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 14px;
            font-size: 12px;
        }

        .total-row.subtle {
            background: #f9fafb;
        }

        .total-row.grand {
            background: var(--brand-primary);
            color: #1f2937;
            font-weight: 700;
            font-size: 14px;
            margin-top: 6px;
            border-radius: 6px;
        }

        .notes-section {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid var(--brand-border);
        }

        .notes-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .note-block h4 {
            font-size: 11px;
            font-weight: 700;
            color: var(--brand-primary-dark);
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .note-block p {
            font-size: 11px;
            color: var(--brand-muted);
            white-space: pre-line;
        }

        .print-footer {
            margin-top: 36px;
            padding-top: 14px;
            border-top: 1px solid var(--brand-border);
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }

        .no-print-actions {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 50;
            display: flex;
            gap: 10px;
        }

        .btn-print {
            padding: 10px 18px;
            background: var(--brand-primary);
            color: #1f2937;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }

        .btn-print:hover {
            background: var(--brand-primary-dark);
        }

        .btn-back {
            padding: 10px 18px;
            background: #6b7280;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-back:hover {
            background: #4b5563;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .print-container {
                max-width: none;
            }

            .items-table thead {
                background: var(--brand-primary) !important;
            }

            .total-row.grand {
                background: var(--brand-primary) !important;
            }

            .party-box {
                background: var(--brand-bg-soft) !important;
            }

            @page {
                size: A4;
                margin: 15mm;
            }
        }

        @media screen {
            .print-container {
                box-shadow: 0 4px 24px rgba(0,0,0,0.08);
                padding: 32px;
                border-radius: 8px;
            }
        }
    </style>
    @stack('print_styles')
</head>
<body>
    @hasSection('print_actions')
        <div class="no-print no-print-actions">
            @yield('print_actions')
        </div>
    @endif

    <div class="print-container">
        @yield('print_content')
    </div>

    @stack('print_scripts')
</body>
</html>
