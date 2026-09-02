<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $levelTitle }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#1a2744;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
          <tr>
            <td style="background:#1a2744;padding:20px 28px;">
              <span style="font-size:20px;font-weight:bold;letter-spacing:2px;color:#ffffff;">BOLD <span style="color:#e8a040;">MARK</span></span>
              <div style="font-size:8px;letter-spacing:4px;color:#cbd3e2;margin-top:2px;">PROPERTIES</div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              <h1 style="font-size:17px;margin:0 0 16px;color:#1a2744;">{{ $levelTitle }}</h1>
              <p style="font-size:14px;line-height:1.6;margin:0 0 14px;">Dear {{ $owner?->full_name ?? 'Customer' }},</p>
              <p style="font-size:14px;line-height:1.6;margin:0 0 14px;">
                Please find attached a <strong>{{ $levelTitle }}</strong> in respect of your account
                (<strong>{{ $unit->customer_code }}</strong>, Unit {{ $unit->unit_number }}) at
                <strong>{{ $community->name }}</strong>.
              </p>
              <p style="font-size:14px;line-height:1.6;margin:0 0 14px;">
                Kindly review the attached letter and settle any outstanding balance at your earliest convenience.
                If you have already made payment, please disregard this notice.
              </p>
              <p style="font-size:14px;line-height:1.6;margin:0;">Yours faithfully,<br><strong>The Managing Agent</strong><br>Bold Mark Properties</p>
            </td>
          </tr>
          <tr>
            <td style="background:#eef1f5;padding:16px 28px;font-size:11px;color:#717b99;text-align:center;">
              Powered by Bold Mark Properties
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
