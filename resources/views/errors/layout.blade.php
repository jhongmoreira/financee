<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title') · Financee</title>
    <link rel="icon" href="/icons/logo.svg" type="image/svg+xml">
    <style>
        :root { --bg: #f3f4f6; --card: #fff; --ink: #111827; --muted: #6b7280; --brand: #059669; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b0f14; --card: #151a21; --ink: #f3f4f6; --muted: #9ca3af; --brand: #10b981; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 24px; background: var(--bg); color: var(--ink);
               font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; text-align: center; }
        .box { max-width: 360px; }
        img { width: 64px; height: 64px; border-radius: 16px; }
        .code { margin: 20px 0 4px; font-size: 13px; font-weight: 700; color: var(--muted); letter-spacing: .08em; }
        h1 { margin: 0; font-size: 22px; }
        p { color: var(--muted); line-height: 1.5; }
        a { display: inline-block; margin-top: 12px; padding: 12px 22px; border-radius: 12px; background: var(--brand); color: #fff; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <img src="/icons/logo.svg" alt="">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a href="@yield('link', '/')">@yield('button', 'Voltar para o início')</a>
    </div>
</body>
</html>
