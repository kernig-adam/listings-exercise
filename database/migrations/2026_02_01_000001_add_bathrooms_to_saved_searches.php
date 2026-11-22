<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive and nullable ("any"), so saved searches that already exist keep matching as before.
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_bathrooms')->nullable();
            $table->unsignedTinyInteger('max_bathrooms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropColumn(['min_bathrooms', 'max_bathrooms']);
        });
    }
};
