<div>
    <x-ui.app-bar title="Perfil" :back="route('more')" />

    <div class="space-y-6 pt-2">
        {{-- Foto --}}
        <div class="flex flex-col items-center gap-3 px-4">
            <div class="relative">
                <x-ui.avatar :user="$user" class="size-24 text-3xl" />
                <div wire:loading.flex wire:target="photo" class="absolute inset-0 items-center justify-center rounded-full bg-black/50">
                    <span class="size-7 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                </div>
                <label class="tap absolute -bottom-1 -right-1 grid size-9 cursor-pointer place-items-center rounded-full bg-brand text-white shadow ring-4 ring-canvas" aria-label="Alterar foto">
                    @svg('heroicon-o-camera', 'size-5')
                    <input type="file" wire:model="photo" accept="image/*" class="sr-only">
                </label>
            </div>
            @error('photo') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            @if ($user->avatar_path)
                <button type="button" wire:click="removePhoto" class="text-sm font-medium text-muted hover:text-ink">Remover foto</button>
            @endif
        </div>

        {{-- Dados --}}
        <x-ui.section title="Seus dados">
            <x-ui.card class="p-4">
                <form wire:submit="saveProfile" class="space-y-4">
                    <x-ui.input label="Nome" name="name" wire:model="name" autocomplete="name" />
                    <x-ui.input label="E-mail" name="email" type="email" wire:model="email" autocomplete="email" inputmode="email" />
                    <x-ui.button class="w-full">Salvar</x-ui.button>
                </form>
            </x-ui.card>
        </x-ui.section>

        {{-- Segurança --}}
        <x-ui.section title="Segurança">
            <x-ui.card class="divide-y divide-line overflow-hidden" x-data>
                <x-ui.list-item icon="lock-closed" label="Alterar senha" @click="$dispatch('open-sheet', 'password')" :chevron="true" />
                <x-ui.list-item icon="arrow-down-tray" label="Exportar todos os meus dados" description="Arquivo JSON com tudo (backup)" :href="route('export.backup')" :navigate="false" />
                <x-ui.list-item icon="arrow-path" icon-class="bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300" label="Recomeçar do zero"
                    description="Apaga lançamentos, contas, cartões e tudo mais; mantém seu login" @click="$dispatch('open-sheet', 'reset-data')" :chevron="true" />
                <x-ui.list-item icon="trash" icon-class="bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-300" label="Excluir conta"
                    description="Apaga todos os seus dados" @click="$dispatch('open-sheet', 'delete-account')" :chevron="true" />
            </x-ui.card>
        </x-ui.section>
    </div>

    <x-ui.bottom-sheet name="password" title="Alterar senha">
        <form wire:submit="updatePassword" class="space-y-4">
            <x-ui.input label="Senha atual" name="current_password" type="password" wire:model="current_password" autocomplete="current-password" />
            <x-ui.input label="Nova senha" name="password" type="password" wire:model="password" autocomplete="new-password" hint="Mínimo de 8 caracteres." />
            <x-ui.input label="Confirmar nova senha" name="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
            <x-ui.button class="w-full">Alterar senha</x-ui.button>
        </form>
    </x-ui.bottom-sheet>

    <x-ui.bottom-sheet name="reset-data" title="Recomeçar do zero">
        <form wire:submit="resetData" class="space-y-4">
            <div class="rounded-2xl bg-amber-500/10 p-4 text-sm">
                <p class="font-semibold text-amber-800 dark:text-amber-200">Serão apagados permanentemente:</p>
                <p class="mt-1 text-amber-800/90 dark:text-amber-200/90">lançamentos, transferências, contas, cartões e faturas, parcelamentos, lançamentos fixos, orçamentos, investimentos, categorias e o histórico de avisos.</p>
                <p class="mt-3 font-semibold text-amber-800 dark:text-amber-200">Continuam:</p>
                <p class="mt-1 text-amber-800/90 dark:text-amber-200/90">seu login, foto, tema e notificações ativadas. As categorias padrão e a conta "Carteira" são criadas de novo.</p>
            </div>
            <p class="text-sm text-muted">Esta ação <strong class="text-ink">não pode ser desfeita</strong>. Se quiser guardar uma cópia, use antes "Exportar todos os meus dados". Digite sua senha para confirmar.</p>
            <x-ui.input label="Senha" name="reset_password" type="password" wire:model="reset_password" autocomplete="current-password" />
            <x-ui.button class="w-full !bg-amber-600 hover:!bg-amber-700">Apagar tudo e recomeçar</x-ui.button>
        </form>
    </x-ui.bottom-sheet>

    <x-ui.bottom-sheet name="delete-account" title="Excluir conta">
        <form wire:submit="deleteAccount" class="space-y-4">
            <p class="text-sm text-muted">Esta ação é <strong class="text-ink">permanente</strong>: todas as suas contas, lançamentos e configurações serão apagados. Digite sua senha para confirmar.</p>
            <x-ui.input label="Senha" name="delete_password" type="password" wire:model="delete_password" autocomplete="current-password" />
            <x-ui.button variant="danger" class="w-full">Excluir minha conta</x-ui.button>
        </form>
    </x-ui.bottom-sheet>
</div>
