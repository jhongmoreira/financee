<?php

use App\Livewire\Admin\Activity;
use App\Livewire\Admin\Users;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function events(?User $user = null): array
{
    return ActivityLog::query()->when($user, fn ($q) => $q->where('user_id', $user->id))->orderBy('id')->pluck('event')->all();
}

// ---------------------------------------------------------------- acesso à administração

it('só o administrador abre as telas de administração', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.activity'))->assertForbidden();

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee($admin->email);
    $this->actingAs($admin)->get(route('admin.activity'))->assertOk()->assertSee('Histórico');
    $this->actingAs($admin)->get(route('more'))->assertSee('Histórico de atividades');
});

it('o administrador continua sem ver as finanças dos outros', function () {
    $other = User::factory()->create();
    $this->actingAs($other);
    $account = Account::query()->create(['name' => 'Conta da outra pessoa', 'type' => 'checking', 'color' => '#4f46e5', 'initial_balance' => 50000]);
    Transaction::factory()->create(['user_id' => $other->id, 'description' => 'Gasto secreto']);
    Auth::logout();

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    expect(Account::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(fn () => Account::query()->findOrFail($account->id))->toThrow(ModelNotFoundException::class);

    $this->get(route('transactions'))->assertOk()->assertDontSee('Gasto secreto');
    $this->get(route('admin.users'))->assertSee('1 lançamento(s), 1 conta(s)')->assertDontSee('Gasto secreto')->assertDontSee('500,00');
});

// ---------------------------------------------------------------- histórico: acessos

it('registra login, senha errada e saída', function () {
    $user = User::factory()->create(['password' => 'segredo123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'errada']);
    $this->post('/login', ['email' => 'ninguem@exemplo.com', 'password' => 'x']);
    $this->post('/login', ['email' => $user->email, 'password' => 'segredo123']);
    $this->post('/logout');

    expect(events($user))->toBe(['login_failed', 'login', 'logout'])
        ->and(ActivityLog::query()->where('user_email', 'ninguem@exemplo.com')->value('description'))->toContain('não cadastrado');
});

it('registra a entrada automática do app (lembrar de mim) e a reabertura depois de um tempo', function () {
    $user = User::factory()->create(['password' => 'segredo123']);
    $login = $this->post('/login', ['email' => $user->email, 'password' => 'segredo123', 'remember' => '1']);
    $cookieName = Auth::guard('web')->getRecallerName();
    $remember = $login->getCookie($cookieName)->getValue();

    // App fechado e reaberto: sessão nova, só o cookie "lembrar de mim"
    $this->flushSession();
    Auth::forgetGuards();
    $this->withCookie($cookieName, $remember)->get('/')->assertOk();

    expect(events($user))->toBe(['login', 'login_auto', 'page_view']);

    // Mesma sessão, depois de 40 minutos parado: "Abriu o app"
    $this->travel(40)->minutes();
    $this->get(route('accounts'))->assertOk();
    expect(array_slice(events($user), -2))->toBe(['app_opened', 'page_view']);
});

it('registra as páginas abertas e os downloads, mas não as ações internas do Livewire', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('home'));
    $this->get(route('reports'));
    $this->get(route('export.csv'));

    $descriptions = ActivityLog::query()->where('event', 'page_view')->pluck('description')->all();
    expect($descriptions)->toBe(['Abriu Início', 'Abriu Relatórios', 'Exportou lançamentos (CSV)']);
});

// ---------------------------------------------------------------- histórico: alterações

it('registra criação, edição e exclusão com o nome do registro (sem valores)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $account = Account::query()->create(['name' => 'Nubank', 'type' => 'checking', 'color' => '#4f46e5', 'initial_balance' => 123456]);
    $account->update(['name' => 'Nubank PJ']);
    $this->travel(1)->minutes();
    $account->touch(); // só o updated_at: não conta como edição
    $account->delete();

    $logs = ActivityLog::query()->whereIn('event', ['created', 'updated', 'deleted'])->orderBy('id')->get();
    expect($logs->pluck('description')->all())->toBe(['Criou conta “Nubank”', 'Editou conta “Nubank PJ”', 'Excluiu conta “Nubank PJ”'])
        ->and($logs[1]->meta['campos'])->toBe(['name'])
        ->and($logs->pluck('description')->implode(' '))->not->toContain('1234');
});

