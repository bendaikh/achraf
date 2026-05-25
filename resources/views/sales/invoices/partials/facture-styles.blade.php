<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; }
    .facture-doc { font-size: 11px; color: #111; }
    .facture-header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border-bottom: 3px solid #111; }
    .facture-header-table td { vertical-align: top; padding-bottom: 14px; }
    .facture-logo {
        width: 88px;
        height: 88px;
        border: 2px dashed #d1d5db;
        border-radius: 6px;
        text-align: center;
        background: #fafafa;
    }
    .facture-logo img { max-width: 84px; max-height: 84px; }
    .facture-logo-placeholder { font-size: 9px; font-weight: 700; color: #9ca3af; padding-top: 36px; display: block; }
    .facture-company-name { font-size: 22px; font-weight: bold; margin-bottom: 6px; }
    .facture-contact-line { font-size: 10px; color: #374151; margin-bottom: 3px; }
    .facture-legal-item { font-size: 9px; font-weight: 600; color: #374151; display: inline-block; margin-right: 12px; margin-top: 4px; }
    .facture-legal-dot {
        display: inline-block;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #fdb819;
        text-align: center;
        line-height: 16px;
        font-size: 7px;
        font-weight: bold;
        margin-right: 4px;
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
    .facture-client-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #6b7280; }
    .facture-client-name { font-size: 14px; font-weight: bold; margin-top: 4px; margin-bottom: 6px; }
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
    }
    .facture-items th.text-right, .facture-items td.text-right { text-align: right; }
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
    .facture-signature-box { height: 68px; border: 2px solid #111; border-radius: 6px; }
    .facture-accent-bar { height: 10px; margin-top: 16px; background-color: #fdb819; border-top: 10px solid #111; }
</style>
