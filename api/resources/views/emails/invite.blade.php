@php
  $roleLabels = [
    'company-admin'        => 'Company Administrator',
    'portfolio-manager'    => 'Portfolio Manager',
    'financial-controller' => 'Financial Controller',
    'portfolio-assistant'  => 'Portfolio Assistant',
    'trustee'              => 'Trustee / Director',
    'owner'                => 'Property Owner',
    'tenant'               => 'Tenant',
    'contractor'           => 'Contractor',
  ];

  $internalRoles = ['company-admin', 'portfolio-manager', 'financial-controller', 'portfolio-assistant'];
  $isInternal    = in_array($role, $internalRoles);
  $roleLabel     = $roleLabels[$role] ?? ucwords(str_replace('-', ' ', $role));

  $headlines = [
    'company-admin'        => "Welcome to the team, {$name}.",
    'portfolio-manager'    => "Welcome aboard, {$name}.",
    'financial-controller' => "Welcome aboard, {$name}.",
    'portfolio-assistant'  => "Welcome to the team, {$name}.",
    'trustee'              => "Welcome, {$name}.",
    'owner'                => "Welcome to your owner portal, {$name}.",
    'tenant'               => "Welcome, {$name}.",
    'contractor'           => "Welcome to BoldMark PMS, {$name}.",
  ];

  $bodies = [
    'company-admin' =>
      "You've been added as <strong style=\"color:#1E2740;\">Company Administrator</strong> on the Bold Mark Properties Management System. You have full access to manage estates, billing, users, and all platform settings. Use the button below to set up your password and get started.",
    'portfolio-manager' =>
      "You've been invited to join as a <strong style=\"color:#1E2740;\">Portfolio Manager</strong> on the Bold Mark Properties Management System. You'll be managing estates, overseeing billing, and keeping things running smoothly for your assigned portfolios. Use the button below to activate your account.",
    'financial-controller' =>
      "You've been invited to join as a <strong style=\"color:#1E2740;\">Financial Controller</strong> on the Bold Mark Properties Management System. You'll have access to invoicing, cashbook management, age analysis, and full financial reporting across your assigned estates. Use the button below to activate your account.",
    'portfolio-assistant' =>
      "You've been invited to join the team as a <strong style=\"color:#1E2740;\">Portfolio Assistant</strong> on the Bold Mark Properties Management System. You'll support daily operations, manage communications, and help keep estates running efficiently. Use the button below to activate your account.",
    'trustee' =>
      "You've been granted access to the Bold Mark Properties Management System as a <strong style=\"color:#1E2740;\">Trustee / Director</strong> for your community scheme. You can view financial reports, track levy collections, and stay fully informed on your scheme's performance. Use the button below to set up your account.",
    'owner' =>
      "You've been added to the Bold Mark Properties Management System as a <strong style=\"color:#1E2740;\">Property Owner</strong>. You can view your account balance, download levy statements, and stay up to date on everything related to your property. Use the button below to set up your account.",
    'tenant' =>
      "You've been added to the Bold Mark Properties Management System as a <strong style=\"color:#1E2740;\">Tenant</strong>. You can access your account, view your invoices, and stay connected with your property manager. Use the button below to set up your account.",
    'contractor' =>
      "You've been added to the Bold Mark Properties Management System as a <strong style=\"color:#1E2740;\">Contractor</strong>. You'll be able to view and manage job cards assigned to you. Use the button below to set up your account and get started.",
  ];

  $headline  = $headlines[$role]  ?? "Welcome to BoldMark PMS, {$name}.";
  $body      = $bodies[$role]     ?? "You've been invited to join the Bold Mark Properties Management System. Use the button below to set up your account.";
  $section   = $isInternal ? 'Welcome to the Team' : 'Welcome to BoldMark PMS';
@endphp

@extends('emails.layout')

@section('title', "You're invited — {$roleLabel}")
@section('section_label', $section)

@section('content')
  <h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1E2740;line-height:1.3;">{{ $headline }}</h1>
  <p style="margin:0 0 28px;font-size:15px;color:#4A5568;line-height:1.7;">{!! $body !!}</p>

  <!-- Role badge -->
  <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
    <tr>
      <td style="background-color:#F8FBFF;border:1px solid #DCDEE8;border-radius:6px;padding:10px 20px;">
        <span style="font-size:11px;font-weight:700;color:#717B99;letter-spacing:1.5px;text-transform:uppercase;">Your role</span>
        &nbsp;&nbsp;
        <span style="font-size:13px;font-weight:600;color:#1E2740;">{{ $roleLabel }}</span>
      </td>
    </tr>
  </table>

  <!-- CTA Button -->
  <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
    <tr>
      <td align="center" style="background-color:#D89B4B;border-radius:6px;">
        <a href="{{ $setupUrl }}" target="_blank"
           style="display:inline-block;padding:14px 36px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.5px;border-radius:6px;background-color:#D89B4B;">
          Set Up Your Account
        </a>
      </td>
    </tr>
  </table>

  <!-- Expiry note -->
  <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:28px;border:1px solid #DCDEE8;border-radius:6px;background-color:#F8FBFF;">
    <tr>
      <td style="padding:16px 20px;">
        <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#1E2740;letter-spacing:0.3px;text-transform:uppercase;">This link does not expire</p>
        <p style="margin:0;font-size:13px;color:#717B99;line-height:1.6;">
          Your invitation link has no expiry date — you can set up your account whenever you're ready. Keep this email safe for future reference.
        </p>
      </td>
    </tr>
  </table>

  <!-- Fallback URL -->
  <p style="margin:0;font-size:12px;color:#A0AEC0;line-height:1.6;">
    If the button above doesn't work, copy and paste this link into your browser:
  </p>
  <p style="margin:6px 0 0;font-size:12px;line-height:1.6;">
    <a href="{{ $setupUrl }}" style="color:#D89B4B;word-break:break-all;">{{ $setupUrl }}</a>
  </p>
@endsection
