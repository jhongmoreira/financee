<x-layouts.guest title="Criar conta">
    <x-slot:subtitle>Crie sua conta gratuita.</x-slot:subtitle>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-ui.input label="Nome" name="name" :value="old('name')" autocomplete="name" required autofocus />
        <x-ui.input label="E-mail" name="email" type="email" :value="old('email')" autocomplete="email" inputmode="email" required />
        <x-ui.input label="Senha" name="password" type="password" autocomplete="new-password" hint="Mínimo de 8 caracteres." required />
        <x-ui.input label="Confirmar senha" name="password_confirmation" type="password" autocomplete="new-password" required />

        <x-ui.button class="w-full">Criar conta</x-ui.button>
    </form>

    <p class="mt-8 text-center text-sm text-muted">
        Já tem conta? <a href="{{ route('login') }}" class="font-semibold text-brand">Entrar</a>
    </p>
</x-layouts.guest>
