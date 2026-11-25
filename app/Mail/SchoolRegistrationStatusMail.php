<?php

namespace App\Mail;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SchoolRegistrationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $status,
        public readonly ?string $reason = null,
    ) {}

    public function build(): self
    {
        $namaSekolah = e($this->tenant->nama_sekolah);
        $npsn = e((string) $this->tenant->npsn);

        if ($this->status === 'approved') {
            $loginUrl = e(route('login'));
            $subject = "Pendaftaran Sekolah Disetujui - {$this->tenant->nama_sekolah}";
            $body = <<<HTML
<h2 style="color:#067647;margin-top:0">Pendaftaran Sekolah Disetujui</h2>
<p>Selamat! Pendaftaran <strong>{$namaSekolah}</strong> (NPSN: <strong>{$npsn}</strong>) di <strong>SIMANTAP_MONSA</strong> telah disetujui dan diaktifkan oleh Super Administrator.</p>
<p style="margin:24px 0">
    <a href="{$loginUrl}" style="background:#067647;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;display:inline-block">
        Masuk ke Dashboard Sekolah
    </a>
</p>
HTML;
        } elseif ($this->status === 'rejected') {
            $alasan = e($this->reason ?: 'Data pendaftaran belum memenuhi persyaratan verifikasi.');
            $subject = "Status Pendaftaran Sekolah - {$this->tenant->nama_sekolah}";
            $body = <<<HTML
<h2 style="color:#B42318;margin-top:0">Pendaftaran Sekolah Belum Disetujui</h2>
<p>Mohon maaf, pengajuan pendaftaran <strong>{$namaSekolah}</strong> (NPSN: <strong>{$npsn}</strong>) belum dapat disetujui.</p>
<p><strong>Catatan Super Administrator:</strong><br>{$alasan}</p>
HTML;
        } elseif ($this->status === 'subscription_renewed') {
            $loginUrl = e(route('login'));
            $paketLabel = e(strtoupper((string) ($this->tenant->paket ?? 'standar')));
            $jatuhTempo = $this->tenant->tgl_kedaluwarsa
                ? e($this->tenant->tgl_kedaluwarsa->format('d-m-Y'))
                : 'Tanpa Batas Waktu (Unlimited)';
            $catatan = $this->reason ? '<p><strong>Keterangan:</strong> '.e($this->reason).'</p>' : '';
            $subject = "Masa Aktif Layanan Diperbarui - {$this->tenant->nama_sekolah}";
            $body = <<<HTML
<h2 style="color:#067647;margin-top:0">Masa Aktif Layanan Sekolah Diperbarui</h2>
<p>Layanan platform <strong>SIMANTAP_MONSA</strong> untuk <strong>{$namaSekolah}</strong> (NPSN: <strong>{$npsn}</strong>) telah diperbarui:</p>
<ul>
    <li><strong>Paket Layanan:</strong> {$paketLabel}</li>
    <li><strong>Masa Aktif Sampai:</strong> {$jatuhTempo}</li>
    <li><strong>Kuota Siswa:</strong> {$this->tenant->kuota_siswa} akun</li>
    <li><strong>Kuota Penyimpanan:</strong> {$this->tenant->kuota_storage_mb} MB</li>
</ul>
{$catatan}
<p style="margin:24px 0">
    <a href="{$loginUrl}" style="background:#067647;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;display:inline-block">
        Buka Dashboard Sekolah
    </a>
</p>
HTML;
        } elseif ($this->status === 'subscription_expiring') {
            $sisaHari = max(0, (int) ($this->reason ?? $this->tenant->sisaHariLangganan() ?? 0));
            $jatuhTempo = $this->tenant->tgl_kedaluwarsa
                ? e($this->tenant->tgl_kedaluwarsa->format('d-m-Y'))
                : '-';
            $subject = "Pengingat Masa Aktif Layanan ({$sisaHari} Hari Lagi) - {$this->tenant->nama_sekolah}";
            $body = <<<HTML
<h2 style="color:#B54708;margin-top:0">Pengingat Masa Aktif Layanan Sekolah</h2>
<p>Masa aktif paket layanan <strong>SIMANTAP_MONSA</strong> untuk <strong>{$namaSekolah}</strong> (NPSN: <strong>{$npsn}</strong>) akan berakhir dalam <strong>{$sisaHari} hari</strong> (jatuh tempo: <strong>{$jatuhTempo}</strong>).</p>
<p>Silakan hubungi Pengelola Platform (Super Administrator) untuk konfirmasi perpanjangan agar akses guru dan peserta didik tetap berjalan tanpa gangguan.</p>
HTML;
        } else {
            $detailUrl = e(route('superadmin.tenants.show', $this->tenant->uuid));
            $subject = "[Super Admin] Pendaftaran Sekolah Baru: {$this->tenant->nama_sekolah} ({$this->tenant->npsn})";
            $body = <<<HTML
<h2 style="color:#1F3864;margin-top:0">Pengajuan Pendaftaran Sekolah Baru</h2>
<p>Sekolah baru telah mendaftar di platform <strong>SIMANTAP_MONSA</strong> dan menunggu peninjauan:</p>
<ul>
    <li><strong>Nama Sekolah:</strong> {$namaSekolah}</li>
    <li><strong>NPSN:</strong> {$npsn}</li>
    <li><strong>Subdomain:</strong> {$this->tenant->subdomain}</li>
</ul>
<p style="margin:24px 0">
    <a href="{$detailUrl}" style="background:#1F3864;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;display:inline-block">
        Tinjau Pendaftaran Sekolah
    </a>
</p>
HTML;
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>{$subject}</title></head>
<body style="font-family:Segoe UI,Arial,sans-serif;color:#1D2939;line-height:1.6;padding:20px">
    <div style="max-width:560px;margin:0 auto;border:1px solid #E4E7EC;border-radius:12px;padding:24px">
        {$body}
    </div>
</body>
</html>
HTML;

        return $this->subject($subject)->html($html);
    }
}
