@props(['title' => null])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    <main class="mx-auto flex min-h-dvh w-full max-w-sm flex-col justify-center px-5 pt-safe pb-safe">
        <div class="mb-8 flex flex-col items-center gap-3 text-center">
            <img src="/icons/logo.svg" alt="" class="size-16 rounded-2xl shadow-lg shadow-emerald-900/20">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ config('app.name') }}</h1>
                @isset($subtitle)
                    <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
                @endisset
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-brand-soft px-4 py-3 text-sm text-brand-strong">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
