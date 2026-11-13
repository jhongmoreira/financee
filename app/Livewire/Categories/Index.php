<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use App\Models\Transaction;
use App\Support\Palette;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, Category> $categories
 */
#[Title('Categorias')]
class Index extends Component
{
    #[Url(as: 'tipo')]
    public string $tab = 'expense';

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public ?string $parent_id = null;

    public string $icon = 'tag';

    public string $color = '#059669';

    public function mount(): void
    {
        if (! array_key_exists($this->tab, Category::TYPES)) {
            $this->tab = 'expense';
        }
    }

    public function create(?int $parentId = null): void
    {
        $this->resetForm();

        if ($parentId && $parent = Category::find($parentId)) {
            $this->parent_id = (string) $parent->id;
            $this->icon = $parent->icon;
            $this->color = $parent->color;
        }

        $this->dispatch('open-sheet', 'category-form');
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->parent_id = $category->parent_id ? (string) $category->parent_id : null;
        $this->icon = $category->icon;
        $this->color = $category->color;

        $this->dispatch('open-sheet', 'category-form');
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:40'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('user_id', Auth::id())->where('type', $this->tab)->whereNull('parent_id'),
                Rule::notIn(array_filter([$this->editingId])),
            ],
            'icon' => ['required', Rule::in(Palette::CATEGORY_ICONS)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($this->editingId) {
            $category = Category::findOrFail($this->editingId);

            // Uma categoria que tem subcategorias não pode virar subcategoria (máximo 2 níveis).
            if ($data['parent_id'] && $category->children()->exists()) {
                $this->addError('parent_id', 'Esta categoria tem subcategorias e não pode ficar dentro de outra.');

                return;
            }

            $category->update($data);
            $message = 'Categoria atualizada.';
        } else {
            Category::create($data + ['type' => $this->tab]);
            $message = 'Categoria criada.';
        }

        $this->dispatch('close-sheet', 'category-form');
        $this->dispatch('toast', message: $message);
        $this->resetForm();
    }

    public ?string $replacement_id = null;

    public function delete(): void
    {
        $category = Category::findOrFail($this->editingId);
        $ids = $this->familyIds($category);

        // Lançamentos da categoria (e subcategorias) vão para a categoria escolhida.
        if (Transaction::query()->whereIn('category_id', $ids)->exists()) {
            $this->validate([
                'replacement_id' => [
                    'required', 'integer', Rule::notIn($ids),
                    Rule::exists('categories', 'id')->where('user_id', Auth::id())->where('type', $category->type),
                ],
            ], ['replacement_id.required' => 'Escolha para onde mover os lançamentos.'], ['replacement_id' => 'categoria de destino']);

            Transaction::query()->whereIn('category_id', $ids)->update(['category_id' => (int) $this->replacement_id]);
        }

        $category->delete(); // subcategorias são removidas em cascata

        $this->dispatch('close-sheet', 'category-form');
        $this->dispatch('toast', message: 'Categoria excluída.');
        $this->resetForm();
    }

    /** @return Collection<int, Category> */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->ofType($this->tab)->roots()->with('children')
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public function render(): View
    {
        $editing = $this->editingId ? Category::withCount('children')->find($this->editingId) : null;
        $usage = $editing ? Transaction::query()->whereIn('category_id', $this->familyIds($editing))->count() : 0;

        return view('livewire.categories.index', [
            'parents' => $this->categories
                ->reject(fn (Category $c) => $c->id === $this->editingId)
                ->pluck('name', 'id')->all(),
            'editing' => $editing,
            'usage' => $usage,
            'replacements' => $editing && $usage ? $this->replacementOptions($editing) : [],
        ]);
    }

    /** @return list<int> */
    private function familyIds(Category $category): array
    {
        return [$category->id, ...$category->children()->pluck('id')->all()];
    }

    /** @return array<int, string> */
    private function replacementOptions(Category $category): array
    {
        $exclude = $this->familyIds($category);
        $options = [];

        foreach (Category::query()->ofType($category->type)->roots()->with('children')->orderBy('sort_order')->get() as $root) {
            if (! in_array($root->id, $exclude, true)) {
                $options[$root->id] = $root->name;
            }
            foreach ($root->children as $child) {
                if (! in_array($child->id, $exclude, true)) {
                    $options[$child->id] = $root->name.' › '.$child->name;
                }
            }
        }

        return $options;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'parent_id', 'icon', 'color', 'replacement_id');
    }
}
