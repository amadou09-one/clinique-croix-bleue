<x-mail::message>
# Nouvelle demande de rendez-vous

Bonjour Dr {{ $rendezVous->medecin->user->nom }},

Un patient souhaite un rendez-vous avec vous. Merci de valider ou refuser cette demande :

<x-mail::table>
| | |
|:-------|-------:|
| Patient | {{ $rendezVous->patient->prenom }} {{ $rendezVous->patient->nom }} |
| Date | {{ ucfirst($rendezVous->date_heure->locale('fr')->translatedFormat('l j F Y')) }} |
| Heure | {{ $rendezVous->date_heure->format('H \h i') }} |
</x-mail::table>

<x-mail::button :url="$urlAccepter" color="success">
Accepter
</x-mail::button>
<x-mail::button :url="$urlRefuser" color="error">
Refuser
</x-mail::button>

Ce lien est valable 48 heures.

Clinique Croix Bleue
</x-mail::message>
