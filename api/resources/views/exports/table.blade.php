<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1E2740; margin: 20px; }
  h1 { font-size: 14px; margin-bottom: 4px; color: #1F3A5C; }
  .meta { color: #717B99; font-size: 8px; margin-bottom: 14px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th { background: #1F3A5C; color: #fff; padding: 5px 6px; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
  td { padding: 4px 6px; border-bottom: 1px solid #DCDEE8; font-size: 8.5px; }
  tr:nth-child(even) td { background: #F8FBFF; }
</style>
</head>
<body>
  <h1>{{ $title }}</h1>
  @if(!empty($meta))
    <div class="meta">
      @foreach($meta as $label => $value)
        <span>{{ $label }}: <strong>{{ $value }}</strong></span>&nbsp;&nbsp;
      @endforeach
    </div>
  @endif
  <table>
    <thead>
      <tr>
        @foreach($headings as $heading)
          <th>{{ $heading }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
        <tr>
          @foreach($row as $cell)
            <td>{{ $cell ?? '—' }}</td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
