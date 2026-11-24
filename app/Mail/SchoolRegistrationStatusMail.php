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
