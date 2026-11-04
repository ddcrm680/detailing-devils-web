<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumns('users', ['id', 'name', 'email', 'password', 'role'])) {
            throw new RuntimeException('SEO migration stopped: apply the canonical users and additive authorization migrations first.');
        }
        // This migration owns only SEO tables, never users or legacy business tables.
        foreach (['seo_metadata', 'seo_revisions'] as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException('SEO migration stopped: a target table already exists; review ownership and schema first.');
            }
        }
        Schema::create(
            'seo_metadata',
            function (Blueprint $table) {
                // The existing engine consumes one atomic snapshot, bounded to 8 MiB.
                $table->unsignedInteger('id')->primary();
                $table->string('origin');
                $table->unsignedBigInteger('version')->default(0);
                $table->longText('payload');
                $table->char('payload_hash', 64);
                $table->longText('sitemap_xml');
                $table->char('sitemap_hash', 64);
                $table->timestamp('updated_at');
            }
        );
        Schema::create(
            'seo_revisions',
            function (Blueprint $table) {
                $table->id();
                $table->string('page_id', 200);
                $table->string('locale', 10)->default('en');
                $table->string('kind', 20);
                $table->longText('fields');
                $table->unsignedBigInteger('base_version');
                $table->foreignId('administrator_id')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->unsignedBigInteger('source_revision_id')->nullable();
                $table->string('policy_reason', 500)->nullable();
                $table->timestamp('created_at');
                $table->index(['page_id', 'locale', 'id']);
            }
        );
    }

    public function down(): void
    {
        // Never delete tables/accounts/history through an automatic rollback, including partial DDL installs.
        throw new RuntimeException('SEO rollback stopped: preserve tables and use an approved backup/recovery plan.');
    }
};
