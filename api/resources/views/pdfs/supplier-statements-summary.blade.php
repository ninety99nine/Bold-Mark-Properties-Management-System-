<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Supplier Statements — {{ $community->name }}</title>
  @php
    $fmt = fn ($v) => number_format((float) $v, 2, '.', ' ');
  @endphp
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; background: #fff; }
    .page { padding: 30px 42px 60px 42px; }
    table { border-collapse: collapse; width: 100%; }
    .head { margin-bottom: 26px; }
    .head td { vertical-align: middle; font-size: 15px; color: #555; }
    .head .c { text-align: center; }
    .head .r { text-align: right; }
    .list td, .list th { border: 1px solid #cfcfcf; padding: 7px 10px; font-size: 10px; }
    .list th { text-align: left; font-weight: normal; color: #333; }
    .list th.num, .list td.num { text-align: right; white-space: nowrap; }
    .list .totals td { font-weight: normal; }
    .list .totals .lbl { text-align: right; }
    .page-foot { position: fixed; left: 0; right: 0; bottom: 20px; text-align: center; font-size: 9px; color: #666; }
  </style>
</head>
<body>
<div class="page">
  {{-- Header --}}
  <table class="head">
    <tr>
      <td style="width: 25%;">&nbsp;</td>
      <td class="c" style="width: 50%;">{{ $community->name }}</td>
      <td class="r" style="width: 25%;">Suppliers Statements</td>
    </tr>
  </table>

  {{-- Listing --}}
  <table class="list">
    <thead>
      <tr>
        <th style="width: 40%;">Supplier</th>
        <th style="width: 32%;">Reference</th>
        <th class="num" style="width: 28%;">Balance as @ {{ $dateLabel }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        @php
          $label = $r['supplier_code'] ? $r['supplier_code'] . ': ' . $r['supplier_name'] : $r['supplier_name'];
        @endphp
        <tr>
          <td>{{ $label }}</td>
          <td>{{ $r['reference'] }}</td>
          <td class="num">{{ $fmt($r['balance']) }}</td>
        </tr>
      @empty
        <tr><td colspan="3" style="text-align:center; padding: 24px;">No suppliers match the current filters.</td></tr>
      @endforelse
      <tr class="totals">
        <td>&nbsp;</td>
        <td class="lbl">Totals</td>
        <td class="num">{{ $fmt($total) }}</td>
      </tr>
    </tbody>
  </table>
</div>

<div class="page-foot">
  <div>Powered by {{ $organization?->display_name ?? 'Bold Mark Properties' }}</div>
</div>

{{-- Reliable "Page X/Y" (CSS counter(pages) resolves to 0 in DomPDF). --}}
<script type="text/php">
  if (isset($pdf)) {
    $text = "Page {PAGE_NUM}/{PAGE_COUNT}";
    $font = $fontMetrics->getFont("DejaVu Sans", "normal");
    $size = 9;
    $w = $fontMetrics->getTextWidth($text, $font, $size);
    $x = ($pdf->get_width() - $w) / 2;
    $y = $pdf->get_height() - 30;
    $pdf->page_text($x, $y, $text, $font, $size, array(0.4, 0.4, 0.4));
  }
</script>
</body>
</html>
