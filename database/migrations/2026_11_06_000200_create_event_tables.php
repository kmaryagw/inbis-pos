<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            // Dipakai di nomor transaksi: POS-[code]-YYYYMMDD-NNNN
            $table->string('code', 12)->unique();
            $table->string('name', 150)->unique();
            $table->string('location', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->string('person_in_charge', 100);
            $table->string('receipt_header', 100)->nullable();
            $table->string('receipt_footer', 150)->nullable();
            $table->string('status', 10)->default('draft')->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // Kasir yang ditugaskan pada kegiatan
        Schema::create('event_user', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'user_id']);
        });

        Schema::create('event_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('selling_price');
            $table->unsignedInteger('opening_stock')->default(0);
            // Saldo stok berjalan; selalu diubah bersamaan dengan stock_movements
            $table->integer('current_stock')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_product_id')->constrained()->cascadeOnDelete();
            // opening | in | out | sale | void
            $table->string('type', 10);
            // Bertanda: positif menambah stok, negatif mengurangi
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_product_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('event_products');
        Schema::dropIfExists('event_user');
        Schema::dropIfExists('events');
    }
};
