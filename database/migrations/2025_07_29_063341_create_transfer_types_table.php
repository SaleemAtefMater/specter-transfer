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
        Schema::create('transfer_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('transfer_types', function (Blueprint $table) {
            if (!Schema::hasColumn('transfer_types', 'safe_type_id')) {
                $table->foreignId('safe_type_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('safe_types')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_types');

        Schema::table('transfer_types', function (Blueprint $table) {
            if (Schema::hasColumn('transfer_types', 'safe_type_id')) {
                $table->dropForeign(['safe_type_id']);
                $table->dropColumn('safe_type_id');
            }
        });
    }
};
