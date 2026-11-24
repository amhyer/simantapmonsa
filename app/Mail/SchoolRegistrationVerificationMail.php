<?php

namespace App\Mail;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SchoolRegistrationVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $namaOperator,
        public readonly string $verifyUrl,
        public readonly bool $requireApproval = false,
    ) {}

    public function build(): self
    {
        $namaSekolah = e($this->tenant->nama_sekolah);
        $npsn = e((string) $this->tenant->npsn);
        $operator = e($this->namaOperator);
        $url = e($this->verifyUrl);
        $infoApproval = $this->requireApproval
            ? '<p>Setelah email terverifikasi, pendaftaran sekolah Anda akan ditinjau dan diaktifkan oleh Super Administrator SIMANTAP.</p>'
            : '<p>Silakan klik tombol di bawah untuk memverifikasi alamat email operator sekolah Anda.</p>';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Verifikasi Email Sekolah</title></head>
<body style="font-family:Segoe UI,Arial,sans-serif;color:#1D2939;line-height:1.6;padding:20px">
    <div style="max-width:560px;margin:0 auto;border:1px solid #E4E7EC;border-radius:12px;padding:24px">
        <h2 style="color:#1F3864;margin-top:0">Verifikasi Email Pendaftaran Sekolah</h2>
        <p>Halo <strong>{$operator}</strong>,</p>
        <p>Terima kasih telah mendaftarkan <strong>{$namaSekolah}</strong> (NPSN: <strong>{$npsn}</strong>) di platform <strong>SIMANTAP_MONSA</strong>.</p>
        {$infoApproval}
        <p style="margin:24px 0">
            <a href="{$url}" style="background:#1F3864;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;display:inline-block">
                Verifikasi Email Sekarang
            </a>
        </p>
        <p style="font-size:12px;color:#667085">Tautan ini berlaku selama 24 jam. Jika tombol di atas tidak berfungsi, salin URL berikut ke peramban Anda:<br>{$url}</p>
    </div>
</body>
</html>
HTML;

        return $this->subject("Verifikasi Email Pendaftaran Sekolah - {$this->tenant->nama_sekolah}")
            ->html($html);
    }
}
