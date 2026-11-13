<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fatura: identificada pelo mês do VENCIMENTO (reference_month = "YYYY-MM").
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();
            $table->string('reference_month', 7);
            $table->date('closing_date');
            $table->date('due_date');
            $table->bigInteger('carried_in')->default(0);  // saldo vindo da fatura anterior (+ dívida / − crédito)
            $table->bigInteger('carried_out')->default(0); // saldo levado para a próxima fatura
            $table->timestamps();

            $table->unique(['credit_card_id', 'reference_month']);
            $table->index(['user_id', 'due_date']);
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_at');
            $table->bigInteger('carried_amount')->default(0); // quanto este pagamento levou para a próxima fatura
            $table->timestamps();

            $table->index(['account_id', 'paid_at']);
        });

        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description', 120);
            $table->unsignedBigInteger('total_amount');
            $table->unsignedSmallInteger('installments');
            $table->date('first_date');
            $table->foreignId('credit_card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('credit_card_id')->nullable()->after('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->after('credit_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_plan_id')->nullable()->after('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number')->nullable()->after('installment_plan_id');
            $table->unsignedSmallInteger('installment_total')->nullable()->after('installment_number');

            $table->index('invoice_id');
            $table->index('credit_card_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installment_plan_id');
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropConstrainedForeignId('credit_card_id');
            $table->dropColumn(['installment_number', 'installment_total']);
        });
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoices');
    }
};
