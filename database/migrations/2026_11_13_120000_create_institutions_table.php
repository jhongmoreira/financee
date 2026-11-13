<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 20)->default('bank'); // bank | broker | other
            $table->string('color', 9);
            $table->string('short', 3);
        });

        $rows = array_map(fn (array $i) => [
            'name' => $i[0], 'code' => $i[1], 'type' => $i[2], 'color' => $i[3], 'short' => $i[4],
        ], require database_path('data/institutions.php'));

        DB::table('institutions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
