<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_urls') || Schema::hasColumn('studios', 'url_locked_at')) {
            throw new RuntimeException('URL setup stopped: review the existing schema before continuing.');
        }
        Schema::create('content_urls', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 32);
            $table->unsignedBigInteger('content_id');
            $table->string('path', 255)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['content_type', 'content_id']);
        });
        Schema::table('studios', function (Blueprint $table) {
            $table->timestamp('url_locked_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve URL reservations. Use the reviewed rollback procedure.');
    }
};
