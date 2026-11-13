<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 60);
            $table->string('color', 9);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('investment_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained('investment_portfolios')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('ticker', 20)->nullable();
            $table->string('class', 20); // stock | fii | etf | fixed_income | cdb | lci_lca | treasury | fund | crypto | pension | other
            $table->string('rate_description', 60)->nullable(); // "110% CDI", "IPCA + 6%"
            $table->date('maturity_date')->nullable();
            $table->decimal('quantity', 20, 8)->nullable();
            $table->bigInteger('current_value')->default(0); // centavos (recalculado a partir dos movimentos)
            $table->date('current_value_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('investment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('investment_assets')->cascadeOnDelete();
            $table->string('type', 15); // contribution | withdrawal | income | valuation
            $table->unsignedBigInteger('amount');
            $table->decimal('quantity', 20, 8)->nullable();
            $table->date('date');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete(); // conta de origem/destino
            $table->string('notes', 200)->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'date']);
            $table->index(['account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_movements');
        Schema::dropIfExists('investment_assets');
        Schema::dropIfExists('investment_portfolios');
    }
};
