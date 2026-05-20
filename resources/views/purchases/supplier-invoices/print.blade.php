<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture Fournisseur {{ $supplierInvoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }

        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #2563eb;
        }

        .company-info {
            flex: 1;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 11px;
            color: #666;
        }

        .invoice-info {
            text-align: right;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }

        .invoice-number {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .invoice-date {
            font-size: 11px;
            color: #666;
        }

        .supplier-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
        }

        .supplier-info h3 {
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .info-item {
            font-size: 11px;
        }

        .info-label {
            color: #666;
            font-weight: 600;
        }

        .info-value {
            color: #333;
            margin-top: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        thead {
            background: #2563eb;
            color: white;
        }

        th {
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        th.text-right {
            text-align: right;
        }

        tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }

        tbody tr:last-child {
            border-bottom: 2px solid #2563eb;
        }

        td {
            padding: 10px 8px;
            font-size: 11px;
        }

        td.text-right {
            text-align: right;
        }

        .totals {
            margin-top: 20px;
            margin-left: auto;
            width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 15px;
            font-size: 12px;
        }

        .total-row.subtotal {
            background: #f8fafc;
        }

        .total-row.grand-total {
            background: #2563eb;
            color: white;
            font-weight: bold;
            font-size: 14px;
            margin-top: 5px;
            border-radius: 5px;
        }

        .notes {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .notes-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .note-section h4 {
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .note-content {
            font-size: 11px;
            color: #666;
            line-height: 1.6;
            white-space: pre-line;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #999;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }

            @page {
                margin: 20mm;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .print-button:hover {
            background: #1d4ed8;
        }

        .back-button {
            position: fixed;
            top: 20px;
            right: 140px;
            padding: 10px 20px;
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-decoration: none;
        }

        .back-button:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <a href="{{ route('supplier-invoices.index') }}" class="back-button no-print">← Retour</a>
    <button onclick="window.print()" class="print-button no-print">Imprimer</button>

    <div class="container">
        <div class="header">
            <div class="company-info">
                <div class="company-name">LAV'FAST</div>
                <div class="company-details">
                    Adresse de l'entreprise<br>
                    Ville, Code Postal<br>
                    Tél: +212 XXX XXX XXX<br>
                    Email: contact@lavfast.ma
                </div>
            </div>
            <div class="invoice-info">
                <div class="invoice-title">FACTURE FOURNISSEUR</div>
                <div class="invoice-number">{{ $supplierInvoice->invoice_number }}</div>
                <div class="invoice-date">Date: {{ $supplierInvoice->invoice_date->format('d/m/Y') }}</div>
                @if($supplierInvoice->due_date)
                <div class="invoice-date">Échéance: {{ $supplierInvoice->due_date->format('d/m/Y') }}</div>
                @endif
            </div>
        </div>

        <div class="supplier-info">
            <h3>Informations Fournisseur</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Fournisseur:</div>
                    <div class="info-value">{{ $supplierInvoice->supplier->name }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Devise:</div>
                    <div class="info-value">{{ $supplierInvoice->currency }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Emplacement de stock:</div>
                    <div class="info-value">{{ $supplierInvoice->stock_location }}</div>
                </div>
                @if($supplierInvoice->commercial_contact)
                <div class="info-item">
                    <div class="info-label">Contact commercial:</div>
                    <div class="info-value">{{ $supplierInvoice->commercial_contact }}</div>
                </div>
                @endif
                @if($supplierInvoice->model)
                <div class="info-item">
                    <div class="info-label">Modèle:</div>
                    <div class="info-value">{{ $supplierInvoice->model }}</div>
                </div>
                @endif
                @if($supplierInvoice->matricule)
                <div class="info-item">
                    <div class="info-label">Matricule:</div>
                    <div class="info-value">{{ $supplierInvoice->matricule }}</div>
                </div>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Réf</th>
                    <th style="width: 35%;">Désignation</th>
                    <th class="text-right" style="width: 10%;">Qté</th>
                    <th class="text-right" style="width: 15%;">Prix Unit.</th>
                    <th class="text-right" style="width: 10%;">TVA</th>
                    <th class="text-right" style="width: 10%;">Remise</th>
                    <th class="text-right" style="width: 15%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($supplierInvoice->items as $item)
                <tr>
                    <td>{{ $item->ref ?? '-' }}</td>
                    <td>{{ $item->designation }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ $item->tax_rate }}%</td>
                    <td class="text-right">{{ number_format($item->discount, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($item->line_total, 2) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row subtotal">
                <span>Sous-total:</span>
                <span>{{ number_format($supplierInvoice->subtotal, 2) }} {{ $supplierInvoice->currency }}</span>
            </div>
            @if($supplierInvoice->discount > 0)
            <div class="total-row">
                <span>Remise:</span>
                <span>{{ number_format($supplierInvoice->discount, 2) }} {{ $supplierInvoice->currency }}</span>
            </div>
            @endif
            @if($supplierInvoice->adjustment != 0)
            <div class="total-row">
                <span>Ajustement:</span>
                <span>{{ number_format($supplierInvoice->adjustment, 2) }} {{ $supplierInvoice->currency }}</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>{{ number_format($supplierInvoice->total, 2) }} {{ $supplierInvoice->currency }}</span>
            </div>
        </div>

        @if($supplierInvoice->remarks || $supplierInvoice->conditions)
        <div class="notes">
            <div class="notes-grid">
                @if($supplierInvoice->remarks)
                <div class="note-section">
                    <h4>Remarques</h4>
                    <div class="note-content">{{ $supplierInvoice->remarks }}</div>
                </div>
                @endif
                @if($supplierInvoice->conditions)
                <div class="note-section">
                    <h4>Conditions</h4>
                    <div class="note-content">{{ $supplierInvoice->conditions }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <div class="footer">
            Document généré le {{ now()->format('d/m/Y à H:i') }}
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
