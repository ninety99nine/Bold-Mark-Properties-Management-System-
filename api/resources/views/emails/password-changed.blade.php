@extends('emails.layout')

@section('title', 'Your password was changed')
@section('section_label', 'Security Alert')

@section('content')
  <h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1E2740;line-height:1.3;">Password changed successfully</h1>
  <p style="margin:0 0 28px;font-size:15px;color:#4A5568;line-height:1.7;">
    Hi <strong style="color:#1E2740;">{{ $name }}</strong>, your account password was changed on {{ $changedAt }}.
    If you made this change, no further action is required.
  </p>

  <!-- Warning box -->
  <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:28px;border:1px solid #FBD38D;border-radius:6px;background-color:#FFFBEB;">
    <tr>
      <td style="padding:16px 20px;">
        <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#92400E;letter-spacing:0.3px;text-transform:uppercase;">Didn't make this change?</p>
        <p style="margin:0;font-size:13px;color:#78350F;line-height:1.6;">
          If you did not change your password, your account may be compromised. Please contact your system administrator immediately.
        </p>
      </td>
    </tr>
  </table>

  <!-- Account info -->
  <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:28px;border:1px solid #E2E8F0;border-radius:6px;background-color:#F8FBFF;">
    <tr>
      <td style="padding:16px 20px;">
        <p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#717B99;letter-spacing:1px;text-transform:uppercase;">Account Details</p>
        <p style="margin:0 0 2px;font-size:13px;color:#1E2740;"><strong>Email:</strong> {{ $email }}</p>
        <p style="margin:0;font-size:13px;color:#1E2740;"><strong>Changed at:</strong> {{ $changedAt }}</p>
      </td>
    </tr>
  </table>

  <p style="margin:0;font-size:12px;color:#A0AEC0;line-height:1.6;">
    This is an automated security notification. Please do not reply to this email.
  </p>
@endsection
