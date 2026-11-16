<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemantauan Kesiapan TKA — tabel tambahan (aditif, tidak mengubah tabel sumber).
 *
 *  - tka_kesiapan_pengaturan       : override aturan hitung per sekolah (ambang, minimum bukti).
 *  - tka_kesiapan_pemetaan         : pemetaan mata pelajaran/label sumber → indikator pemantauan.
 *  - tka_kesiapan_tindak_lanjut    : rekomendasi yang dapat ditinjau/disunting guru + status.
 *  - tka_kesiapan_tindak_lanjut_revisi : riwayat revisi rekomendasi.
 *  - tka_kesiapan_audit            : audit perubahan konfigurasi oleh admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tka_kesiapan_pengaturan')) {
            Schema::create('tka_kesiapan_pengaturan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->json('aturan')->nullable();
                $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('tenant_id', 'tka_kesiapan_pengaturan_tenant_unique');
            });
        }

        if (! Schema::hasTable('tka_kesiapan_pemetaan')) {
            Schema::create('tka_kesiapan_pemetaan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->string('label_sumber', 120);        // nama mata pelajaran / label indikator di sumber (huruf kecil)
                $table->string('indikator_kode', 40);
                $table->boolean('aktif')->default(true);
                $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tenant_id', 'label_sumber'], 'tka_kesiapan_pemetaan_unique');
                $table->index(['tenant_id', 'indikator_kode'], 'tka_kesiapan_pemetaan_ind_idx');
            });
        }

        if (! Schema::hasTable('tka_kesiapan_tindak_lanjut')) {
            Schema::create('tka_kesiapan_tindak_lanjut', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->string('tahun_ajaran', 20);
                $table->string('semester', 10);
                $table->string('indikator_kode', 40);
                $table->text('rekomendasi');
                $table->json('bukti_ref')->nullable();       // jejak bukti yang mendasari
                $table->string('status', 20)->default('disarankan'); // disarankan|ditinjau|berjalan|selesai|ditolak
                $table->text('catatan_guru')->nullable();
                $table->boolean('disunting_guru')->default(false);
                $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['siswa_id', 'tahun_ajaran', 'semester', 'indikator_kode'], 'tka_kesiapan_tl_unique');
                $table->index(['tenant_id', 'status'], 'tka_kesiapan_tl_status_idx');
            });
        }

        if (! Schema::hasTable('tka_kesiapan_tindak_lanjut_revisi')) {
            Schema::create('tka_kesiapan_tindak_lanjut_revisi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('tindak_lanjut_id')->constrained('tka_kesiapan_tindak_lanjut')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status_sebelum', 20)->nullable();
                $table->string('status_sesudah', 20)->nullable();
                $table->text('rekomendasi_sebelum')->nullable();
                $table->text('rekomendasi_sesudah')->nullable();
                $table->text('catatan')->nullable();
                $table->timestamps();

                $table->index('tindak_lanjut_id', 'tka_kesiapan_tlr_tl_idx');
            });
        }

        if (! Schema::hasTable('tka_kesiapan_audit')) {
            Schema::create('tka_kesiapan_audit', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('aksi', 40);
                $table->json('sebelum')->nullable();
                $table->json('sesudah')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at'], 'tka_kesiapan_audit_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tka_kesiapan_audit');
        Schema::dropIfExists('tka_kesiapan_tindak_lanjut_revisi');
        Schema::dropIfExists('tka_kesiapan_tindak_lanjut');
        Schema::dropIfExists('tka_kesiapan_pemetaan');
        Schema::dropIfExists('tka_kesiapan_pengaturan');
    }
};
