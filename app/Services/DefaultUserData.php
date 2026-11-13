<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cria a estrutura inicial de um usuário: categorias padrão e a conta "Carteira".
 * Idempotente: não faz nada se o usuário já tiver categorias/contas.
 */
class DefaultUserData
{
    /** [nome, ícone, cor, [subcategorias]] */
    public const EXPENSES = [
        ['Alimentação', 'shopping-cart', '#ea580c', ['Mercado', 'Restaurante', 'Delivery', 'Padaria']],
        ['Moradia', 'home', '#2563eb', ['Aluguel', 'Condomínio', 'Energia', 'Água', 'Gás', 'Internet', 'Manutenção']],
        ['Transporte', 'truck', '#0284c7', ['Combustível', 'Aplicativo', 'Transporte público', 'Manutenção', 'Estacionamento']],
        ['Saúde', 'heart', '#dc2626', ['Plano de saúde', 'Farmácia', 'Consultas', 'Academia']],
        ['Educação', 'academic-cap', '#4f46e5', ['Mensalidade', 'Cursos', 'Livros']],
        ['Lazer', 'ticket', '#c026d3', ['Viagens', 'Cinema e shows', 'Passeios']],
        ['Compras', 'shopping-bag', '#db2777', ['Roupas', 'Eletrônicos', 'Casa']],
        ['Assinaturas', 'tv', '#7c3aed', ['Streaming', 'Celular', 'Software']],
        ['Cuidados pessoais', 'sparkles', '#e11d48', []],
        ['Pets', 'bug-ant', '#65a30d', []],
        ['Presentes e doações', 'gift', '#d97706', []],
        ['Impostos e taxas', 'document-text', '#475569', ['Impostos', 'Tarifas bancárias', 'Juros e multas']],
        ['Outros', 'ellipsis-horizontal', '#6b7280', []],
    ];

    public const INCOMES = [
        ['Salário', 'briefcase', '#059669', []],
        ['Freelance', 'computer-desktop', '#0d9488', []],
        ['Rendimentos', 'arrow-trending-up', '#10b981', []],
        ['Reembolsos', 'receipt-percent', '#0284c7', []],
        ['Presentes', 'gift', '#d97706', []],
        ['Outras receitas', 'ellipsis-horizontal', '#6b7280', []],
    ];

    public function createFor(User $user): void
    {
        DB::transaction(function () use ($user) {
            if (! Category::withoutGlobalScopes()->where('user_id', $user->id)->exists()) {
                $this->createCategories($user, 'expense', self::EXPENSES);
                $this->createCategories($user, 'income', self::INCOMES);
            }

            if (! Account::withoutGlobalScopes()->where('user_id', $user->id)->exists()) {
                $account = new Account(['name' => 'Carteira', 'type' => 'wallet', 'color' => '#059669']);
                $account->user_id = $user->id;
                $account->save();
            }
        });
    }

    /** @param list<array{0: string, 1: string, 2: string, 3: list<string>}> $definitions */
    private function createCategories(User $user, string $type, array $definitions): void
    {
        foreach ($definitions as $order => [$name, $icon, $color, $children]) {
            $parent = $this->category($user, $type, $name, $icon, $color, $order);

            foreach ($children as $childOrder => $childName) {
                $this->category($user, $type, $childName, $icon, $color, $childOrder, $parent->id);
            }
        }
    }

    private function category(User $user, string $type, string $name, string $icon, string $color, int $order, ?int $parentId = null): Category
    {
        $category = new Category([
            'type' => $type, 'name' => $name, 'icon' => $icon, 'color' => $color,
            'sort_order' => $order, 'parent_id' => $parentId,
        ]);
        $category->user_id = $user->id;
        $category->save();

        return $category;
    }
}
