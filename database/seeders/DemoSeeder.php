<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\InvestmentAsset;
use App\Models\InvestmentMovement;
use App\Models\InvestmentPortfolio;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\DefaultUserData;
use App\Services\InvestmentService;
use App\Services\InvoiceService;
use App\Services\RecurrenceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Usuário de demonstração com ~6 meses de dados (somente para desenvolvimento).
 * Login: demo@financee.test / demo12345
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::where('email', 'demo@financee.test')->delete();
        $user = User::create(['name' => 'Ana Demonstração', 'email' => 'demo@financee.test', 'password' => 'demo12345']);
        app(DefaultUserData::class)->createFor($user);
        Auth::setUser($user);

        mt_srand(42);
        $cat = fn (string $name) => Category::where('name', $name)->value('id');
        $today = CarbonImmutable::today();
        $start = $today->subMonthsNoOverflow(5)->startOfMonth();

        $nubank = Account::create(['name' => 'Nubank', 'type' => 'checking', 'color' => '#820ad1', 'initial_balance' => 250000, 'institution_id' => 1]);
        Account::where('name', 'Carteira')->update(['initial_balance' => 15000]);
        $card = CreditCard::create(['name' => 'Nubank Roxinho', 'brand' => 'mastercard', 'limit' => 800000, 'closing_day' => 3, 'due_day' => 10, 'color' => '#820ad1', 'payment_account_id' => $nubank->id, 'institution_id' => 1]);

        // Fixos
        $recurrences = app(RecurrenceService::class);
        foreach ([
            ['income', 'Salário', 650000, 'Salário', 5],
            ['expense', 'Aluguel', 180000, 'Aluguel', 10],
            ['expense', 'Internet', 9990, 'Internet', 15],
            ['expense', 'Energia', 18000, 'Energia', 20],
        ] as [$type, $desc, $amount, $category, $day]) {
            $r = Recurrence::create(['type' => $type, 'description' => $desc, 'amount' => $amount, 'category_id' => $cat($category),
                'account_id' => $nubank->id, 'frequency' => 'monthly', 'start_date' => $start->setDay($day)]);
            $recurrences->generate($r);
        }
        // Passado: tudo pago
        Transaction::query()->whereNull('paid_at')->where('date', '<', $today->toDateString())->get()->each(fn ($t) => $t->update(['paid_at' => $t->date]));

        // Gastos do dia a dia (cartão e conta)
        $invoices = app(InvoiceService::class);
        $daily = [['Mercado', 'Mercado', 8000, 35000], ['iFood', 'Delivery', 3500, 9000], ['Uber', 'Aplicativo', 1500, 4500],
            ['Posto Shell', 'Combustível', 15000, 25000], ['Farmácia', 'Farmácia', 2000, 12000], ['Cinema', 'Cinema e shows', 4000, 9000]];

        for ($date = $start; $date->lessThanOrEqualTo($today); $date = $date->addDays(mt_rand(1, 3))) {
            [$desc, $category, $min, $max] = $daily[mt_rand(0, count($daily) - 1)];
            $amount = mt_rand($min, $max);
            if (mt_rand(0, 2) > 0) {
                $invoices->createPurchase($card, $desc, $cat($category), $date, $amount);
            } else {
                Transaction::create(['type' => 'expense', 'description' => $desc, 'amount' => $amount, 'date' => $date, 'paid_at' => $date, 'account_id' => $nubank->id, 'category_id' => $cat($category)]);
            }
        }
        $invoices->createPurchase($card, 'Notebook', $cat('Eletrônicos'), $today->subMonthsNoOverflow(2), 459990, 10);

        // Faturas passadas pagas
        foreach ($card->invoices()->get() as $invoice) {
            if ($invoice->due_date->lessThan($today)) {
                $remaining = $invoices->summary($invoice)['remaining'];
                if ($remaining > 0) {
                    $invoices->pay($invoice, $nubank, $remaining, $invoice->due_date);
                }
            }
        }

        // Orçamentos
        $budgets = app(BudgetService::class);
        foreach (['Alimentação' => 120000, 'Transporte' => 60000, 'Lazer' => 30000, 'Saúde' => 25000] as $name => $amount) {
            $budgets->set(Category::where('name', $name)->whereNull('parent_id')->firstOrFail(), $amount, null);
        }

        // Investimentos
        $portfolio = InvestmentPortfolio::create(['name' => 'XP Investimentos', 'color' => '#111111']);
        $service = app(InvestmentService::class);
        foreach ([['CDB Banco X 2028', 'cdb', '110% CDI', 1000000, 1062000], ['HGLG11', 'fii', null, 300000, 312500], ['ITSA4', 'stock', null, 200000, 188000]] as [$name, $class, $rate, $in, $now]) {
            $asset = InvestmentAsset::create(['portfolio_id' => $portfolio->id, 'name' => $name, 'class' => $class, 'rate_description' => $rate]);
            InvestmentMovement::create(['asset_id' => $asset->id, 'type' => 'contribution', 'amount' => $in, 'date' => $start]);
            InvestmentMovement::create(['asset_id' => $asset->id, 'type' => 'valuation', 'amount' => $now, 'date' => $today->subDays(3)]);
            $service->recalculate($asset);
        }

        Auth::forgetUser();
    }
}
