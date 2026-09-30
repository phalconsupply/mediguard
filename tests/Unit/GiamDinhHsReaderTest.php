<?php

namespace Tests\Unit;

use App\Services\Import\Xml\GiamDinhHsReader;
use PHPUnit\Framework\TestCase;

class GiamDinhHsReaderTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../Fixtures/giamdinhhs_nested.xml';

    public function test_it_reads_the_file_level_header(): void
    {
        $header = (new GiamDinhHsReader(self::FIXTURE))->header();

        $this->assertSame('99999', $header['facility_code']);
        $this->assertSame('20260930', $header['created_on']);
        $this->assertSame(2, $header['declared_count']);
    }

    public function test_it_reads_xml_nested_directly_in_noidungfile(): void
    {
        $hoSo = iterator_to_array((new GiamDinhHsReader(self::FIXTURE))->hoSo());

        $this->assertCount(2, $hoSo);

        $first = $hoSo[1];
        $this->assertSame(1, $first['hoso_index']);
        $this->assertArrayHasKey('XML1', $first['files']);
        $this->assertSame('TEST0001', $first['files']['XML1'][0]['MA_LK']);
        $this->assertSame('202607030800', $first['files']['XML1'][0]['NGAY_VAO']);
    }

    public function test_it_reads_every_detail_record_with_its_position(): void
    {
        $hoSo = iterator_to_array((new GiamDinhHsReader(self::FIXTURE))->hoSo());

        $thuoc = $hoSo[1]['files']['XML2'];
        $this->assertCount(2, $thuoc);
        $this->assertSame(['1', '2'], array_column($thuoc, 'STT'));
        $this->assertSame(['1', '2'], array_column($thuoc, '_position'));

        $dvkt = $hoSo[1]['files']['XML3'];
        $this->assertCount(2, $dvkt);

        // Dòng thứ hai trống MA_DICH_VU nhưng có MA_VAT_TU: đây là loại dòng khác,
        // không phải dữ liệu lỗi.
        $this->assertSame('', $dvkt[1]['MA_DICH_VU']);
        $this->assertSame('VT001', $dvkt[1]['MA_VAT_TU']);
    }

    public function test_a_branch_with_no_records_is_not_reported_as_an_error(): void
    {
        $hoSo = iterator_to_array((new GiamDinhHsReader(self::FIXTURE))->hoSo());

        $this->assertSame([], $hoSo[1]['files']['XML10']);
        $this->assertSame([], $hoSo[2]['files']['XML15']);
        $this->assertSame([], $hoSo[1]['issues'], 'Nhánh rỗng không được tính là lỗi cấu trúc.');
        $this->assertSame([], $hoSo[2]['issues']);
    }

    public function test_it_reads_a_single_record_list(): void
    {
        $hoSo = iterator_to_array((new GiamDinhHsReader(self::FIXTURE))->hoSo());

        $this->assertCount(1, $hoSo[2]['files']['XML3']);
        $this->assertSame('DV002', $hoSo[2]['files']['XML3'][0]['MA_DICH_VU']);
    }

    public function test_it_also_accepts_the_base64_variant(): void
    {
        $inner = '<XML2><DSACH_CHI_TIET_THUOC><CHI_TIET_THUOC><MA_LK>B64</MA_LK><STT>1</STT>'
            .'<MA_THUOC>T9</MA_THUOC></CHI_TIET_THUOC></DSACH_CHI_TIET_THUOC></XML2>';

        $path = $this->writeTemp($this->wrap('XML2', base64_encode($inner)));

        $hoSo = iterator_to_array((new GiamDinhHsReader($path))->hoSo());
        @unlink($path);

        $this->assertSame('B64', $hoSo[1]['files']['XML2'][0]['MA_LK']);
    }

    public function test_content_that_is_neither_nested_xml_nor_base64_is_reported(): void
    {
        $path = $this->writeTemp($this->wrap('XML2', 'nội dung không đọc được'));

        $hoSo = iterator_to_array((new GiamDinhHsReader($path))->hoSo());
        @unlink($path);

        $this->assertSame([], $hoSo[1]['files']['XML2']);
        $this->assertNotEmpty($hoSo[1]['issues']);
        $this->assertStringContainsString('XML2', $hoSo[1]['issues'][0]);
    }

    public function test_external_entities_are_not_expanded(): void
    {
        $secret = tempnam(sys_get_temp_dir(), 'mg_secret_');
        file_put_contents($secret, 'NOI-DUNG-BI-MAT');

        $xml = '<?xml version="1.0"?>'
            .'<!DOCTYPE GIAMDINHHS [<!ENTITY xxe SYSTEM "file://'.str_replace('\\', '/', $secret).'">]>'
            .'<GIAMDINHHS><THONGTINHOSO><DANHSACHHOSO><HOSO><FILEHOSO>'
            .'<LOAIHOSO>XML1</LOAIHOSO><NOIDUNGFILE><TONG_HOP><MA_LK>&xxe;</MA_LK><STT>1</STT></TONG_HOP></NOIDUNGFILE>'
            .'</FILEHOSO></HOSO></DANHSACHHOSO></THONGTINHOSO></GIAMDINHHS>';

        $path = $this->writeTemp($xml);
        $hoSo = iterator_to_array((new GiamDinhHsReader($path))->hoSo());

        $content = json_encode($hoSo, JSON_UNESCAPED_UNICODE);
        @unlink($path);
        @unlink($secret);

        $this->assertStringNotContainsString('NOI-DUNG-BI-MAT', (string) $content);
    }

    private function wrap(string $type, string $content): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<GIAMDINHHS><THONGTINHOSO><DANHSACHHOSO><HOSO><FILEHOSO>'
            ."<LOAIHOSO>{$type}</LOAIHOSO><NOIDUNGFILE>{$content}</NOIDUNGFILE>"
            .'</FILEHOSO></HOSO></DANHSACHHOSO></THONGTINHOSO></GIAMDINHHS>';
    }

    private function writeTemp(string $xml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mg_xml_');
        file_put_contents($path, $xml);

        return $path;
    }
}
