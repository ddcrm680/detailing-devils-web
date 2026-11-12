<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['studios', 'studio_images'] as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException('Studio migration stopped: a target table already exists. Review ownership before continuing.');
            }
        }

        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->text('address');
            $table->string('city', 100)->nullable();
            $table->string('state', 100);
            $table->char('country_code', 2)->nullable();
            $table->json('phones');
            $table->string('map_url', 2048)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['status', 'sort_order', 'id']);
        });

        Schema::create('studio_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->restrictOnDelete();
            $table->string('path', 255)->unique();
            $table->string('alt_text', 255)->default('');
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
            $table->index(['studio_id', 'sort_order', 'id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Studio rollback stopped: preserve studio records and media; use the reviewed recovery plan.');
    }
};
