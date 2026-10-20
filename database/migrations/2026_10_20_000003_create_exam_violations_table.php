<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3, langkah 5b: catatan pelanggaran CBT pindah ke engine terpadu.
 *
 * Tabel lama `cbt_violations` hanya menyimpan `{result_id, type}`. Padanan di
 * engine menggantung ke `exam_attempts` (bukan `exam_results`, karena attempt
 * selalu ada lebih dulu) dengan jejak `sumber_tabel`/`sumber_id` agar pemetaan
 * dari `cbt_violations` idempoten — sama seperti tabel `exam_*` lainnya.
 *
 * Murni aditif: tidak menyentuh tabel lama, sehingga jalur lama tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_violations')) {
            Schema::create('exam_violations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
                $table->string('tipe', 40);
                $table->string('sumber_tabel', 60)->nullable();
                $table->unsignedBigInteger('sumber_id')->nullable();
                $table->timestamps();

                $table->index(['attempt_id', 'tipe']);
                $table->index(['sumber_tabel', 'sumber_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_violations');
    }
};
