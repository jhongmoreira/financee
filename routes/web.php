<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ServiceWorkerController;
use App\Livewire\Accounts;
use App\Livewire\Cards;
use App\Livewire\Categories;
use App\Livewire\Home;
use App\Livewire\Investments;
use App\Livewire\More;
use App\Livewire\Notifications;
use App\Livewire\Planning;
use App\Livewire\Profile;
use App\Livewire\Recurrences;
use App\Livewire\Reports;
use App\Livewire\Transactions;
use Illuminate\Support\Facades\Route;

// PWA
Route::get('/sw.js', ServiceWorkerController::class)->name('sw');
Route::view('/offline', 'offline')->name('offline');

Route::middleware('auth')->group(function () {
    Route::get('/', Home::class)->name('home');
    Route::get('/lancamentos', Transactions\Index::class)->name('transactions');
    Route::get('/planejamento', Planning::class)->name('planning');
    Route::get('/mais', More::class)->name('more');
    Route::get('/perfil', Profile::class)->name('profile');

    Route::get('/contas', Accounts\Index::class)->name('accounts');
    Route::get('/cartoes', Cards\Index::class)->name('cards');
    Route::get('/cartoes/{card}/fatura', Cards\Invoice::class)->name('cards.invoice');
    Route::get('/categorias', Categories\Index::class)->name('categories');
    Route::get('/fixos', Recurrences\Index::class)->name('recurrences');
    Route::get('/investimentos', Investments\Index::class)->name('investments');
    Route::get('/relatorios', Reports\Index::class)->name('reports');
    Route::get('/notificacoes', Notifications::class)->name('notifications');
    Route::post('/push/assinar', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/cancelar', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::get('/exportar/lancamentos.csv', [ExportController::class, 'csv'])->name('export.csv');
    Route::get('/exportar/relatorio.pdf', [ExportController::class, 'pdf'])->name('export.pdf');
    Route::get('/exportar/backup.json', [ExportController::class, 'backup'])->name('export.backup');
    Route::get('/investimentos/ativo/{asset}', Investments\Asset::class)->name('investments.asset');
});
