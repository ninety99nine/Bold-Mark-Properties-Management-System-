@extends('emails.layout')

@section('title', 'Reset your password')
@section('section_label', 'Account Security')

@section('content')
  <h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1E2740;line-height:1.3;">Reset your password</h1>
  <p style="margin:0 0 24px;font-size:15px;color:#717B99;line-height:1.6;">
    Hi {{ $name }},
  </p>
  <p style="margin:0 0 32px;font-size:15px;color:#4A5568;line-height:1.7;">
    We received a request to reset the password for your <strong style="color:#1E2740;">BoldMark PMS</strong> account.
    Click the button below to choose a new password. This link will expire in <strong style="color:#1E2740;">24 hours</strong>.
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
@endsection
