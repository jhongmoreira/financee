<div>
    <x-ui.app-bar title="Categorias" :back="route('more')">
        <x-slot:actions>
            <button type="button" wire:click="create" class="tap rounded-full p-2 text-brand hover:bg-card" aria-label="Nova categoria">
                @svg('heroicon-o-plus-circle', 'size-7')
            </button>
        </x-slot:actions>
        <x-slot:below>
            <div class="px-4 pb-3">
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-card p-1 ring-1 ring-line">
                    @foreach (\App\Models\Category::TYPES as $value => $label)
                        <button type="button" wire:click="$set('tab', '{{ $value }}')"
                                @class([
                                    'tap rounded-lg py-2 text-sm font-semibold',
                                    'bg-expense/10 text-expense' => $tab === $value && $value === 'expense',
                                    'bg-income/10 text-income' => $tab === $value && $value === 'income',
                                    'text-muted' => $tab !== $value,
                                ])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </x-slot:below>
    </x-ui.app-bar>

    <div class="space-y-3 px-4 pt-1">
        @forelse ($this->categories as $category)
            <x-ui.card wire:key="cat-{{ $category->id }}" class="overflow-hidden">
                <div class="flex items-center">
                    <button type="button" wire:click="edit({{ $category->id }})" class="tap flex min-w-0 flex-1 items-center gap-3 px-4 py-3 text-left hover:bg-card-hover">
                        <x-ui.badge-avatar :color="$category->color" :icon="$category->icon" class="size-10" />
                        <span class="min-w-0">
                            <span class="block truncate font-semibold">{{ $category->name }}</span>
                            @if ($category->children->isNotEmpty())
                                <span class="block text-sm text-muted">{{ $category->children->count() }} {{ Str::plural('subcategoria', $category->children->count()) }}</span>
                            @endif
                        </span>
                    </button>
                    <button type="button" wire:click="create({{ $category->id }})" class="tap mr-2 rounded-full p-2 text-muted hover:bg-card-hover" aria-label="Adicionar subcategoria em {{ $category->name }}">
                        @svg('heroicon-o-plus', 'size-5')
                    </button>
                </div>
                @if ($category->children->isNotEmpty())
                    <div class="flex flex-wrap gap-2 border-t border-line px-4 py-3">
                        @foreach ($category->children as $child)
                            <button type="button" wire:key="cat-{{ $child->id }}" wire:click="edit({{ $child->id }})"
                                    class="tap flex items-center gap-1.5 rounded-full border border-line bg-canvas px-3 py-1.5 text-sm hover:bg-card-hover">
                                <span class="size-2 rounded-full" style="background-color: {{ $child->color }}"></span>
                                {{ $child->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        @empty
            <x-ui.card>
                <x-ui.empty-state icon="tag" title="Nenhuma categoria" description="Crie categorias para organizar seus lançamentos.">
                    <x-ui.button type="button" wire:click="create">@svg('heroicon-o-plus', 'size-5') Nova categoria</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @endforelse
    </div>

    <x-ui.bottom-sheet name="category-form" :title="$editingId ? 'Editar categoria' : ($parent_id ? 'Nova subcategoria' : 'Nova categoria')">
        <form wire:submit="save" class="space-y-4">
            <div class="flex items-center gap-3">
                <x-ui.badge-avatar :color="$color" :icon="$icon" class="size-12" />
                <div class="flex-1">
                    <x-ui.input name="name" wire:model="name" placeholder="Nome da categoria" maxlength="40" aria-label="Nome" />
                </div>
            </div>
            @if (! $editing || $editing->children_count === 0)
                <x-ui.select label="Dentro de" name="parent_id" wire:model="parent_id" :options="$parents" placeholder="— Categoria principal —" />
            @endif
            <x-ui.icon-picker wire:model.live="icon" />
            <x-ui.color-picker wire:model.live="color" />

            <x-ui.button class="w-full">{{ $editingId ? 'Salvar alterações' : 'Criar' }}</x-ui.button>

            @if ($editing)
                <x-ui.button type="button" variant="secondary" class="w-full !text-rose-600 dark:!text-rose-400" wire:click="delete"
                    wire:confirm="Excluir “{{ $editing->name }}”{{ $editing->children_count ? ' e suas '.$editing->children_count.' subcategorias' : '' }}?">
                    Excluir categoria
                </x-ui.button>
            @endif
        </form>
    </x-ui.bottom-sheet>
</div>
