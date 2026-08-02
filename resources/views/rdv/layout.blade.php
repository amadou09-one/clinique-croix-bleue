<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>@yield('title', 'Clinique Croix Bleue')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Poppins',sans-serif;background:#F5F7FA;color:#1F2937;min-height:100vh;display:grid;place-items:center;padding:24px}
  .card{background:#fff;border-radius:18px;box-shadow:0 12px 32px rgba(15,35,70,.08);padding:44px;max-width:440px;width:100%;text-align:center}
  .logo{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:28px}
  .logo-mark{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#007BFF,#3D9BFF);display:grid;place-items:center;color:#fff;font-weight:700;font-size:17px}
  .logo b{font-size:15px;font-weight:700}
  .icon{font-size:44px;margin-bottom:14px;line-height:1}
  h1{font-size:20px;font-weight:700;margin-bottom:10px}
  p{font-size:14px;color:#6B7280;line-height:1.6}
  table.details{width:100%;margin-top:22px;text-align:left;font-size:13px;border-collapse:collapse}
  table.details td{padding:8px 0;border-bottom:1px solid #F0F3F8}
  table.details td:first-child{color:#6B7280}
  table.details td:last-child{text-align:right;font-weight:600}
  .footer{margin-top:26px;font-size:12px;color:#9CA3AF}
</style>
</head>
<body>
<div class="card">
  <div class="logo"><div class="logo-mark">+</div><b>Clinique Croix Bleue</b></div>
  @yield('content')
  <div class="footer">Ouakam, Dakar, Sénégal · +221 33 800 00 00</div>
</div>
</body>
</html>
