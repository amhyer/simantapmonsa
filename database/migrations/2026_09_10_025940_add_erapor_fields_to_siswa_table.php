<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('nisn', 20)->nullable()->after('nis');
            $table->string('nik', 20)->nullable()->after('nisn');
            $table->string('no_kk', 20)->nullable()->after('nik');
            $table->string('no_akta_lahir', 50)->nullable()->after('no_kk');
            $table->string('agama', 50)->nullable()->after('jenis_kelamin');
            $table->string('tempat_lahir', 100)->nullable()->after('agama');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->string('alamat', 500)->nullable()->after('nama_orang_tua');
            $table->string('rt', 10)->nullable()->after('alamat');
            $table->string('rw', 10)->nullable()->after('rt');
            $table->string('dusun', 100)->nullable()->after('rw');
            $table->string('desa', 100)->nullable()->after('dusun');
            $table->string('kecamatan', 100)->nullable()->after('desa');
            $table->string('kabupaten', 100)->nullable()->after('kecamatan');
            $table->string('provinsi', 100)->nullable()->after('kabupaten');
            $table->string('kode_pos', 10)->nullable()->after('provinsi');
            $table->string('telepon', 20)->nullable()->after('kode_pos');
            $table->string('anak_ke')->nullable()->after('telepon');
            $table->integer('jumlah_saudara')->nullable()->after('anak_ke');
            $table->integer('tinggi_badan')->nullable()->after('jumlah_saudara');
            $table->integer('berat_badan')->nullable()->after('tinggi_badan');
            $table->string('golongan_darah', 5)->nullable()->after('berat_badan');
            $table->string('foto', 255)->nullable()->after('golongan_darah');
            $table->boolean('penerima_kip')->default(false)->after('foto');
            $table->string('no_kip', 50)->nullable()->after('penerima_kip');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn([
                'nisn', 'nik', 'no_kk', 'no_akta_lahir', 'agama',
                'tempat_lahir', 'tanggal_lahir', 'alamat', 'rt', 'rw',
                'dusun', 'desa', 'kecamatan', 'kabupaten', 'provinsi',
                'kode_pos', 'telepon', 'anak_ke', 'jumlah_saudara',
                'tinggi_badan', 'berat_badan', 'golongan_darah', 'foto',
                'penerima_kip', 'no_kip',
            ]);
        });
    }
};
