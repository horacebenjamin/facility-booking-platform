<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title') - Facility4Hire</title>
    <style>
        @page { margin: 48px 46px 70px; }
        body { font-family: DejaVu Sans, sans-serif; color: #243247; font-size: 10px; line-height: 1.5; }
        .brand { color: #183854; font-size: 17px; font-weight: bold; letter-spacing: .3px; }
        .brand span { color: #b67816; }
        .rule { border-bottom: 2px solid #b67816; margin: 12px 0 24px; }
        header { page-break-after: avoid; }
        h1 { color: #183854; font-size: 23px; margin: 0 0 4px; line-height: 1.2; }
        h2 { color: #183854; font-size: 12px; margin: 24px 0 8px; }
        .muted { color: #64748b; }
        .document-reference { color: #64748b; font-size: 9px; overflow-wrap: anywhere; word-wrap: break-word; }
        .document-reference strong { color: #243247; font-weight: bold; }
        .intro { width: 100%; margin-bottom: 22px; }
        .intro td { vertical-align: top; width: 50%; }
        .right { text-align: right; }
        .label { color: #64748b; text-transform: uppercase; font-size: 8px; letter-spacing: .6px; }
        .value { font-size: 11px; font-weight: bold; overflow-wrap: anywhere; word-wrap: break-word; }
        .space { height: 10px; }
        table.items { border-collapse: collapse; width: 100%; margin: 12px 0; }
        .items thead { display: table-header-group; }
        .items th { background: #edf2f7; color: #183854; text-align: left; font-size: 8px; text-transform: uppercase; padding: 8px; }
        .items td { border-bottom: 1px solid #dbe3eb; padding: 9px 8px; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        .items th.right, .items td.right { text-align: right; }
        .items tr { page-break-inside: avoid; }
        .totals { margin: 16px 0 0 auto; width: 46%; border-collapse: collapse; page-break-inside: avoid; }
        .totals td { padding: 5px 8px; }
        .totals .final { border-top: 2px solid #183854; background: #edf2f7; font-weight: bold; font-size: 12px; }
        .balance-summary { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 12px -8px 18px; page-break-inside: avoid; }
        .balance-summary td { width: 50%; padding: 11px 12px; background: #f6f8fa; border: 1px solid #dbe3eb; vertical-align: top; }
        .balance-summary .balance { color: #183854; font-size: 13px; font-weight: bold; margin-top: 3px; }
        .amount-highlight { padding: 11px 12px; border: 1px solid #dbe3eb; background: #edf2f7; color: #183854; font-size: 15px; font-weight: bold; text-align: right; }
        .note { margin-top: 18px; padding: 10px 12px; background: #f6f8fa; color: #45566b; page-break-inside: avoid; }
        footer { position: fixed; bottom: -38px; left: 0; right: 0; border-top: 1px solid #dbe3eb; padding-top: 8px; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    <header><div class="brand">Facility<span>4</span>Hire</div><div class="rule"></div></header>
    @yield('body')
    <footer>Facility4Hire - Generated from the account record - Keep this document for your records.</footer>
</body>
</html>
