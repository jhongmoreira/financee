@props(['title' => null])
@php
    $themePref = auth()->user()?->theme();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icons/logo.svg" type="image/svg+xml">
<link rel="icon" href="/icons/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="theme-color" content="#f3f4f6">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="format-detection" content="telephone=no">

{{-- Aplica o tema antes da primeira pintura (evita "flash" branco no tema escuro) --}}
<script>
    window.applyTheme = function (pref) {
        try { if (pref) localStorage.setItem('theme', pref); } catch (e) {}
        pref = pref || (function () { try { return localStorage.getItem('theme'); } catch (e) { return null; } })() || 'system';
        var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.dataset.themePref = pref;
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', dark ? '#0b0f14' : '#f3f4f6');
    };
    window.applyTheme(@json($themePref));
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (document.documentElement.dataset.themePref === 'system') window.applyTheme('system');
    });
    // wire:navigate copia os atributos do <html> da página nova: reaplica o tema na troca (sem "piscar")
    document.addEventListener('livewire:navigating', function (e) {
        var pref = document.documentElement.dataset.themePref;
        if (e.detail && e.detail.onSwap) e.detail.onSwap(function () { window.applyTheme(pref); });
    });
    document.addEventListener('livewire:navigated', function () {
        window.applyTheme(document.documentElement.dataset.themePref || null);
    });
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])

@production
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js', { scope: '/' }); });
        }
    </script>
@endproduction
