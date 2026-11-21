<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan internal untuk admin/kasir; tidak dicetak di struk
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('change_amount');
            $table->foreignId('note_updated_by')->nullable()->after('note')->constrained('users')->restrictOnDelete();
            $table->dateTime('note_updated_at')->nullable()->after('note_updated_by');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('note_updated_by');
            $table->dropColumn(['note', 'note_updated_at']);
        });
    }
};
