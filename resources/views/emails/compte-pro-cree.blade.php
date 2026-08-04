@extends('emails.layout')

@section('title', 'Votre compte a été créé')

@php
$libelleRole = match($utilisateur->role) {
    'medecin' => 'médecin',
    'secretaire' => 'secrétaire',
    'admin' => 'administrateur',
    default => 'utilisateur',
};
@endphp

@section('content')
<h1 style="font-size:20px;font-weight:700;margin:0 0 16px;color:#1F2937;">Bienvenue à la Clinique Croix Bleue</h1>
<p style="margin:0 0 20px;">Bonjour {{ $utilisateur->prenom }},</p>
<p style="margin:0 0 24px;">
  Un compte {{ $libelleRole }} vient d'être créé pour vous par l'administration de la clinique.
  Pour y accéder, définissez votre mot de passe en cliquant sur le bouton ci-dessous :
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
  <tr>
    <td style="border-radius:13px;background-color:#007BFF;">
      <a href="{{ $urlDefinirMotDePasse }}" style="display:inline-block;padding:14px 26px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">
        Définir mon mot de passe
      </a>
    </td>
  </tr>
</table>

<p style="margin:0 0 6px;color:#6B7280;font-size:13px;">
  Ce lien est valable 7 jours. Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :
</p>
<p style="margin:0 0 24px;color:#007BFF;font-size:12.5px;word-break:break-all;">{{ $urlDefinirMotDePasse }}</p>

<p style="margin:0;color:#6B7280;font-size:13px;">
  Une fois votre mot de passe défini, vous pourrez accéder à votre espace {{ $libelleRole }}.
</p>
@endsection
