<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->jsonb('translations')->nullable();
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->jsonb('translations')->nullable();
        });
        Schema::table('deals', function (Blueprint $table): void {
            $table->jsonb('translations')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->dropColumn('translations');
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('translations');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('translations');
        });
    }
};
