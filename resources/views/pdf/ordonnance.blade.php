<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Ordonnance</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; color: #1F2937; font-size: 13px; }
    .entete { border-bottom: 3px solid #007BFF; padding-bottom: 14px; margin-bottom: 24px; }
    .entete .clinique { font-size: 20px; font-weight: bold; color: #007BFF; }
    .entete .coordonnees { font-size: 11px; color: #6B7280; margin-top: 4px; }
    .bloc { margin-bottom: 18px; }
    .bloc .label { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6B7280; }
    .bloc .valeur { font-size: 14px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { text-align: left; font-size: 10.5px; text-transform: uppercase; color: #6B7280; border-bottom: 1px solid #D1D5DB; padding: 6px 8px; }
    td { padding: 10px 8px; border-bottom: 1px solid #EAEEF4; font-size: 13px; vertical-align: top; }
    .pied { margin-top: 40px; font-size: 11px; color: #6B7280; border-top: 1px solid #EAEEF4; padding-top: 12px; }
</style>
</head>
<body>
    <div class="entete">
        <div class="clinique">✚ Clinique Croix Bleue</div>
        <div class="coordonnees">Route de l'Aéroport, Ouakam, Dakar, Sénégal — +221 33 800 00 00 — contact@croixbleue.sn</div>
    </div>

    <table style="margin-bottom: 10px;">
        <tr>
            <td style="border: none; width: 50%; padding: 0;">
                <div class="bloc">
                    <div class="label">Patient</div>
                    <div class="valeur">{{ $patient->prenom }} {{ $patient->nom }}</div>
                </div>
            </td>
            <td style="border: none; width: 50%; padding: 0;">
                <div class="bloc">
                    <div class="label">Prescripteur</div>
                    <div class="valeur">Dr {{ $medecin->user->prenom }} {{ $medecin->user->nom }}</div>
                    <div class="coordonnees">{{ $medecin->specialite->nom ?? '' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="bloc">
        <div class="label">Date</div>
        <div class="valeur">{{ $date->translatedFormat('d F Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Médicament</th>
                <th>Posologie</th>
                <th>Début</th>
                <th>Fin</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($traitements as $traitement)
                <tr>
                    <td><strong>{{ $traitement->medicament }}</strong></td>
                    <td>{{ $traitement->posologie }}</td>
                    <td>{{ $traitement->date_debut->translatedFormat('d/m/Y') }}</td>
                    <td>{{ $traitement->date_fin?->translatedFormat('d/m/Y') ?? 'En cours' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pied">
        Ordonnance générée électroniquement par la Clinique Croix Bleue — document confidentiel, à usage exclusif du patient nommé ci-dessus.
    </div>
</body>
</html>
