<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>@yield('title', 'BoldMark PMS') — BoldMark PMS</title>
</head>
<body style="margin:0;padding:0;background-color:#F0F4F8;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">

  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F0F4F8;padding:40px 16px;">
    <tr>
      <td align="center">

        <!-- Card -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;">

          <!-- Header -->
          <tr>
            <td align="center" style="background-color:#0B1F38;border-radius:8px 8px 0 0;padding:32px 40px;">
              <!-- Wordmark -->
              <p style="margin:0;font-size:22px;font-weight:800;color:#ffffff;letter-spacing:3px;text-transform:uppercase;line-height:1;">BOLD MARK</p>
              <p style="margin:4px 0 0;font-size:10px;font-weight:600;color:#D89B4B;letter-spacing:4px;text-transform:uppercase;">PROPERTIES</p>

              <!-- Amber divider -->
              <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin-top:24px;">
                <tr>
                  <td style="width:32px;height:2px;background-color:#D89B4B;border-radius:1px;"></td>
                </tr>
              </table>

              <p style="margin:16px 0 0;color:rgba(255,255,255,0.5);font-size:11px;letter-spacing:2px;text-transform:uppercase;">@yield('section_label', 'Notification')</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="background-color:#ffffff;padding:40px 40px 32px;border-left:1px solid #E2E8F0;border-right:1px solid #E2E8F0;">
              @yield('content')
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#F8FBFF;border:1px solid #E2E8F0;border-top:none;border-radius:0 0 8px 8px;padding:24px 40px;">
              <p style="margin:0 0 2px;font-size:12px;font-weight:600;color:#1E2740;">Bold Mark Properties</p>
              <p style="margin:0 0 16px;font-size:11px;color:#A0AEC0;line-height:1.6;">
                112 Boeing Rd, Bedfordview, Johannesburg &nbsp;·&nbsp; NAMA-9141 &nbsp;·&nbsp; PPRA Registered
              </p>
              <p style="margin:0;font-size:11px;color:#DCDEE8;text-align:center;">
                © {{ date('Y') }} Bold Mark Properties. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
        <!-- /Card -->

      </td>
    </tr>
  </table>

</body>
</html>
