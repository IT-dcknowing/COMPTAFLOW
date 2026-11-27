<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} - ComptaFlow</title>
<style>
@page { size: A4 landscape; margin: 12mm; }
* { box-sizing: border-box; }
body { margin: 0; padding: 24px; font-family: Arial, Helvetica, sans-serif; color: #172033; background: #eef2f7; }
.sheet { position: relative; max-width: 1500px; margin: auto; padding: 30px; background: #fff; box-shadow: 0 8px 30px rgba(15,23,42,.12); overflow: hidden; }
.watermark { position: fixed; top: 42%; left: 50%; transform: translate(-50%,-50%) rotate(-28deg); font-size: 100px; font-weight: 800; letter-spacing: 8px; color: rgba(31,78,121,.055); white-space: nowrap; pointer-events: none; z-index: 0; }
.header, .report { position: relative; z-index: 1; }
.brand { text-align: center; color: #1f4e78; font-size: 25px; font-weight: 800; letter-spacing: 2px; }
.company { text-align: center; margin-top: 5px; font-size: 17px; font-weight: 700; }
h1 { margin: 10px 0 6px; text-align: center; font-size: 20px; }
.meta { text-align: center; color: #64748b; font-size: 12px; line-height: 1.7; }
table { position: relative; z-index: 1; width: 100%; border-collapse: collapse; margin-top: 22px; font-size: 11px; }
th { padding: 9px 7px; color: #fff; background: #1f4e78; border: 1px solid #b7c9d6; text-align: center; }
td { padding: 7px; border: 1px solid #d9e2f3; vertical-align: top; }
td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
tr.account td { background: #d9d9d9; font-weight: 700; }
tr.opening td { background: #fff2cc; font-weight: 700; }
tr.subtotal td { background: #eaf0f6; font-weight: 700; }
tr.grand td, tr.total td { background: #d9eaf7; font-weight: 700; }
.footer { margin-top: 18px; text-align: center; color: #94a3b8; font-size: 10px; }
@media print { body { padding: 0; background: #fff; } .sheet { box-shadow: none; padding: 0; max-width: none; } .watermark { position: fixed; } }
</style>
</head>
<body>
<div class="sheet">
<div class="watermark">COMPTAFLOW</div>
<div class="header">
<div class="brand">COMPTAFLOW</div>
<div class="company">{{ $company }}</div>
<h1>{{ $title }}</h1>
<div class="meta">Période du {{ $dateDebut }} au {{ $dateFin }}<br>Plage des comptes : {{ $range }}<br>Date de tirage : {{ $printedAt }}</div>
</div>
<div class="report">
<table>
<thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
<tbody>
@foreach($rows as $row)
@php
    $first = (string)($row[0] ?? '');
    $type = str_starts_with($first, 'TOTAL GÉNÉRAL') || str_contains((string)($row[1] ?? ''), 'TOTAUX') ? 'grand' : (str_starts_with($first, 'TOTAL ') ? 'subtotal' : (str_starts_with($first, 'SOLDE INITIAL') ? 'opening' : (str_contains($first, ' — ') && count(array_filter(array_slice($row,1), fn($v)=>$v !== '' && $v !== null)) === 0 ? 'account' : '')));
@endphp
<tr class="{{ $type }}">
@foreach($row as $i => $cell)
<td class="{{ is_numeric($cell) && $i >= 2 ? 'num' : '' }}">{{ is_numeric($cell) && $i >= (count($headers)-3) ? number_format((float)$cell, 2, ',', ' ') : $cell }}</td>
@endforeach
</tr>
@endforeach
</tbody>
</table>
</div>
<div class="footer">© ComptaFlow — Document de prévisualisation Excel</div>
</div>
</body>
</html>
