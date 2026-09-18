<?php

namespace App\Http\Controllers\Ortu;

trait HasPredikatKaih
{
    private function getPredikatKaih($rata)
    {
        if ($rata >= 3.5) return ['huruf' => 'A', 'label' => 'Membudaya', 'kelas' => 'tag-ok'];
        if ($rata >= 2.5) return ['huruf' => 'B', 'label' => 'Berkembang', 'kelas' => 'tag-warn'];
        if ($rata >= 1.5) return ['huruf' => 'C', 'label' => 'Mulai Terlihat', 'kelas' => 'tag-mut'];
        if ($rata > 0) return ['huruf' => 'D', 'label' => 'Perlu Pembiasaan', 'kelas' => 'tag-bad'];
        return ['huruf' => '-', 'label' => 'Belum dilaporkan', 'kelas' => 'tag-mut'];
    }
}
