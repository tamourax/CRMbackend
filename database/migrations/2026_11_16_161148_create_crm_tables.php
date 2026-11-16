<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('company');
            $table->string('industry');
            $table->string('status', 20)->default('prospect');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->decimal('value', 14, 2);
            $table->string('stage', 20)->default('lead');
            $table->date('expected_close_date')->nullable();
            $table->text('description')->nullable();
            $table->text('lost_reason')->nullable();
            $table->timestamps();
            $table->index('stage');
            $table->index('expected_close_date');
        });
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_status_check CHECK (status IN ('prospect','active','inactive'))");
        DB::statement("ALTER TABLE deals ADD CONSTRAINT deals_stage_check CHECK (stage IN ('lead','qualified','proposal','negotiation','won','lost'))");
        DB::statement('ALTER TABLE deals ADD CONSTRAINT deals_value_check CHECK (value >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
        Schema::dropIfExists('customers');
    }
};
