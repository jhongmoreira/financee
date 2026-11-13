<?php

use App\Models\User;
use App\Services\DefaultUserData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('type', 10); // expense | income
            $table->string('name');
            $table->string('icon', 40);
            $table->string('color', 9);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'parent_id']);
        });

        // Usuários que já existiam antes desta etapa recebem as categorias e a conta padrão.
        User::query()->each(fn (User $user) => app(DefaultUserData::class)->createFor($user));
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
