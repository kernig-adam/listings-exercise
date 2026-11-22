<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A null criterion means "any".
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_price')->nullable();
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();
            $table->unsignedTinyInteger('max_bedrooms')->nullable();
            $table->timestamps();

            // "List/count this user's saved searches" (page and the 20-search
            // cap). Explicit: only MySQL auto-indexes foreign keys; SQLite and
            // PostgreSQL don't.
            $table->index('user_id');
        });

        // No rows for a search means "any region". The unique pair also serves
        // "regions of this search" (leading column); the region index serves the
        // matcher's "which searches want a listing in this region" lookup.
        Schema::create('saved_search_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $table->string('region');

            $table->unique(['saved_search_id', 'region']);
            $table->index('region');
        });

        // As above, for property types ("any type" when no rows).
        Schema::create('saved_search_property_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $table->string('property_type');

            $table->unique(['saved_search_id', 'property_type']);
            $table->index('property_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_search_property_types');
        Schema::dropIfExists('saved_search_regions');
        Schema::dropIfExists('saved_searches');
    }
};
