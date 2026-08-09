@extends('emails.layout')

@section('title', 'Nouveau message de contact')

@section('content')
<h1 style="font-size:20px;font-weight:700;margin:0 0 16px;color:#1F2937;">Nouveau message reçu depuis le site vitrine</h1>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 24px;border-collapse:collapse;">
  <tr>
    <td style="padding:8px 0;color:#6B7280;font-size:13px;width:120px;">Nom</td>
    <td style="padding:8px 0;font-size:14px;font-weight:600;">{{ $messageContact->nom }}</td>
  </tr>
  <tr>
    <td style="padding:8px 0;color:#6B7280;font-size:13px;">E-mail</td>
    <td style="padding:8px 0;font-size:14px;font-weight:600;">{{ $messageContact->email }}</td>
  </tr>
  @if ($messageContact->telephone)
  <tr>
    <td style="padding:8px 0;color:#6B7280;font-size:13px;">Téléphone</td>
    <td style="padding:8px 0;font-size:14px;font-weight:600;">{{ $messageContact->telephone }}</td>
  </tr>
  @endif
  @if ($messageContact->sujet)
  <tr>
    <td style="padding:8px 0;color:#6B7280;font-size:13px;">Sujet</td>
    <td style="padding:8px 0;font-size:14px;font-weight:600;">{{ $messageContact->sujet }}</td>
  </tr>
  @endif
</table>

<p style="margin:0 0 8px;color:#6B7280;font-size:13px;">Message :</p>
<p style="margin:0 0 24px;padding:16px;background-color:#F5F7FA;border-radius:12px;white-space:pre-line;">{{ $messageContact->message }}</p>

<p style="margin:0;color:#6B7280;font-size:13px;">
  Répondez directement à {{ $messageContact->email }}, ou traitez ce message depuis l'espace Administration.
</p>
@endsection
