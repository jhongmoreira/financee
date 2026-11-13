<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased">
    <main class="min-h-dvh flex flex-col items-center justify-center gap-3 px-4 text-center">
        <div class="size-16 rounded-2xl bg-emerald-600 text-white grid place-items-center text-3xl font-bold shadow-lg">F</div>
        <h1 class="text-2xl font-semibold">Hello, Financee</h1>
        <p class="text-sm text-slate-500">Release {{ trim(@file_get_contents(base_path('RELEASE')) ?: 'dev') }} · PHP {{ PHP_VERSION }}</p>
    </main>
</body>
</html>
