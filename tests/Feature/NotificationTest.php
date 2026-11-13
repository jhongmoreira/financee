<?php

use App\Livewire\Notifications;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPortfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\FinanceAlert;
use App\Services\BudgetService;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Support\VapidKeys;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-15 09:00');
    Notification::fake();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->account = Account::create(['name' => 'Conta', 'type' => 'checking', 'color' => '#059669', 'initial_balance' => 500000]);
    $this->service = app(NotificationService::class);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function titles(User $user): array
{
    return Notification::sent($user, FinanceAlert::class)->map(fn (FinanceAlert $n) => $n->title)->all();
}

it('avisa contas que vencem hoje, amanhã e as vencidas ontem, sem repetir', function () {
    $pending = fn ($desc, $date, $amount = 10000) => Transaction::factory()->pending()->create(['account_id' => $this->account->id, 'description' => $desc, 'date' => $date, 'amount' => $amount]);
    $pending('Luz', '2026-11-15');
    $pending('Água', '2026-11-15');
    $pending('Internet', '2026-11-16');
    $pending('Aluguel', '2026-11-14');
    $pending('Longe', '2026-11-25');

    Auth::forgetUser();
    expect($this->service->runDaily())->toBe(3)
        ->and(titles($this->user))->toEqualCanonicalizing(['2 contas vencem hoje', 'Conta vence amanhã', 'Conta vencida ontem']);

    expect($this->service->runDaily())->toBe(0); // não repete
});

it('respeita as preferências do usuário', function () {
    $this->user->updateSetting('notifications.due', false);
    Transaction::factory()->pending()->create(['account_id' => $this->account->id, 'date' => '2026-11-15']);

    Auth::forgetUser();
    expect($this->service->runDaily())->toBe(0);
});

it('avisa fatura que fecha hoje e que vence em breve', function () {
    $card = CreditCard::create(['name' => 'Roxinho', 'brand' => 'visa', 'limit' => 100000, 'closing_day' => 15, 'due_day' => 22, 'color' => '#7c3aed']);
    $other = CreditCard::create(['name' => 'Azul', 'brand' => 'visa', 'limit' => 100000, 'closing_day' => 8, 'due_day' => 16, 'color' => '#2563eb']);
    app(InvoiceService::class)->createPurchase($card, 'X', null, CarbonImmutable::parse('2026-11-10'), 12000);
    app(InvoiceService::class)->createPurchase($other, 'Y', null, CarbonImmutable::parse('2026-11-01'), 5000);

    Auth::forgetUser();
    $this->service->runDaily();

    expect(titles($this->user))->toEqualCanonicalizing(['Fatura Roxinho fechou', 'Fatura Azul vence amanhã']);
});

it('avisa saldo previsto negativo no máximo 1 vez por semana', function () {
    Transaction::factory()->pending()->create(['account_id' => $this->account->id, 'date' => '2026-12-10', 'amount' => 900000]);
    $this->user->updateSetting('notifications.due', false);

    CarbonImmutable::setTestNow('2026-11-16 09:00'); // segunda-feira
    Auth::forgetUser();
    $this->service->runDaily();
    CarbonImmutable::setTestNow('2026-11-19 09:00'); // mesma semana
    $this->service->runDaily();
    expect(titles($this->user))->toBe(['Atenção: saldo previsto negativo']);

    CarbonImmutable::setTestNow('2026-11-23 09:00'); // semana seguinte
    $this->service->runDaily();
    expect(titles($this->user))->toHaveCount(2);
});

it('lembra de atualizar investimentos no dia 1º', function () {
    $p = InvestmentPortfolio::create(['name' => 'XP', 'color' => '#000000']);
    InvestmentAsset::create(['portfolio_id' => $p->id, 'name' => 'CDB', 'class' => 'cdb']);
    CarbonImmutable::setTestNow('2026-12-01 09:00');

    Auth::forgetUser();
    $this->service->runDaily();

    expect(titles($this->user))->toBe(['Hora de atualizar seus investimentos']);
});

it('alerta orçamento em 80% e 100% uma vez cada', function () {
    $food = Category::create(['type' => 'expense', 'name' => 'Alimentação', 'icon' => 'shopping-cart', 'color' => '#ea580c']);
    app(BudgetService::class)->set($food, 10000, null);
    $spend = fn ($amount) => Transaction::factory()->create(['account_id' => $this->account->id, 'category_id' => $food->id, 'amount' => $amount, 'date' => '2026-11-10']);

    $spend(5000);
    $spend(3500); // 85%
    $spend(500);  // 90% (já avisou 80)
    $spend(2000); // 110%

    expect(titles($this->user))->toBe(['85% do orçamento de Alimentação', 'Orçamento de Alimentação estourado']);
});

it('rotina diária não mistura dados entre usuários', function () {
    $other = User::factory()->create();
    $foreign = new Account(['name' => 'Alheia', 'type' => 'checking', 'color' => '#000000']);
    $foreign->user_id = $other->id;
    $foreign->save();
    $t = new Transaction(['type' => 'expense', 'description' => 'Conta do outro', 'amount' => 100, 'date' => '2026-11-15', 'account_id' => $foreign->id]);
    $t->user_id = $other->id;
    $t->save();

    Auth::forgetUser();
    $this->service->runDaily();

    expect(titles($this->user))->toBe([])
        ->and(Notification::sent($other, FinanceAlert::class)->first()->body)->toContain('Conta do outro');
});

it('gera e reutiliza as chaves VAPID', function () {
    config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
    $first = VapidKeys::publicKey();

    config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
    expect($first)->toBeString()->not->toBeEmpty()->and(VapidKeys::publicKey())->toBe($first);
});

it('registra e remove a inscrição de push do dispositivo', function () {
    $payload = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'BPk', 'auth' => 'xyz']];

    $this->postJson(route('push.subscribe'), $payload)->assertOk();
    expect($this->user->pushSubscriptions()->count())->toBe(1);

    $this->postJson(route('push.unsubscribe'), ['endpoint' => $payload['endpoint']])->assertOk();
    expect($this->user->pushSubscriptions()->count())->toBe(0);
});

it('tela de notificações salva preferências e envia teste', function () {
    Livewire::test(Notifications::class)
        ->assertSee('Contas a vencer e vencidas')
        ->set('prefs.budget', false)->set('dueDays', 3)
        ->call('sendTest');

    expect($this->user->fresh()->wantsNotification('budget'))->toBeFalse()
        ->and($this->user->fresh()->dueDaysBefore())->toBe(3)
        ->and(titles($this->user))->toBe(['Teste do Financee']);
});
