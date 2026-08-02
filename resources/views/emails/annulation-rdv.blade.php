@extends('emails.layout')

@section('title', 'Rendez-vous annulé')

@section('content')
<h1 style="font-size:20px;font-weight:700;margin:0 0 16px;color:#1F2937;">Rendez-vous annulé</h1>
<p style="margin:0 0 20px;">Bonjour {{ $rendezVous->patient->prenom }},</p>
<p style="margin:0 0 24px;">Le rendez-vous suivant a bien été annulé :</p>

@include('emails.partials.recap-rdv')

<p style="margin:24px 0 0;color:#6B7280;font-size:13px;">
  Vous pouvez prendre un nouveau rendez-vous à tout moment depuis votre espace patient.
</p>
@endsection
