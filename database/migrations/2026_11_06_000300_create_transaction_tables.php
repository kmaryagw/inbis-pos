<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            // Dibuat di browser; mencegah transaksi ganda saat tombol ditekan berulang / jaringan retry
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('transacted_at');
            $table->unsignedInteger('item_count');
            $table->unsignedBigInteger('total');
            $table->string('payment_method', 10);
            $table->unsignedBigInteger('amount_paid');
            $table->unsignedBigInteger('change_amount')->default(0);
            $table->string('status', 10)->default('paid');
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'transacted_at']);
            $table->index(['event_id', 'status']);
            $table->index('cashier_id');
        });

        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_product_id')->constrained()->restrictOnDelete();
            $table->string('product_code_snapshot', 50);
            $table->string('product_name_snapshot', 150);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal');
        });

        // Penomoran urut per kegiatan per hari, dikunci dengan SELECT ... FOR UPDATE
        Schema::create('transaction_counters', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['event_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_counters');
        Schema::dropIfExists('transaction_details');
        Schema::dropIfExists('transactions');
    }
};
