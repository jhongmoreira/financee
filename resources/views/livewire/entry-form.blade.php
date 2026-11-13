<div>
    <x-ui.bottom-sheet name="entry-form" :title="$editingId ? 'Editar lançamento' : 'Novo lançamento'">
        <form wire:submit="save" class="space-y-4">
            {{-- Tipo --}}
            @unless ($editingId)
                <div class="grid grid-cols-4 gap-1 rounded-xl bg-canvas p-1">
                    @foreach ([
                        'expense' => ['Despesa', 'text-expense bg-expense/10'],
                        'income' => ['Receita', 'text-income bg-income/10'],
                        'card' => ['Cartão', 'text-violet-600 bg-violet-500/10 dark:text-violet-400'],
                        'transfer' => ['Transf.', 'text-sky-600 bg-sky-500/10 dark:text-sky-400'],
                    ] as $value => [$label, $active])
                        <label wire:key="kind-{{ $value }}" @class(['tap cursor-pointer rounded-lg py-2 text-center text-sm font-semibold', $active => $kind === $value, 'text-muted' => $kind !== $value])>
                            <input type="radio" wire:model.live="kind" value="{{ $value }}" class="sr-only"> {{ $label }}
                        </label>
                    @endforeach
                </div>
            @endunless

            @if ($kind === 'card' && empty($cards))
                <x-ui.empty-state icon="credit-card" title="Nenhum cartão cadastrado" description="Cadastre um cartão para lançar compras nele.">
                    <x-ui.button :href="route('cards')" wire:navigate x-on:click="close()">Cadastrar cartão</x-ui.button>
                </x-ui.empty-state>
            @else
                @if ($kind === 'card' && ! $planId)
                    <label class="flex w-fit cursor-pointer items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="is_refund" class="size-4 rounded accent-emerald-600" @disabled($editingId)>
                        Estorno / crédito no cartão
                    </label>
                @endif

                {{-- Valor --}}
                <div @class(['[&_input]:text-3xl [&_input]:font-bold',
                    '[&_input]:text-expense' => $kind === 'expense' || ($kind === 'card' && ! $is_refund),
                    '[&_input]:text-income' => $kind === 'income' || ($kind === 'card' && $is_refund)])>
                    <x-ui.money-input name="amount" wire:model="amount" />
                </div>

                {{-- Parcelamento (cartão, novo lançamento) --}}
                @if ($kind === 'card' && ! $editingId && ! $is_refund)
                    <div class="flex items-center gap-3">
                        <div class="w-28 shrink-0">
                            <x-ui.select name="installments" wire:model.live="installments" aria-label="Parcelas"
                                :options="collect(range(1, 48))->mapWithKeys(fn ($n) => [$n => $n === 1 ? 'À vista' : $n.'x'])->all()" />
                        </div>
                        @if ($installments > 1)
                            <div class="grid flex-1 grid-cols-2 gap-1 rounded-xl bg-canvas p-1 text-xs font-semibold">
                                @foreach (['total' => 'Valor total', 'parcel' => 'Valor da parcela'] as $mode => $label)
                                    <label wire:key="mode-{{ $mode }}" @class(['tap cursor-pointer rounded-lg py-2 text-center', 'bg-card text-ink shadow-sm' => $installment_mode === $mode, 'text-muted' => $installment_mode !== $mode])>
                                        <input type="radio" wire:model.live="installment_mode" value="{{ $mode }}" class="sr-only"> {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if ($installments > 1 && $amount > 0)
                        @php
                            $total = $installment_mode === 'parcel' ? $amount * $installments : $amount;
                            $parts = \App\Models\InstallmentPlan::split($total, $installments);
                        @endphp
                        <p class="-mt-2 text-sm text-muted">
                            {{ $installments }}x de <strong class="text-ink">{{ \App\Support\Money::format($parts[1]) }}</strong>
                            @if ($parts[0] !== $parts[1]) (1ª de {{ \App\Support\Money::format($parts[0]) }}) @endif
                            · total {{ \App\Support\Money::format($total) }}
                        </p>
                    @endif
                @endif

                {{-- Parcela em edição --}}
                @if ($plan)
                    <div class="rounded-2xl bg-violet-500/10 p-3 text-sm">
                        <p class="font-medium text-violet-700 dark:text-violet-300">Parcela de "{{ $plan->description }}" · {{ $plan->installments }}x · total {{ \App\Support\Money::format($plan->total_amount) }}</p>
                        <p class="mb-2 mt-1 text-muted">Aplicar alterações e exclusão em:</p>
                        <div class="grid grid-cols-3 gap-1 rounded-xl bg-card p-1 text-xs font-semibold">
                            @foreach (['this' => 'Só esta', 'future' => 'Esta e próximas', 'all' => 'Todas'] as $value => $label)
                                <label wire:key="scope-{{ $value }}" @class(['tap cursor-pointer rounded-lg py-2 text-center', 'bg-violet-600 text-white' => $scope === $value, 'text-muted' => $scope !== $value])>
                                    <input type="radio" wire:model.live="scope" value="{{ $value }}" class="sr-only"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Descrição --}}
                <div>
                    <x-ui.input name="description" wire:model.blur="description" list="entry-suggestions" autocomplete="off" maxlength="120"
                        :placeholder="$kind === 'transfer' ? 'Descrição (opcional)' : 'Descrição'" aria-label="Descrição" />
                    <datalist id="entry-suggestions">
                        @foreach ($suggestions as $suggestion)
                            <option value="{{ $suggestion }}"></option>
                        @endforeach
                    </datalist>
                </div>

                @if ($kind === 'transfer')
                    <div class="grid grid-cols-[1fr_auto_1fr] items-end gap-2">
                        <x-ui.select label="De" name="account_id" wire:model="account_id" :options="$accounts" placeholder="Conta" />
                        <span class="pb-3 text-muted">@svg('heroicon-o-arrow-right', 'size-5')</span>
                        <x-ui.select label="Para" name="to_account_id" wire:model="to_account_id" :options="$accounts" placeholder="Conta" />
                    </div>
                @else
                    {{-- Categoria: recentes + lista completa --}}
                    <div>
                        <p class="mb-1.5 text-sm font-medium">Categoria</p>
                        @if ($recentCategories->isNotEmpty())
                            <div class="-mx-5 mb-2 flex gap-2 overflow-x-auto px-5 pb-1 [scrollbar-width:none]">
                                @foreach ($recentCategories as $cat)
                                    <button type="button" wire:key="rc-{{ $cat->id }}" wire:click="$set('category_id', '{{ $cat->id }}')"
                                            @class(['tap flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm',
                                                'border-transparent text-white' => (string) $category_id === (string) $cat->id,
                                                'border-line bg-canvas' => (string) $category_id !== (string) $cat->id])
                                            @if ((string) $category_id === (string) $cat->id) style="background-color: {{ $cat->color }}" @endif>
                                        @svg('heroicon-o-'.\App\Support\Palette::safeIcon($cat->icon), 'size-4')
                                        {{ $cat->name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        <div class="relative">
                            <select wire:model.live="category_id" aria-label="Categoria"
                                    @class(['block w-full appearance-none rounded-xl border bg-card py-3 pl-4 pr-10 outline-none focus:border-brand focus:ring-4 focus:ring-brand/15',
                                        'border-rose-500' => $errors->has('category_id'), 'border-line' => ! $errors->has('category_id')])>
                                <option value="">Sem categoria</option>
                                @foreach ($categoryOptions as $group => $items)
                                    <optgroup label="{{ $group }}">
                                        @foreach ($items as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @svg('heroicon-m-chevron-up-down', 'pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-muted')
                        </div>
                        @error('category_id') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    @if ($kind === 'card')
                        @unless ($planId)
                            <div class="grid grid-cols-2 gap-3">
                                <x-ui.select label="Cartão" name="credit_card_id" wire:model.live="credit_card_id" :options="$cards" placeholder="Selecione" />
                                <x-ui.select label="Fatura" name="invoice_ref" wire:model="invoice_ref" :options="$invoiceOptions" />
                            </div>
                        @endunless
                    @else
                        <x-ui.select label="Conta" name="account_id" wire:model="account_id" :options="$accounts" placeholder="Selecione a conta" />
                    @endif
                @endif

                {{-- Data --}}
                @unless ($planId)
                    <div>
                        <p class="mb-1.5 text-sm font-medium">{{ $kind === 'card' ? 'Data da compra' : 'Data' }}</p>
                        <div class="flex gap-2">
                            <button type="button" wire:click="setDate('today')" @class(['tap rounded-full px-4 py-2 text-sm font-medium', 'bg-brand text-white' => $isToday, 'bg-canvas text-ink' => ! $isToday])>Hoje</button>
                            <button type="button" wire:click="setDate('yesterday')" @class(['tap rounded-full px-4 py-2 text-sm font-medium', 'bg-brand text-white' => $isYesterday, 'bg-canvas text-ink' => ! $isYesterday])>Ontem</button>
                            <input type="date" wire:model.live="date" aria-label="Outra data"
                                   @class(['min-w-0 flex-1 rounded-full border px-4 py-2 text-sm outline-none focus:border-brand',
                                       'border-brand bg-brand-soft font-medium text-brand-strong' => ! $isToday && ! $isYesterday, 'border-line bg-canvas' => $isToday || $isYesterday])>
                        </div>
                        @error('date') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                @endunless

                @if ($kind !== 'card')
                    <x-ui.toggle wire:model="paid"
                        :label="match ($kind) { 'income' => 'Recebido', 'transfer' => 'Transferência efetivada', default => 'Pago' }"
                        :description="$kind === 'transfer' ? 'Desligue para agendar.' : 'Desligue para lançar como previsto (pendente).'" />
                @endif

                @if ($kind !== 'transfer')
                    @if ($showMore)
                        <div>
                            <label for="f-notes" class="mb-1.5 block text-sm font-medium">Observação</label>
                            <textarea id="f-notes" wire:model="notes" rows="2" maxlength="1000"
                                      class="block w-full rounded-xl border border-line bg-card px-4 py-3 outline-none focus:border-brand focus:ring-4 focus:ring-brand/15"></textarea>
                        </div>
                    @else
                        <button type="button" wire:click="$set('showMore', true)" class="text-sm font-medium text-brand">+ Observação</button>
                    @endif
                @endif

                <div class="space-y-3 pt-1">
                    <x-ui.button class="w-full">{{ $editingId ? 'Salvar alterações' : 'Salvar' }}</x-ui.button>
                    @if ($editingId)
                        @if ($planId)
                            <x-ui.button type="button" variant="ghost" class="w-full" wire:click="anticipate"
                                wire:confirm="Antecipar as parcelas futuras deste parcelamento para a fatura atual?">
                                @svg('heroicon-o-forward', 'size-5') Antecipar parcelas futuras
                            </x-ui.button>
                        @endif
                        <x-ui.button type="button" variant="secondary" class="w-full !text-rose-600 dark:!text-rose-400"
                            wire:click="delete" wire:confirm="{{ $planId ? 'Excluir as parcelas no escopo escolhido?' : 'Excluir este lançamento?' }}">Excluir</x-ui.button>
                    @else
                        <x-ui.button type="button" variant="ghost" class="w-full" wire:click="save(true)">Salvar e criar outro</x-ui.button>
                    @endif
                </div>
            @endif
        </form>
    </x-ui.bottom-sheet>
</div>
