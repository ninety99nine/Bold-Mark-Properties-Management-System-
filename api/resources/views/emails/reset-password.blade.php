<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>Reset your password — BoldMark PMS</title>
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
              <table cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td>
                    <!-- Wordmark -->
                    <table cellpadding="0" cellspacing="0" border="0" align="center">
                      <tr>
                        <td style="padding-right:12px;border-right:1px solid rgba(255,255,255,0.2);">
                          <span style="color:#ffffff;font-size:15px;font-weight:700;letter-spacing:0.5px;">BOLD MARK</span><br />
                          <span style="color:rgba(255,255,255,0.45);font-size:9px;letter-spacing:2.5px;text-transform:uppercase;">Properties</span>
                        </td>
                        <td style="padding-left:12px;">
                          <span style="color:rgba(255,255,255,0.35);font-size:9px;letter-spacing:2px;text-transform:uppercase;">Property Management</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <!-- Amber divider -->
              <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin-top:24px;">
                <tr>
                  <td style="width:32px;height:2px;background-color:#D89B4B;border-radius:1px;"></td>
                </tr>
              </table>

              <p style="margin:16px 0 0;color:rgba(255,255,255,0.5);font-size:11px;letter-spacing:2px;text-transform:uppercase;">Account Security</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="background-color:#ffffff;padding:40px 40px 32px;border-left:1px solid #E2E8F0;border-right:1px solid #E2E8F0;">

              <h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1E2740;line-height:1.3;">Reset your password</h1>
              <p style="margin:0 0 24px;font-size:15px;color:#717B99;line-height:1.6;">
                Hi {{ $name }},
              </p>
              <p style="margin:0 0 32px;font-size:15px;color:#4A5568;line-height:1.7;">
                We received a request to reset the password for your <strong style="color:#1E2740;">BoldMark PMS</strong> account.
                Click the button below to choose a new password. This link will expire in <strong style="color:#1E2740;">60 minutes</strong>.
              </p>

              <!-- CTA Button -->
              <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
                <tr>
                  <td align="center" style="background-color:#D89B4B;border-radius:6px;">
                    <a href="{{ $resetUrl }}" target="_blank"
                       style="display:inline-block;padding:14px 36px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.5px;border-radius:6px;background-color:#D89B4B;">
                      Reset Password
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Security note -->
              <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:28px;border:1px solid #DCDEE8;border-radius:6px;background-color:#F8FBFF;">
                <tr>
                  <td style="padding:16px 20px;">
                    <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#1E2740;letter-spacing:0.3px;text-transform:uppercase;">Didn't request this?</p>
                    <p style="margin:0;font-size:13px;color:#717B99;line-height:1.6;">
                      If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
                    </p>
                  </td>
                </tr>
              </table>

              <!-- Fallback URL -->
              <p style="margin:0;font-size:12px;color:#A0AEC0;line-height:1.6;">
                If the button above doesn't work, copy and paste this link into your browser:
              </p>
              <p style="margin:6px 0 0;font-size:12px;line-height:1.6;">
                <a href="{{ $resetUrl }}" style="color:#D89B4B;word-break:break-all;">{{ $resetUrl }}</a>
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#F8FBFF;border:1px solid #E2E8F0;border-top:none;border-radius:0 0 8px 8px;padding:24px 40px;">
              <table cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td>
                    <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#1E2740;">Bold Mark Properties</p>
                    <p style="margin:0;font-size:11px;color:#A0AEC0;line-height:1.6;">
                      112 Boeing Rd, Bedfordview, Johannesburg &nbsp;·&nbsp; NAMA-9141 &nbsp;·&nbsp; PPRA Registered
                    </p>
                  </td>
                  <td align="right" style="vertical-align:top;">
                    <p style="margin:0;font-size:11px;color:#DCDEE8;">
                      © {{ date('Y') }} Bold Mark Properties
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
        <!-- /Card -->

      </td>
    </tr>
  </table>

</body>
</html>
