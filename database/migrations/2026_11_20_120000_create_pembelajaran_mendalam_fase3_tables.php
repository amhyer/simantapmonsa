<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_mengajar', function (Blueprint $table) {
            if (! Schema::hasColumn('jurnal_mengajar', 'tp_id')) {
                $table->unsignedBigInteger('tp_id')->nullable()->after('perangkat_id')->index();
            }
            if (! Schema::hasColumn('jurnal_mengajar', 'praktik_pedagogis')) {
                $table->string('praktik_pedagogis', 120)->nullable()->after('topik');
            }
            if (! Schema::hasColumn('jurnal_mengajar', 'prinsip_pm')) {
                $table->json('prinsip_pm')->nullable()->after('praktik_pedagogis');
            }
            if (! Schema::hasColumn('jurnal_mengajar', 'pengalaman_belajar')) {
                $table->json('pengalaman_belajar')->nullable()->after('prinsip_pm');
            }
            if (! Schema::hasColumn('jurnal_mengajar', 'asesmen')) {
                $table->text('asesmen')->nullable()->after('kegiatan');
            }
        });

        Schema::create('pm_kokurikuler', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->string('tahun_ajaran', 20)->index();
            $table->unsignedTinyInteger('tingkat')->default(1)->index();
            $table->string('tema_judul', 255);
            $table->string('bentuk_kegiatan', 80)->default('kolaboratif_lintas_disiplin');
            $table->json('dimensi_profil')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->text('tujuan')->nullable();
            $table->text('rangkaian_kegiatan')->nullable();
            $table->string('mitra', 255)->nullable();
            $table->text('refleksi_hasil')->nullable();
            $table->string('sumber', 40)->default('manual');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_kokurikuler');

        Schema::table('jurnal_mengajar', function (Blueprint $table) {
            $cols = array_filter(
                ['tp_id', 'praktik_pedagogis', 'prinsip_pm', 'pengalaman_belajar', 'asesmen'],
                fn (string $c) => Schema::hasColumn('jurnal_mengajar', $c)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
