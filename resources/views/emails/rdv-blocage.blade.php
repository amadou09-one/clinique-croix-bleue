@extends('emails.layout')

@section('title', 'Rendez-vous à reprogrammer')

@section('content')
<h1 style="font-size:20px;font-weight:700;margin:0 0 16px;color:#1F2937;">Votre rendez-vous doit être reprogrammé</h1>
<p style="margin:0 0 20px;">Bonjour {{ $rendezVous->patient->prenom }},</p>
<p style="margin:0 0 24px;">Votre médecin n'est exceptionnellement pas disponible à la date prévue. Le rendez-vous suivant a été annulé :</p>

@include('emails.partials.recap-rdv')

<p style="margin:24px 0 0;color:#6B7280;font-size:13px;">
  Nous vous invitons à reprendre un nouveau rendez-vous depuis votre espace patient. Toutes nos excuses pour la gêne occasionnée.
</p>
@endsection
