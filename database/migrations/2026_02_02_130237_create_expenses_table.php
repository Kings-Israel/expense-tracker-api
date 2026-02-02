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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('original_amount', 15, 2);
            $table->string('original_currency', 3);
            $table->decimal('converted_amount', 15, 2);
            $table->string('converted_currency', 3);
            $table->decimal('conversion_rate', 15, 6);
            $table->text('message');
            $table->string('source')->nullable(); // 'bank' or 'mobile_money'
            $table->string('reference')->nullable();
            $table->timestamp('transaction_date');
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
