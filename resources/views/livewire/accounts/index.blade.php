<div>
    <x-ui.app-bar title="Contas" :back="route('more')">
        <x-slot:actions>
            <x-ui.privacy-toggle />
            <button type="button" wire:click="create" class="tap rounded-full p-2 text-brand hover:bg-card" aria-label="Nova conta">
                @svg('heroicon-o-plus-circle', 'size-7')
            </button>
        </x-slot:actions>
    </x-ui.app-bar>

    <div class="space-y-5 pt-2">
        <div class="px-4">
            <x-ui.card class="p-4">
                <p class="text-sm text-muted">Saldo total das contas</p>
                <x-ui.money :value="$this->total" @class(['mt-1 block text-2xl font-bold', 'text-expense' => $this->total < 0]) />
            </x-ui.card>
        </div>

        <x-ui.section>
            @if ($this->accounts->isEmpty())
                <x-ui.card>
                    <x-ui.empty-state icon="building-library" title="Nenhuma conta cadastrada" description="Cadastre suas contas bancárias, poupança e carteira.">
                        <x-ui.button type="button" wire:click="create">@svg('heroicon-o-plus', 'size-5') Nova conta</x-ui.button>
                    </x-ui.empty-state>
                </x-ui.card>
            @else
                <x-ui.card class="divide-y divide-line overflow-hidden">
                    @foreach ($this->accounts as $account)
                        <button type="button" wire:key="acc-{{ $account->id }}" @if (! $reordering) wire:click="edit({{ $account->id }})" @endif
                                @class(['tap flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-card-hover', 'opacity-60' => $account->archived_at])>
                            @if ($account->institution && $account->institution->type !== 'other')
                                <x-ui.badge-avatar :color="$account->color" :text="$account->institution->short" class="size-10" />
                            @else
                                <x-ui.badge-avatar :color="$account->color" :icon="$account->typeIcon()" class="size-10" />
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $account->name }}</span>
                                <span class="block truncate text-sm text-muted">
                                    {{ $account->typeLabel() }}
                                    @if ($account->category) · {{ $account->category->name }} @endif
                                    @if ($account->archived_at) · Arquivada @elseif (! $account->include_in_total) · Fora do total @endif
                                </span>
                            </span>
                            @if ($reordering && ! $account->archived_at)
                                <span class="flex flex-col" x-on:click.stop>
                                    <span role="button" wire:click="move({{ $account->id }}, -1)" class="tap rounded p-1 text-muted hover:bg-canvas" aria-label="Subir {{ $account->name }}">@svg('heroicon-m-chevron-up', 'size-5')</span>
                                    <span role="button" wire:click="move({{ $account->id }}, 1)" class="tap rounded p-1 text-muted hover:bg-canvas" aria-label="Descer {{ $account->name }}">@svg('heroicon-m-chevron-down', 'size-5')</span>
                                </span>
                            @else
                                <x-ui.money :value="$account->balance()" @class(['font-semibold', 'text-expense' => $account->balance() < 0]) />
                            @endif
                        </button>
                    @endforeach
                </x-ui.card>
            @endif

            <div class="mt-3 flex items-center justify-center gap-5 text-sm text-muted">
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="checkbox" wire:model.live="showArchived" class="size-4 rounded accent-emerald-600"> Mostrar arquivadas
                </label>
                @if ($this->accounts->count() > 1)
                    <button type="button" wire:click="$toggle('reordering')" class="font-medium text-brand">{{ $reordering ? 'Concluir' : 'Reordenar' }}</button>
                @endif
            </div>
        </x-ui.section>

        <x-ui.section>
            <x-ui.card class="overflow-hidden" x-data>
                <x-ui.list-item icon="tag" icon-class="bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-300"
                    label="Categorias de contas" description="Ex.: Reserva de emergência. Use para filtrar o saldo no Início."
                    :badge="$accountCategories->count() ?: null" @click="$dispatch('open-sheet', 'account-categories')" :chevron="true" />
            </x-ui.card>
        </x-ui.section>
    </div>

    <x-ui.bottom-sheet name="account-categories" title="Categorias de contas">
        <div class="space-y-4">
            @if ($accountCategories->isEmpty())
                <p class="text-sm text-muted">Crie categorias como “Reserva de emergência” ou “Dia a dia”, escolha a categoria ao editar cada conta e filtre o saldo da tela inicial por elas.</p>
            @else
                <div class="divide-y divide-line overflow-hidden rounded-2xl border border-line">
                    @foreach ($accountCategories as $accountCategory)
                        <div wire:key="acc-cat-{{ $accountCategory->id }}" @class(['flex items-center gap-1 py-1.5 pl-4 pr-1.5', 'bg-brand-soft' => $categoryEditingId === $accountCategory->id])>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $accountCategory->name }}</span>
                                <span class="block text-sm text-muted">{{ $accountCategory->accounts_count }} {{ $accountCategory->accounts_count === 1 ? 'conta' : 'contas' }}</span>
                            </span>
                            <button type="button" wire:click="editCategory({{ $accountCategory->id }})" class="tap rounded-full p-2 text-muted hover:bg-card-hover" aria-label="Renomear {{ $accountCategory->name }}">
                                @svg('heroicon-o-pencil-square', 'size-5')
                            </button>
                            <button type="button" wire:click="deleteCategory({{ $accountCategory->id }})" class="tap rounded-full p-2 text-rose-600 hover:bg-card-hover dark:text-rose-400" aria-label="Excluir {{ $accountCategory->name }}"
                                    wire:confirm="Excluir a categoria “{{ $accountCategory->name }}”? As contas continuam existindo, só ficam sem categoria.">
                                @svg('heroicon-o-trash', 'size-5')
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <form wire:submit="saveCategory" class="space-y-3">
                <x-ui.input :label="$categoryEditingId ? 'Renomear categoria' : 'Nova categoria'" name="categoryName" wire:model="categoryName" placeholder="Ex.: Reserva de emergência" maxlength="40" />
                <div class="flex gap-3">
                    @if ($categoryEditingId)
                        <x-ui.button type="button" variant="secondary" class="flex-1" wire:click="cancelCategoryEdit">Cancelar</x-ui.button>
                    @endif
                    <x-ui.button class="flex-1">{{ $categoryEditingId ? 'Salvar' : 'Adicionar' }}</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.bottom-sheet>

    <x-ui.bottom-sheet name="account-form" :title="$editingId ? 'Editar conta' : 'Nova conta'">
        <form wire:submit="save" class="space-y-4">
            <x-ui.select label="Instituição" name="institution_id" wire:model.live="institution_id" :options="$institutions" placeholder="Nenhuma / dinheiro" />
            <x-ui.input label="Nome" name="name" wire:model="name" placeholder="Ex.: Nubank, Carteira" maxlength="60" />
            <x-ui.select label="Tipo" name="type" wire:model="type" :options="collect(\App\Models\Account::TYPES)->map(fn ($t) => $t[0])->all()" />
            <x-ui.select label="Categoria" name="account_category_id" wire:model="account_category_id" :options="$accountCategories->pluck('name', 'id')->all()" placeholder="Sem categoria"
                :hint="$accountCategories->isEmpty() ? 'Cadastre em “Categorias de contas”, no fim desta tela.' : null" />
            <x-ui.money-input :label="$editingId ? 'Saldo inicial' : 'Saldo atual'" name="initial_balance" wire:model="initial_balance" allow-negative
                hint="Use − para conta no negativo (cheque especial)." />
            <x-ui.color-picker wire:model="color" />
            <x-ui.toggle label="Incluir no saldo geral" description="Desligue para contas que não quer somar no total." wire:model="include_in_total" />

            <x-ui.button class="w-full">{{ $editingId ? 'Salvar alterações' : 'Criar conta' }}</x-ui.button>

            @if ($editing)
                <div class="rounded-2xl border border-line p-4" x-data="{ open: false }">
                    <button type="button" class="flex w-full items-center justify-between text-left" x-on:click="open = ! open">
                        <span>
                            <span class="block text-sm font-medium">Ajustar saldo</span>
                            <span class="block text-sm text-muted">Saldo no app: {{ \App\Support\Money::format($editing->balance()) }}</span>
                        </span>
                        @svg('heroicon-o-chevron-down', 'size-5 text-muted')
                    </button>
                    <div x-show="open" x-collapse class="mt-3 space-y-3">
                        <x-ui.money-input label="Saldo real no banco" name="real_balance" wire:model="real_balance" allow-negative
                            hint="Criaremos um lançamento de ajuste com a diferença." />
                        <x-ui.button type="button" variant="secondary" class="w-full" wire:click="adjustBalance">Ajustar</x-ui.button>
                    </div>
                </div>

                <x-ui.button :href="route('transactions', ['conta' => $editing->id])" variant="ghost" class="w-full" wire:navigate>
                    @svg('heroicon-o-list-bullet', 'size-5') Ver lançamentos desta conta
                </x-ui.button>

                <div class="grid grid-cols-2 gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="toggleArchive">
                        {{ $editing->archived_at ? 'Reativar' : 'Arquivar' }}
                    </x-ui.button>
                    <x-ui.button type="button" variant="secondary" class="!text-rose-600 dark:!text-rose-400"
                        wire:click="delete" wire:confirm="Excluir a conta “{{ $editing->name }}”? Esta ação não pode ser desfeita.">
                        Excluir
                    </x-ui.button>
                </div>
            @endif
        </form>
    </x-ui.bottom-sheet>
</div>
