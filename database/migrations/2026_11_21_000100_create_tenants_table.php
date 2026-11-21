<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pemilik produk: dosen atau tenant inkubasi. Tidak pernah dihapus agar rekap lama tetap utuh.
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150)->index();
            $table->string('type', 10);                       // dosen | tenant
            $table->string('unit', 150)->nullable();          // fakultas / prodi / nama usaha
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('category_id')->constrained()->restrictOnDelete();
        });

        // Snapshot pemilik saat terjual: bila kepemilikan produk berpindah, rekap lama tidak ikut berubah
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('event_product_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::dropIfExists('tenants');
    }
};
