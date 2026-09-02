<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Customer Statements — {{ $community->name }}</title>
  @php
    $fmt = fn ($v) => number_format((float) $v, 2, '.', ' ');
  @endphp
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #222; background: #fff; }
    .page { padding: 34px 40px 60px 40px; }
    table { border-collapse: collapse; width: 100%; }
    .head { margin-bottom: 22px; }
    .head td { vertical-align: middle; }
    .head .title-l { font-size: 16px; color: #333; }
    .head .title-r { font-size: 16px; color: #333; text-align: right; }
    .list th { text-align: left; font-weight: bold; font-size: 10px; padding: 8px 10px; border-bottom: 1.5px solid #333; }
    .list th.num { text-align: right; white-space: nowrap; }
    .list td { padding: 7px 10px; font-size: 10px; border-bottom: 1px solid #dcdcdc; vertical-align: top; }
    .list td.num { text-align: right; white-space: nowrap; }
    .page-foot { position: fixed; left: 0; right: 0; bottom: 20px; text-align: center; font-size: 9px; color: #666; }
    .page-foot .pw { font-weight: bold; }
  </style>
</head>
<body>
<div class="page">
  {{-- Header --}}
  <table class="head">
    <tr>
      <td class="title-l">{{ $community->name }}@if($entityLabel) {{ $entityLabel }}@endif</td>
      <td class="title-r">Customer Statements</td>
    </tr>
  </table>

  {{-- Listing --}}
  <table class="list">
    <thead>
      <tr>
        <th style="width: 42%;">Customer</th>
        <th style="width: 26%;">Reference</th>
        <th class="num" style="width: 32%;">Balance as @ {{ $dateLabel }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        @php
          $name = (string) $r['customer_name'];
          if (! empty($r['is_sold']) && stripos($name, 'sold') === false) { $name .= '(SOLD)'; }
          $label = $r['customer_code'] ? $r['customer_code'] . ': ' . $name : $name;
        @endphp
        <tr>
          <td>{{ $label }}</td>
          <td>Unit No {{ $r['unit_number'] }}</td>
          <td class="num">{{ $fmt($r['balance']) }}</td>
        </tr>
      @empty
        <tr><td colspan="3" style="text-align:center; padding: 24px;">No customers match the current filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="page-foot">
  <div class="pw">Powered by {{ $organization?->display_name ?? 'Bold Mark Properties' }}</div>
</div>

{{-- Reliable "Page X/Y" (CSS counter(pages) resolves to 0 in DomPDF). --}}
<script type="text/php">
  if (isset($pdf)) {
    $text = "Page {PAGE_NUM}/{PAGE_COUNT}";
    $font = $fontMetrics->getFont("DejaVu Sans", "normal");
    $size = 9;
    $w = $fontMetrics->getTextWidth($text, $font, $size);
    $x = ($pdf->get_width() - $w) / 2;
    $y = $pdf->get_height() - 36;
    $pdf->page_text($x, $y, $text, $font, $size, array(0.4, 0.4, 0.4));
  }
</script>
</body>
</html>
