<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumns('users', ['id', 'name', 'email', 'password', 'remember_token'])
            || Schema::hasColumn('users', 'role')
        ) {
            throw new RuntimeException('Authorization migration stopped: review the canonical users table before adding its role column.');
        }

        Schema::table(
            'users',
            function (Blueprint $table) {
                // Null/unknown roles receive no SEO access; no existing account is promoted.
                $table->string('role', 30)->nullable();
            }
        );
    }

    public function down(): void
    {
        throw new RuntimeException('Authorization rollback stopped: preserve role assignments and use an approved recovery plan.');
    }
};
