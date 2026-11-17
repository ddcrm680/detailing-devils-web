<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_deliveries', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();

            $table->string('source', 20);
            // ID zero is reserved for a source's permanent activation checkpoint.
            $table->unsignedBigInteger('source_record_id');
            $table->unsignedBigInteger('checkpoint_id')->nullable();
            $table->string('record_key', 100)->unique();
            $table->uuid('reference')->unique();
            $table->string('status', 20);
            $table->longText('payload')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->uuid('processing_token')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error', 50)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_record_id']);
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Preserve lead delivery checkpoints and delivery references. Review rollback before removing this table.'
        );
    }
};
