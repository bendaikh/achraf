<style>
    @page { margin: 16mm 12mm 16mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; }
    .facture-doc { font-size: 11px; color: #111; }
    .facture-header-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; border-bottom: 3px solid #111; }
    .facture-header-table td { vertical-align: top; padding-bottom: 10px; }
    .facture-header-table td.facture-logo-cell { width: 150px; padding-right: 10px; }
    .facture-logo { width: 140px; text-align: center; }
    .facture-logo img { width: 140px; height: auto; display: block; margin: 0 auto; }
    .facture-logo-placeholder { font-size: 9px; font-weight: 700; color: #9ca3af; padding-top: 28px; display: block; min-height: 70px; }
    .facture-company-name { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
    .facture-company-subtitle { font-size: 11px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .facture-contact-line { font-size: 10px; color: #374151; margin-bottom: 3px; }
    .facture-legal-item { font-size: 9px; color: #374151; display: inline-block; margin-right: 12px; margin-top: 4px; }
    .facture-meta { text-align: right; }
    .facture-title { font-size: 30px; font-weight: bold; margin-bottom: 8px; }
    .facture-number-badge {
        display: inline-block;
        background: #fdb819;
        color: #111;
        font-weight: bold;
        font-size: 13px;
        padding: 6px 14px;
        border-radius: 6px;
        margin-bottom: 6px;
    }
    .facture-date-line { font-size: 11px; font-weight: 600; color: #374151; margin-top: 3px; }
    .facture-client-tab {
        background: #fdb819;
        color: #111;
        font-weight: bold;
        font-size: 10px;
        text-transform: uppercase;
        padding: 6px 14px;
        display: inline-block;
    }
    .facture-client-box { border: 2px solid #111; border-top: none; padding: 12px 14px; }
    .facture-client-name { font-size: 14px; font-weight: bold; margin-bottom: 6px; }
    .facture-client-line { font-size: 10px; color: #374151; margin-bottom: 3px; }
    .facture-items {
        width: 527pt;
        max-width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
        table-layout: fixed;
    }
    .facture-items thead { display: table-header-group; background: #fdb819; color: #111; }
    .facture-items tr,
    .facture-items td,
    .facture-items th { page-break-inside: avoid; }
    .facture-items th {
        padding: 8px 5px;
        text-align: left;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        border: 1px solid #e5a617;
        vertical-align: middle;
        overflow: hidden;
    }
    .facture-items th.text-left, .facture-items td.text-left { text-align: left; }
    .facture-items th.text-right, .facture-items td.text-right { text-align: right; }
    .facture-items th.text-center, .facture-items td.text-center { text-align: center; }
    .facture-items td {
        padding: 7px 5px;
        font-size: 10px;
        border: 1px solid #e5e7eb;
        vertical-align: top;
        overflow: hidden;
        word-wrap: break-word;
    }
    .facture-items tr.facture-origin-group td {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        padding: 6px 8px;
        font-size: 10px;
        font-weight: 600;
        color: #111;
    }
    .facture-origin-header { width: 100%; border-collapse: collapse; }
    .facture-origin-header td { border: none !important; padding: 0 !important; background: transparent !important; font-size: 10px; }
    .facture-origin-left { text-align: left; }
    .facture-origin-right { text-align: right; white-space: nowrap; width: 120px; }
    .facture-totals-wrap { width: 100%; border-collapse: collapse; margin: 0; }
    .facture-totals-wrap td { vertical-align: top; padding: 0; }
    .facture-totals-spacer { width: 58%; }
    .facture-totals-cell { width: 42%; }
    .facture-totals-block { margin-top: 8px; page-break-inside: avoid; break-inside: avoid; }
    .facture-notes-box { border: 2px solid #111; border-radius: 6px; padding: 8px 10px; margin-top: 2px; page-break-inside: avoid; break-inside: avoid; }
    .facture-notes-title { font-size: 9px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
    .facture-notes-body { font-size: 10px; white-space: pre-line; color: #374151; }
    .facture-amount-words { margin-top: 8px; margin-bottom: 8px; font-size: 10px; font-weight: 600; page-break-inside: avoid; break-inside: avoid; }
    .facture-totals { width: 100%; border-collapse: collapse; }
    .facture-totals td { padding: 5px 10px; font-size: 11px; border-bottom: 1px solid #e5e7eb; }
    .facture-totals tr.grand td { background: #fdb819; font-weight: bold; font-size: 13px; border: none; }
    .facture-closing-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .facture-closing-table > tbody > tr > td { vertical-align: top; padding: 0; }
    .facture-closing-left { padding-right: 12px !important; }
    .facture-closing-right { padding-left: 4px !important; }
    .facture-settlement-box {
        border: 2px solid #111;
        border-radius: 6px;
        padding: 8px 10px;
    }
    .facture-settlement-title {
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 6px;
        padding-bottom: 3px;
        border-bottom: 1px solid #e5e7eb;
    }
    .facture-settlement-line { font-size: 10px; color: #111; margin-bottom: 2px; }
    .facture-settlement-payment {
        margin-top: 6px;
        padding-top: 4px;
        border-top: 1px dashed #d1d5db;
    }
    .facture-settlement-payment-title {
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 3px;
        color: #374151;
    }
    .facture-settlement-total { margin-top: 6px; font-size: 11px; }
    .facture-settlement-remaining { margin-top: 4px; font-size: 11px; font-weight: 600; }
    .facture-signature-label { font-size: 9px; font-weight: bold; text-transform: uppercase; text-align: center; margin-bottom: 4px; }
    .facture-signature-box { height: 110px; border: 2px solid #111; border-radius: 6px; text-align: center; padding: 0; overflow: hidden; }
    .facture-signature-box-table { width: 100%; height: 110px; border-collapse: collapse; }
    .facture-signature-box-table td { text-align: center; vertical-align: middle; padding: 1px 2px; }
    .facture-signature-box .facture-cachet-img { display: inline-block; margin: 0 auto; }
    .facture-footer-meta { font-size: 9px; color: #6b7280; margin-top: 14px; }
    .facture-accent-bar { height: 10px; margin-top: 10px; background-color: #fdb819; border-top: 10px solid #111; }
    .facture-page-footer { margin: 0; padding: 0; }
    @if(!empty($forPdf) && !empty($pdfFooter))
    @page { margin: 16mm 12mm {{ $pdfFooter['reserve_mm'] }}mm 12mm; }
    .facture-page-footer {
        position: fixed;
        left: 0;
        right: 0;
        width: 100%;
        bottom: -{{ $pdfFooter['pull_mm'] }}mm;
    }
    .facture-page-footer .facture-closing-table { margin-top: 0; }
    @endif
</style>
