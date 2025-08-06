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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('transfer_type_id')->constrained('transfer_types');
            $table->string('customer_name');
            $table->string('phone_number');
            $table->decimal('sent_amount', 10, 2);
            $table->decimal('transfer_cost', 10, 2);
            $table->decimal('customer_price', 10, 2);
            $table->decimal('receiver_net_amount', 10, 2)->default(0);
            $table->enum('status', ['checked', 'delivered', 'canceled'])->default('checked');
            $table->text('notes')->nullable();
            $table->string('transfer_photo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
