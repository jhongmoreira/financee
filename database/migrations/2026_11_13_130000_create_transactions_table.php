<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // income | expense
            $table->string('description', 120);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('amount'); // centavos, sempre positivo
            $table->date('date'); // data do lançamento / vencimento
            $table->date('paid_at')->nullable(); // null = previsto (pendente)
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_adjustment')->default(false); // ajuste de saldo (fora de relatórios)
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['account_id', 'paid_at']);
            $table->index(['category_id', 'date']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('to_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('date');
            $table->date('paid_at')->nullable();
            $table->string('description', 120)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('transactions');
    }
};
