<x-mail::message>
# Demande de rendez-vous reçue

Bonjour {{ $rendezVous->patient->prenom }},

Nous avons bien reçu votre demande de rendez-vous à la Clinique Croix Bleue. Elle est en attente de validation par le médecin — vous recevrez un e-mail dès sa réponse.

<x-mail::table>
| | |
|:-------|-------:|
| Médecin | Dr {{ $rendezVous->medecin->user->prenom }} {{ $rendezVous->medecin->user->nom }} |
| Spécialité | {{ $rendezVous->medecin->specialite->nom }} |
| Date | {{ ucfirst($rendezVous->date_heure->locale('fr')->translatedFormat('l j F Y')) }} |
| Heure | {{ $rendezVous->date_heure->format('H \h i') }} |
| Statut | En attente de validation |
</x-mail::table>

Merci de votre confiance,<br>
Clinique Croix Bleue
</x-mail::message>
