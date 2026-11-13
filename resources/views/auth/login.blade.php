<x-layouts.guest title="Entrar">
    <x-slot:subtitle>Suas finanças, simples e no bolso.</x-slot:subtitle>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-ui.input label="E-mail" name="email" type="email" :value="old('email')" autocomplete="email" inputmode="email" required autofocus />
        <x-ui.input label="Senha" name="password" type="password" autocomplete="current-password" required />

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" value="1" checked class="size-4 rounded border-line accent-emerald-600">
                Manter conectado
            </label>
            <a href="{{ route('password.request') }}" class="font-medium text-brand">Esqueci a senha</a>
        </div>

        <x-ui.button class="w-full">Entrar</x-ui.button>
    </form>

    @if (Route::has('register'))
        <p class="mt-8 text-center text-sm text-muted">
            Ainda não tem conta? <a href="{{ route('register') }}" class="font-semibold text-brand">Criar conta</a>
        </p>
    @endif
</x-layouts.guest>
