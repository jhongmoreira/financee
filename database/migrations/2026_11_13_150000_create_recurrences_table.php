<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // income | expense
            $table->string('description', 120);
            $table->unsignedBigInteger('amount');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('frequency', 12)->default('monthly'); // weekly | monthly | bimonthly | quarterly | semiannual | yearly
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('auto_mark_paid')->default(false); // débito automático
            $table->date('generated_until')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'end_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurrence_id')->nullable()->after('installment_total')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_id');
        });
        Schema::dropIfExists('recurrences');
    }
};
