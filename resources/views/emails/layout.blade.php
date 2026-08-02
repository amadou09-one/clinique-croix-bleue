<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>@yield('title', 'Clinique Croix Bleue')</title>
</head>
<body style="margin:0;padding:0;background-color:#F5F7FA;font-family:'Poppins','Segoe UI',Arial,sans-serif;color:#1F2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:520px;background:#FFFFFF;border-radius:18px;overflow:hidden;box-shadow:0 12px 32px rgba(15,35,70,.08);" cellpadding="0" cellspacing="0">
  <tr>
    <td style="background-color:#007BFF;padding:26px 32px;">
      <table role="presentation" cellpadding="0" cellspacing="0"><tr>
        <td style="width:36px;height:36px;background-color:rgba(255,255,255,.18);border-radius:10px;text-align:center;vertical-align:middle;color:#ffffff;font-size:17px;font-weight:700;font-family:Arial,sans-serif;">+</td>
        <td style="padding-left:12px;color:#ffffff;font-size:17px;font-weight:700;">Clinique Croix Bleue</td>
      </tr></table>
    </td>
  </tr>
  <tr>
    <td style="padding:32px;font-size:15px;line-height:1.6;">
      @yield('content')
    </td>
  </tr>
  <tr>
    <td style="padding:20px 32px;background-color:#F5F7FA;border-top:1px solid #EAEEF4;color:#6B7280;font-size:12px;line-height:1.7;">
      Clinique Croix Bleue — Route de l'Aéroport, Ouakam, Dakar, Sénégal<br>
      +221 33 800 00 00 · contact@croixbleue.sn<br>
      Cet e-mail est envoyé automatiquement, merci de ne pas y répondre directement.
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>
