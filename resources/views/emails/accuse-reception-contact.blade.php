@extends('emails.layout')

@section('title', 'Nous avons bien reçu votre message')

@section('content')
<h1 style="font-size:20px;font-weight:700;margin:0 0 16px;color:#1F2937;">Merci de nous avoir contactés</h1>
<p style="margin:0 0 20px;">Bonjour {{ $messageContact->nom }},</p>
<p style="margin:0 0 24px;">
  Nous avons bien reçu votre message{{ $messageContact->sujet ? ' concernant « '.$messageContact->sujet.' »' : '' }}
  et nous vous répondrons dans les meilleurs délais, généralement sous 24 à 48 heures ouvrées.
</p>
<p style="margin:0;color:#6B7280;font-size:13px;">
  Pour toute urgence médicale, contactez-nous directement au +221 33 800 00 00 (service disponible 24 h/24, 7 j/7).
</p>
@endsection
