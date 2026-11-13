@props(['label' => null, 'name', 'allowNegative' => false, 'hint' => null])
{{--
    Campo de valor em reais. O Livewire recebe CENTAVOS (int) via wire:model.
    Digitação estilo app de banco: os dígitos entram pela direita (1 → 0,01 → 0,12 → 1,23).
--}}
@php $model = $attributes->wire('model')->value(); @endphp
<div x-data="{
        cents: @entangle($attributes->wire('model')),
        get negative() { return this.cents < 0 },
        get display() {
            const v = Math.abs(parseInt(this.cents || 0));
            return (v / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        set(abs, el) {
            abs = Math.min(Math.max(0, abs), 9999999999999);
            this.cents = this.negative ? -abs : abs;
            el.value = this.display;
            el.setSelectionRange(el.value.length, el.value.length);
        },
        // Trata cada tecla sem depender da posição do cursor (no iOS o select() ao focar é ignorado)
        beforeinput(e) {
            const abs = Math.abs(parseInt(this.cents || 0));
            const type = e.inputType;
            if (type === 'insertText' || type === 'insertReplacementText') {
                e.preventDefault();
                if (e.data === '-' && {{ $allowNegative ? 'true' : 'false' }}) return this.toggleSign(e.target);
                const digits = (e.data || '').replace(/\D/g, '');
                if (digits) this.set(parseInt(String(abs) + digits), e.target);
            } else if (type === 'insertFromPaste' || type === 'insertFromDrop') {
                e.preventDefault();
                const text = e.data ?? e.dataTransfer?.getData('text') ?? '';
                this.set(parseInt(text.replace(/\D/g, '') || '0'), e.target);
            } else if (type.startsWith('delete')) {
                e.preventDefault();
                this.set(type.includes('Word') || type.includes('Line') ? 0 : Math.floor(abs / 10), e.target);
            }
        },
        // Fallback (teclados que não emitem beforeinput cancelável)
        input(e) {
            this.set(parseInt(e.target.value.replace(/\D/g, '').slice(-13) || '0'), e.target);
        },
        toggleSign(el) { this.cents = -(parseInt(this.cents || 0)); if (el) el.value = this.display },
     }">
    @if ($label)
        <label for="f-{{ $model }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
    @endif
    <div @class([
        'flex items-center gap-2 rounded-xl border bg-card px-4 transition focus-within:border-brand focus-within:ring-4 focus-within:ring-brand/15',
        'border-rose-500' => $errors->has($model),
        'border-line' => ! $errors->has($model),
    ])>
        @if ($allowNegative)
            <button type="button" @click="toggleSign($refs.field)" class="tap -ml-1 rounded-lg px-2 py-1 text-lg font-bold"
                    :class="negative ? 'text-expense' : 'text-muted'" aria-label="Alternar positivo/negativo">
                <span x-text="negative ? '−' : '+'"></span>
            </button>
        @endif
        <span class="text-muted" :class="negative && 'text-expense'">R$</span>
        <input id="f-{{ $model }}" type="text" inputmode="numeric" autocomplete="off"
               :value="display" x-ref="field" @beforeinput="beforeinput($event)" @input="input($event)"
               @focus="$nextTick(() => $event.target.setSelectionRange(99, 99))"
               class="w-full bg-transparent py-3 text-lg font-semibold tabular-nums outline-none" :class="negative && 'text-expense'">
    </div>
    @error($model)
        <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @enderror
</div>
