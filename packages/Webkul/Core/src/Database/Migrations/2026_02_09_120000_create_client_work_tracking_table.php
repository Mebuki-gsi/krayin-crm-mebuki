<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_work_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('client_cnpj', 20);
            $table->string('client_name')->nullable();
            $table->string('classification')->nullable();
            $table->boolean('is_checked')->default(true);
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('unchecked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['user_id', 'client_cnpj']);
            $table->index(['user_id', 'checked_at']);
            $table->index('checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_work_tracking');
    }
};
