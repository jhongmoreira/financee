<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Sem conexão · {{ config('app.name') }}</title>
    <script>try{var t=localStorage.getItem('theme');if(t==='dark'||((!t||t==='system')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-dvh place-items-center bg-canvas px-6 text-center text-ink antialiased">
    <div>
        <span class="mx-auto mb-4 grid size-16 place-items-center rounded-2xl bg-brand-soft text-brand-strong">@svg('heroicon-o-signal-slash', 'size-8')</span>
        <h1 class="text-xl font-semibold">Você está sem internet</h1>
        <p class="mt-2 text-sm text-muted">O Financee precisa de conexão para mostrar seus dados atualizados.</p>
        <button onclick="location.reload()" class="tap mt-6 rounded-xl bg-brand px-5 py-3 font-semibold text-white">Tentar novamente</button>
    </div>
</body>
</html>
