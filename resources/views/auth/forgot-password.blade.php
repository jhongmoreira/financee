<x-layouts.guest title="Recuperar senha">
    <x-slot:subtitle>Informe seu e-mail e enviaremos um link para criar uma nova senha.</x-slot:subtitle>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-ui.input label="E-mail" name="email" type="email" :value="old('email')" autocomplete="email" inputmode="email" required autofocus />
        <x-ui.button class="w-full">Enviar link</x-ui.button>
    </form>

    <p class="mt-8 text-center text-sm text-muted">
        <a href="{{ route('login') }}" class="font-semibold text-brand">Voltar para o login</a>
    </p>
</x-layouts.guest>
