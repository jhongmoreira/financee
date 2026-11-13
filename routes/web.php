<?php

use App\Http\Controllers\ServiceWorkerController;
use App\Livewire\Accounts;
use App\Livewire\Cards;
use App\Livewire\Categories;
use App\Livewire\Home;
use App\Livewire\More;
use App\Livewire\Profile;
use App\Livewire\Transactions;
use Illuminate\Support\Facades\Route;

// PWA
Route::get('/sw.js', ServiceWorkerController::class)->name('sw');
Route::view('/offline', 'offline')->name('offline');

Route::middleware('auth')->group(function () {
    Route::get('/', Home::class)->name('home');
    Route::get('/lancamentos', Transactions\Index::class)->name('transactions');
    Route::view('/planejamento', 'pages.planning')->name('planning');
    Route::get('/mais', More::class)->name('more');
    Route::get('/perfil', Profile::class)->name('profile');

    Route::get('/contas', Accounts\Index::class)->name('accounts');
    Route::get('/cartoes', Cards\Index::class)->name('cards');
    Route::get('/categorias', Categories\Index::class)->name('categories');
});
