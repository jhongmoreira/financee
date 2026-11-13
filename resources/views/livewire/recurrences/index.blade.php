<div>
    <x-ui.app-bar title="Lançamentos fixos" :back="route('more')">
        <x-slot:actions>
            <x-ui.privacy-toggle />
            <button type="button" x-on:click="$dispatch('open-sheet', 'entry-form'); $dispatch('open-entry', { kind: 'expense', repeat: 'fixed' })"
                    class="tap rounded-full p-2 text-brand hover:bg-card" aria-label="Novo lançamento fixo">
                @svg('heroicon-o-plus-circle', 'size-7')
            </button>
        </x-slot:actions>
    </x-ui.app-bar>

    <div class="space-y-4 px-4 pt-2">
        <x-ui.card class="grid grid-cols-2 gap-4 p-4">
            <div>
                <p class="text-xs text-muted">Despesas fixas / mês</p>
                <x-ui.money :value="$monthlyExpense" class="block text-lg font-semibold text-expense" />
            </div>
            <div>
                <p class="text-xs text-muted">Receitas fixas / mês</p>
                <x-ui.money :value="$monthlyIncome" class="block text-lg font-semibold text-income" />
            </div>
        </x-ui.card>

        @if ($recurrences->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="arrow-path" title="Nenhum lançamento fixo"
                    description="Cadastre salário, aluguel, assinaturas e contas que se repetem: o app lança os próximos meses como previstos.">
                    <x-ui.button type="button" x-on:click="$dispatch('open-sheet', 'entry-form'); $dispatch('open-entry', { kind: 'expense', repeat: 'fixed' })">
                        @svg('heroicon-o-plus', 'size-5') Novo lançamento fixo
                    </x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <x-ui.card class="divide-y divide-line overflow-hidden">
                @foreach ($recurrences as $r)
                    @php($ended = $r->end_date && $r->end_date->lessThan(today()))
                    <button type="button" wire:key="rec-{{ $r->id }}" wire:click="edit({{ $r->id }})"
                            @class(['tap flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-card-hover', 'opacity-60' => $ended])>
                        <x-ui.badge-avatar :color="$r->category->color ?? '#6b7280'" :icon="$r->category->icon ?? 'arrow-path'" class="size-10" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $r->description }}</span>
                            <span class="block truncate text-sm text-muted">
                                {{ $r->frequencyLabel() }}
                                · {{ $ended ? 'encerrado' : (isset($next[$r->id]) ? 'próximo '.\Carbon\CarbonImmutable::parse($next[$r->id])->format('d/m') : 'sem próximos') }}
                                · {{ $r->creditCard ? 'Cartão '.$r->creditCard->name : $r->account?->name }}
                                @if ($r->auto_mark_paid) · débito automático @endif
                            </span>
                        </span>
                        <x-ui.money :value="$r->type === 'income' ? $r->amount : -$r->amount" @class(['font-semibold', 'text-income' => $r->type === 'income', 'text-expense' => $r->type === 'expense']) />
                    </button>
                @endforeach
            </x-ui.card>
        @endif

        <label class="flex cursor-pointer items-center justify-center gap-2 text-sm text-muted">
            <input type="checkbox" wire:model.live="showEnded" class="size-4 rounded accent-emerald-600"> Mostrar encerrados
        </label>
    </div>

    <x-ui.bottom-sheet name="recurrence-form" title="Editar lançamento fixo">
        @if ($editing)
            <form wire:submit="save" class="space-y-4">
                <x-ui.money-input label="Valor" name="amount" wire:model="amount" />
                <x-ui.input label="Descrição" name="description" wire:model="description" maxlength="120" />
                <x-ui.select label="Categoria" name="category_id" wire:model="category_id" :options="$categories" placeholder="Sem categoria" />
                @if ($editing->credit_card_id)
                    <p class="text-sm text-muted">Lançado no cartão <strong class="text-ink">{{ $editing->creditCard?->name }}</strong>.</p>
                @else
                    <x-ui.select label="Conta" name="account_id" wire:model="account_id" :options="$accounts" placeholder="Selecione" />
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.select label="Frequência" name="frequency" wire:model="frequency" :options="collect(\App\Models\Recurrence::FREQUENCIES)->map(fn ($f) => $f[0])->all()" />
                    <x-ui.input label="Até (opcional)" name="end_date" type="date" wire:model="end_date" />
                </div>
                @unless ($editing->credit_card_id)
                    <x-ui.toggle wire:model="auto_mark_paid" label="Débito automático" description="Marca como pago sozinho no dia." />
                @endunless
                <p class="rounded-xl bg-canvas px-4 py-3 text-xs text-muted">As mudanças valem a partir de hoje: os lançamentos previstos são refeitos e os já pagos não mudam.</p>
                <x-ui.button class="w-full">Salvar</x-ui.button>
                <x-ui.button type="button" variant="secondary" class="w-full !text-rose-600 dark:!text-rose-400" wire:click="stop"
                    wire:confirm="Encerrar este lançamento fixo? Os previstos a partir de hoje serão removidos.">Encerrar</x-ui.button>
            </form>
        @endif
    </x-ui.bottom-sheet>
</div>
