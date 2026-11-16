<?php

namespace Tests\Unit;

use App\Services\MesinPresensi\ZkTcpProtocol;
use PHPUnit\Framework\TestCase;

class MesinPresensiTcpProtocolTest extends TestCase
{
    public function test_paket_connect_memakai_header_tcp_zkteco(): void
    {
        $paket = ZkTcpProtocol::paket(1000, 0, 0);

        $this->assertSame('5050827d08000000e80317fc00000000', bin2hex($paket));
    }

    public function test_paket_menyertakan_session_dan_reply_id(): void
    {
        $paket = ZkTcpProtocol::paket(1003, 0x1234, 0x0007);
        $payload = substr($paket, 8);

        $this->assertSame("\x50\x50\x82\x7d", substr($paket, 0, 4));
        $this->assertSame(strlen($payload), unpack('V', substr($paket, 4, 4))[1]);
        $this->assertSame(1003, unpack('v', substr($payload, 0, 2))[1]);
        $this->assertSame(0x1234, unpack('v', substr($payload, 4, 2))[1]);
        $this->assertSame(0x0007, unpack('v', substr($payload, 6, 2))[1]);
    }

    public function test_checksum_sesuai_vektor_zk_protocol(): void
    {
        // Vektor dari dokumentasi protokol ZKTeco (payload tanpa checksum).
        $this->assertSame(
            0x175A,
            ZkTcpProtocol::checksum(hex2bin('0b00f38d03005a4b4661636556657273696f6e00')),
        );
        $this->assertSame(
            0x6A29,
            ZkTcpProtocol::checksum(hex2bin('d007f38d0a0009')),
        );
    }

    public function test_communication_key_sesuai_algoritma_make_commkey(): void
    {
        // Vektor dihitung dari make_commkey() pyzk (commpro.c MakeKey).
        $this->assertSame('617d3279', bin2hex(ZkTcpProtocol::commKey(0, 0)));
        $this->assertSame('61fd3279', bin2hex(ZkTcpProtocol::commKey(1, 0)));
        $this->assertSame('61fd32ff', bin2hex(ZkTcpProtocol::commKey(1, 34438)));
        $this->assertSame('297f327f', bin2hex(ZkTcpProtocol::commKey(123456, 34438)));
    }

    public function test_record_40_byte_dibaca_sesuai_offset_zkteco(): void
    {
        // Contoh record dari dokumentasi: user "999111333", waktu 0x2368B36B,
        // verify type 1 (fingerprint), state 0 (check in).
        $record = pack('v', 13)
            .'999111333'
            .str_repeat("\x00", 15)
            ."\x01"
            .pack('V', 0x2368B36B)
            ."\x00"
            ."\x00\x00\x00\x00\xFF\x00\x00\x00";

        $this->assertSame(40, strlen($record));

        $hasil = ZkTcpProtocol::record($record, 40);

        $this->assertNotNull($hasil);
        $this->assertSame('999111333', $hasil['userId']);
        $this->assertSame(1, $hasil['verifyType']);
        $this->assertSame(0, $hasil['state']);
        $this->assertSame('2018-06-25 17:50:35', $hasil['waktu']->toDateTimeString());
    }

    public function test_record_16_dan_8_byte_memakai_offset_yang_benar(): void
    {
        $enamBelas = pack('V', 7).pack('V', 0)."\x00\x01".str_repeat("\x00", 6);
        $hasil = ZkTcpProtocol::record($enamBelas, 16);

        $this->assertNotNull($hasil);
        $this->assertSame('7', $hasil['userId']);
        $this->assertSame('2000-01-01 00:00:00', $hasil['waktu']->toDateTimeString());
        $this->assertSame(1, $hasil['verifyType']);

        $delapan = pack('v', 7)."\x00".pack('V', 0)."\x01";
        $hasil = ZkTcpProtocol::record($delapan, 8);

        $this->assertNotNull($hasil);
        $this->assertSame('7', $hasil['userId']);
        $this->assertSame('2000-01-01 00:00:00', $hasil['waktu']->toDateTimeString());
        $this->assertSame(1, $hasil['verifyType']);
    }

    public function test_dataset_dengan_prefix_ukuran_tetap_mengenali_record(): void
    {
        $record = pack('v', 13)
            .'999111333'
            .str_repeat("\x00", 15)
            ."\x01"
            .pack('V', 0x2368B36B)
            ."\x00"
            ."\x00\x00\x00\x00\xFF\x00\x00\x00";

        // Dataset protokol: 4 byte ukuran total diikuti record 40 byte.
        $dataset = pack('V', strlen($record) * 2).$record.$record;

        $this->assertSame(0, ZkTcpProtocol::ukuranRecord($dataset));
        $this->assertSame(40, ZkTcpProtocol::ukuranRecord(substr($dataset, 4)));
    }
}