it('rotinas automáticas (sem ninguém logado) não entram no histórico', function () {
    $user = User::factory()->create();
    Transaction::factory()->create(['user_id' => $user->id, 'description' => 'Gerado pelo cron']);

    expect(ActivityLog::query()->whereIn('event', ['created', 'updated', 'deleted'])->count())->toBe(0);
});

it('registra mudança de senha no perfil, mas não de tema', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->updateSetting('theme', 'dark');
    $user->update(['password' => 'outra-senha-123']);

    expect(ActivityLog::query()->where('event', 'profile_updated')->pluck('description')->all())->toBe(['Alterou o perfil: senha']);
});

it('se o histórico falhar, o app continua funcionando', function () {
    $this->actingAs(User::factory()->create());
    Schema::drop('activity_logs');

    $this->get(route('home'))->assertOk();
    Account::query()->create(['name' => 'Inter', 'type' => 'checking', 'color' => '#4f46e5', 'initial_balance' => 0]);
    expect(Account::query()->count())->toBe(1);
});

// ---------------------------------------------------------------- gestão de contas

it('desativar tira a pessoa de todos os aparelhos e impede o acesso', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['password' => 'segredo123']);

    $this->actingAs($admin);
    Livewire::test(Users::class)->call('toggleActive', $user->id);
    expect($user->refresh()->is_active)->toBeFalse();

    Auth::logout();
    $this->actingAs($user)->get(route('home'))->assertRedirect(route('login'));
    $this->assertGuest();
    expect(events($user))->toContain('blocked');

    $this->actingAs($admin);
    Livewire::test(Users::class)->call('toggleActive', $user->id);
    expect($user->refresh()->is_active)->toBeTrue();
});

it('o administrador não desativa nem exclui a própria conta', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test(Users::class)->call('toggleActive', $admin->id)->assertStatus(422);
    expect($admin->refresh()->is_active)->toBeTrue();
});

it('excluir exige digitar o e-mail e apaga a conta com todos os dados', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();
    Transaction::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($admin);
    Livewire::test(Users::class)->call('askDelete', $user->id)->set('confirmEmail', 'errado@x.com')->call('deleteUser')->assertHasErrors('confirmEmail');
    expect(User::query()->find($user->id))->not->toBeNull();

    Livewire::test(Users::class)->call('askDelete', $user->id)->set('confirmEmail', strtoupper($user->email))->call('deleteUser')->assertHasNoErrors();
    expect(User::query()->find($user->id))->toBeNull()
        ->and(DB::table('transactions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(ActivityLog::query()->where('event', 'admin')->value('description'))->toContain($user->email);
});

it('envia o link de redefinição de senha', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin);
    Livewire::test(Users::class)->call('sendReset', $user->id)->assertDispatched('toast');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('filtra o histórico por usuário e tipo', function () {
    $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Geral']);
    $ana = User::factory()->create(['name' => 'Ana Souza']);
    $this->actingAs($ana);
    Account::query()->create(['name' => 'Conta da Ana', 'type' => 'checking', 'color' => '#4f46e5', 'initial_balance' => 0]);
    Auth::logout();

    $this->actingAs($admin);
    Livewire::test(Activity::class)->set('usuario', (string) $ana->id)->set('tipo', 'alteracoes')
        ->assertSee('Criou conta “Conta da Ana”');
    Livewire::test(Activity::class)->set('usuario', (string) $ana->id)->set('tipo', 'acesso')
        ->assertDontSee('Conta da Ana');
});

it('o comando cria o administrador com senha temporária e os dados iniciais', function () {
    $this->artisan('app:create-admin', ['email' => 'Chefe@Exemplo.com', '--name' => 'Chefe'])
        ->expectsOutputToContain('Senha temporária:')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'chefe@exemplo.com')->firstOrFail();
    expect($admin->is_admin)->toBeTrue()
        ->and(Category::withoutGlobalScopes()->where('user_id', $admin->id)->count())->toBeGreaterThan(0);

    // Rodar de novo só confirma, sem trocar a senha
    $hash = $admin->password;
    $this->artisan('app:create-admin', ['email' => 'chefe@exemplo.com'])->assertSuccessful();
    expect($admin->refresh()->password)->toBe($hash);
});
