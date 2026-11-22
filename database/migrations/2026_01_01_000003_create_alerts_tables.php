<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            // Restrict: an alert's go-live event must not vanish under it, so
            // any future outbox pruning has to be a deliberate decision.
            $table->foreignId('outbox_message_id')->constrained()->restrictOnDelete();
            $table->string('reason');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // One alert per user per go-live; makes reprocessing idempotent
            // (insert-or-skip). Leading user_id also serves "this user's alerts
            // newest first" and the unread count.
            $table->unique(['user_id', 'outbox_message_id']);
            // "Has this user been alerted about this listing before?" (reason)
            // and "is there an unread alert for it?" (flip-flop guard).
            $table->index(['user_id', 'listing_id']);
            // FK index: cascade when a listing is deleted.
            $table->index('listing_id');
        });

        Schema::create('alert_saved_search', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            // Cascade removes only the link; the alert itself stays.
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();

            // Leading alert_id serves "searches that matched this alert"; the
            // saved_search_id index serves the cascade on search delete.
            $table->unique(['alert_id', 'saved_search_id']);
            $table->index('saved_search_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_saved_search');
        Schema::dropIfExists('alerts');
    }
};
