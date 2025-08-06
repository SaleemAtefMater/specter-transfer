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
        Schema::create('safe_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('safe_type_id')->constrained('safe_types')->onDelete('cascade');
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('last_updated')->useCurrent();
            $table->unique('safe_type_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safe_balances');
    }
};
