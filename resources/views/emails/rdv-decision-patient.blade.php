<x-mail::message>
@if($accepte)
# Rendez-vous confirmé ✅

Bonjour {{ $rendezVous->patient->prenom }},

Bonne nouvelle : votre rendez-vous a été confirmé par le médecin.
@else
# Votre demande n'a pas été retenue

Bonjour {{ $rendezVous->patient->prenom }},

Nous sommes désolés, le médecin n'a pas pu retenir votre demande de rendez-vous.
@endif

<x-mail::table>
| | |
|:-------|-------:|
| Médecin | Dr {{ $rendezVous->medecin->user->prenom }} {{ $rendezVous->medecin->user->nom }} |
| Spécialité | {{ $rendezVous->medecin->specialite->nom }} |
| Date | {{ ucfirst($rendezVous->date_heure->locale('fr')->translatedFormat('l j F Y')) }} |
| Heure | {{ $rendezVous->date_heure->format('H \h i') }} |
| Statut | {{ $accepte ? 'Confirmé' : 'Non retenu' }} |
</x-mail::table>

@unless($accepte)
Vous pouvez prendre un nouveau rendez-vous à tout moment depuis votre espace patient.
@endunless

Clinique Croix Bleue
</x-mail::message>
