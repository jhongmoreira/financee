<div>
    <x-ui.app-bar title="Usuários" :back="route('more')">
        <x-slot:actions>
            <a href="{{ route('admin.activity') }}" wire:navigate class="tap rounded-full px-3 py-1.5 text-sm font-semibold text-brand hover:bg-card">Histórico</a>
        </x-slot:actions>
    </x-ui.app-bar>

    <div class="mx-auto max-w-3xl space-y-4 px-4 pt-2">
        <p class="text-sm text-muted">{{ $rows->count() }} conta(s). Aqui você gerencia o acesso; os dados financeiros de cada pessoa continuam visíveis só para ela.</p>

        <div class="grid grid-cols-1 gap-3">
            @foreach ($rows as $row)
                @php $u = $row['user']; @endphp
                <x-ui.card class="p-4" wire:key="u-{{ $u->id }}">
                    <div class="flex items-start gap-3">
                        <x-ui.avatar :user="$u" class="size-11 text-sm" />
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="truncate font-semibold">{{ $u->name }}</span>
                                @if ($u->is_admin) <span class="rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-semibold text-violet-700 dark:bg-violet-950 dark:text-violet-300">Admin</span> @endif
                                @if (! $u->is_active) <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-300">Desativada</span> @endif
                            </p>
                            <p class="truncate text-sm text-muted">{{ $u->email }}</p>
                            <p class="mt-1 text-xs text-muted">
                                Desde {{ $u->created_at?->format('d/m/Y') }} ·
                                {{ $row['last_seen'] ? 'último acesso '.$row['last_seen']->locale('pt_BR')->diffForHumans() : 'sem acesso registrado' }} ·
                                {{ $row['transactions'] }} lançamento(s), {{ $row['accounts'] }} conta(s)
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-line pt-3 text-sm">
                        <a href="{{ route('admin.activity', ['usuario' => $u->id]) }}" wire:navigate class="tap rounded-full border border-line px-3 py-1.5 font-medium hover:bg-card-hover">Ver histórico</a>
                        <button type="button" wire:click="sendReset({{ $u->id }})" wire:confirm="Enviar um link de redefinição de senha para {{ $u->email }}?" class="tap rounded-full border border-line px-3 py-1.5 font-medium hover:bg-card-hover">Enviar link de senha</button>
                        @if ($u->id !== $me)
                            <button type="button" wire:click="toggleActive({{ $u->id }})" @if ($u->is_active) wire:confirm="Desativar a conta de {{ $u->email }}? A pessoa sai de todos os aparelhos e não consegue mais entrar." @endif class="tap rounded-full border border-line px-3 py-1.5 font-medium hover:bg-card-hover">{{ $u->is_active ? 'Desativar' : 'Reativar' }}</button>
                            <button type="button" wire:click="askDelete({{ $u->id }})" class="tap rounded-full border border-rose-200 px-3 py-1.5 font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-400 dark:hover:bg-rose-950">Excluir</button>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    </div>

    <x-ui.bottom-sheet name="delete-user" title="Excluir conta">
        @if ($deletingUser)
            <form wire:submit="deleteUser" class="space-y-4">
                <p class="text-sm">Isso apaga <strong>a conta e todos os dados</strong> de {{ $deletingUser->name }} (lançamentos, contas, cartões, investimentos). Não dá para desfazer.</p>
                <p class="text-sm text-muted">Se a ideia é só impedir o acesso, use <strong>Desativar</strong>.</p>
                <x-ui.input label="Digite o e-mail {{ $deletingUser->email }} para confirmar" name="confirmEmail" wire:model="confirmEmail" autocomplete="off" />
                <x-ui.button variant="danger" class="w-full">Excluir definitivamente</x-ui.button>
            </form>
        @endif
    </x-ui.bottom-sheet>
</div>
