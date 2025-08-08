<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();

            $table->string('transfer_number')->unique();

            // Link to the safe type (e.g., Western Union, Bank)


//            $table->unsignedBigInteger('transfer_type_id');
//            $table->foreign('transfer_type_id')->references('id')->on('safe_types')
//                ->onDelete('cascade');
            $table->foreignId('transfer_type_id')->constrained('safe_types');

            // Customer details
            $table->string('customer_name');
            $table->string('phone_number');

            // Transfer values
            $table->decimal('sent_amount', 12, 2);
            $table->decimal('transfer_cost', 12, 2);
            $table->decimal('customer_price', 12, 2);
            $table->decimal('receiver_net_amount', 12, 2)->default(0); // auto-calculated

            // Status of the remittance
            $table->enum('status', [
                'pending_verification',
                'checked',
                'partially_delivered',  // New status
                'delivered',
                'canceled'
            ])->default('pending_verification');

            // Delivery details
            $table->foreignId('delivery_safe_type_id')
                ->nullable()
                ->constrained('safe_types')
                ->onDelete('set null')
                ->comment('Safe type used for delivery payment');


            $table->decimal('delivery_amount', 12, 2)
                ->nullable()
                ->comment('Actual amount paid for delivery');

            $table->text('delivery_notes')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Optional
            $table->text('notes')->nullable();
            $table->string('transfer_photo')->nullable();

            $table->decimal('total_delivered_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);


            // Meta
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
