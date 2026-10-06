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
        Schema::create('tka_dimensions_enhanced', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->string('kode_dimensi')->unique();
            $table->string('nama_dimensi');
            $table->text('deskripsi')->nullable();
            $table->enum('kategori', ['religius', 'nasionalis', 'mandiri', 'gotong_royong', 'integritas', 'kreatif', 'berdaya_saing']);
            $table->float('bobot')->default(1.0);
            $table->json('rubrik_penilaian')->comment('Rubrik scoring: {1: "belum", 2: "sedang", 3: "baik", 4: "sangat baik"}');
            $table->float('threshold_siap')->default(3.0)->comment('Threshold untuk dianggap siap');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('semester_id');
            $table->index('kategori');
            $table->index('aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tka_dimensions_enhanced');
    }
};
