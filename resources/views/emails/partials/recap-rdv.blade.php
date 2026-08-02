<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;border-radius:14px;">
  <tr>
    <td style="padding:12px 16px;font-size:13px;color:#6B7280;">Médecin</td>
    <td style="padding:12px 16px;font-size:14px;font-weight:600;text-align:right;">Dr {{ $rendezVous->medecin->user->prenom }} {{ $rendezVous->medecin->user->nom }}</td>
  </tr>
  <tr>
    <td style="padding:12px 16px;font-size:13px;color:#6B7280;">Spécialité</td>
    <td style="padding:12px 16px;font-size:14px;font-weight:600;text-align:right;color:#007BFF;">{{ $rendezVous->medecin->specialite->nom }}</td>
  </tr>
  <tr>
    <td style="padding:12px 16px;font-size:13px;color:#6B7280;">Date</td>
    <td style="padding:12px 16px;font-size:14px;font-weight:600;text-align:right;">{{ ucfirst($rendezVous->date_heure->locale('fr')->translatedFormat('l j F Y')) }}</td>
  </tr>
  <tr>
    <td style="padding:12px 16px;font-size:13px;color:#6B7280;">Heure</td>
    <td style="padding:12px 16px;font-size:14px;font-weight:600;text-align:right;">{{ $rendezVous->date_heure->format('H \h i') }}</td>
  </tr>
  <tr>
    <td style="padding:12px 16px;font-size:13px;color:#6B7280;">Adresse</td>
    <td style="padding:12px 16px;font-size:14px;font-weight:600;text-align:right;">Ouakam, Dakar</td>
  </tr>
</table>
