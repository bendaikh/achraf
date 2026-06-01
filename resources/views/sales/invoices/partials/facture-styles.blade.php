<style>
    @page { margin: 16mm 12mm 50mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; }
    .facture-doc { font-size: 11px; color: #111; }
    .facture-header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border-bottom: 3px solid #111; }
    .facture-header-table td { vertical-align: top; padding-bottom: 14px; }
    .facture-header-table td.facture-logo-cell { width: 150px; padding-right: 10px; }
    .facture-logo {
        width: 140px;
        text-align: center;
    }
    .facture-logo img {
        width: 140px;
        height: auto;
        display: block;
        margin: 0 auto;
    }
    .facture-logo-placeholder { font-size: 9px; font-weight: 700; color: #9ca3af; padding-top: 62px; display: block; min-height: 140px; }
    .facture-company-name { font-size: 22px; font-weight: bold; margin-bottom: 6px; }
    .facture-contact-line { font-size: 10px; color: #374151; margin-bottom: 3px; }
    .facture-legal-item { font-size: 9px; font-weight: 600; color: #374151; display: inline-block; margin-right: 12px; margin-top: 4px; }
    .facture-legal-dot {
        display: inline-table;
        width: 16px;
        height: 16px;
        margin-right: 4px;
        vertical-align: middle;
        border-collapse: collapse;
    }
    .facture-legal-dot td {
        width: 16px;
        height: 16px;
        background: #fdb819;
        border-radius: 50%;
        text-align: center;
        vertical-align: middle;
        font-size: 8px;
        font-weight: bold;
        color: #111;
        line-height: 1;
        padding: 0;
    }
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
    .facture-client-box {
        border: 2px solid #111;
        border-top: none;
        padding: 12px 14px;
    }
    .facture-client-name { font-size: 14px; font-weight: bold; margin-bottom: 6px; }
    .facture-client-line { font-size: 10px; color: #374151; margin-bottom: 3px; }
    .facture-items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .facture-items thead { background: #fdb819; color: #111; }
    .facture-items th {
        padding: 8px 5px;
        text-align: left;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        border: 1px solid #e5a617;
        vertical-align: middle;
    }
    .facture-items th.text-right, .facture-items td.text-right { text-align: right; }
    .facture-items th.text-center, .facture-items td.text-center { text-align: center; }
    .facture-items td { padding: 7px 5px; font-size: 10px; border: 1px solid #e5e7eb; vertical-align: top; }
    .facture-items tbody tr.empty-row td { height: 24px; }
    .facture-bottom-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .facture-bottom-table td { vertical-align: top; }
    .facture-notes-box { border: 2px solid #111; border-radius: 6px; padding: 10px 12px; min-height: 90px; }
    .facture-notes-title { font-size: 9px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; }
    .facture-notes-body { font-size: 10px; white-space: pre-line; color: #374151; }
    .facture-amount-words { margin-top: 10px; font-size: 10px; font-weight: 600; }
    .facture-totals { width: 100%; border-collapse: collapse; }
    .facture-totals td { padding: 6px 10px; font-size: 11px; border-bottom: 1px solid #e5e7eb; }
    .facture-totals tr.grand td { background: #fdb819; font-weight: bold; font-size: 13px; border: none; }
    .facture-footer-table { width: 100%; border-collapse: collapse; margin-top: 24px; border-top: 2px solid #111; }
    .facture-footer-table td { padding-top: 12px; vertical-align: bottom; }
    .facture-footer-meta { font-size: 10px; color: #6b7280; }
    .facture-signature-label { font-size: 9px; font-weight: bold; text-transform: uppercase; text-align: center; margin-bottom: 6px; }
    .facture-signature-box {
        height: 130px;
        border: 2px solid #111;
        border-radius: 6px;
        text-align: center;
        padding: 0;
        overflow: hidden;
    }
    .facture-signature-box-table {
        width: 100%;
        height: 130px;
        border-collapse: collapse;
    }
    .facture-signature-box-table td {
        text-align: center;
        vertical-align: middle;
        padding: 1px 2px;
    }
    .facture-signature-box .facture-cachet-img {
        display: inline-block;
        margin: 0 auto;
    }
    .facture-accent-bar { height: 10px; margin-top: 16px; background-color: #fdb819; border-top: 10px solid #111; }

    .facture-footer-fixed {
        position: fixed;
        left: 0;
        right: 0;
        bottom: -35mm;
        height: 40mm;
    }

    .facture-footer-spacer { height: 20px; }
</style>
