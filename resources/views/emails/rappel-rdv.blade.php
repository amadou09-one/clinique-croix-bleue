@extends('emails.layout')

@section('title', 'Rappel de rendez-vous')

@section('content')
<h1 style="font-size:20px;font-weight:700;margin:0 0 16px;color:#1F2937;">Rappel : rendez-vous demain 🔔</h1>
<p style="margin:0 0 20px;">Bonjour {{ $rendezVous->patient->prenom }},</p>
<p style="margin:0 0 24px;">Nous vous rappelons votre rendez-vous à la Clinique Croix Bleue prévu dans moins de 24 heures :</p>

@include('emails.partials.recap-rdv')

<p style="margin:24px 0 0;color:#6B7280;font-size:13px;">
  En cas d'empêchement, vous pouvez annuler jusqu'à <b>6 heures avant</b> l'horaire prévu depuis votre espace patient.
</p>
@endsection
