<?php

namespace Database\Seeders;

use App\Models\MappingProfile;
use App\Models\PrecheckDetail;
use Illuminate\Database\Seeder;

/**
 * Cấu hình ánh xạ cho XML gửi giám định, dựng theo cấu trúc quan sát được ở
 * mẫu `Data_gui-Giam_dinh.xml`.
 *
 * Mỗi trường chuẩn nhận nhiều tên nguồn ứng viên vì các kỳ và các hệ thống HIS
 * đặt tên khác nhau. Danh sách này cần được đối chiếu thêm với mẫu XML của
 * các loại KCB/kỳ khác trước khi chốt.
 */
class MappingProfileSeeder extends Seeder
{
    public function run(): void
    {
        MappingProfile::updateOrCreate(
            ['code' => 'GIAMDINHHS_XML', 'version' => 1],
            [
                'name' => 'XML gửi giám định (GIAMDINHHS)',
                'format' => 'xml',
                'is_active' => true,
                'reader_options' => [
                    // Mẫu chứa XML con lồng trực tiếp trong NOIDUNGFILE, không phải base64.
                    'noidungfile' => 'nested_xml_or_base64',
                    'datetime_formats' => ['YmdHi', 'YmdHis', 'Ymd'],
                ],
                'definition' => [
                    'encounter' => [
                        'source' => 'XML1',
                        'fields' => [
                            'ma_lk' => ['MA_LK'],
                            'ma_bn' => ['MA_BN'],
                            'ma_the' => ['MA_THE_BHYT', 'MA_THE'],
                            'ma_khoa' => ['MA_KHOA'],
                            'ma_benh_chinh' => ['MA_BENH_CHINH', 'MA_BENH'],
                            'ma_benh_kt' => ['MA_BENH_KT', 'MA_BENHKHAC'],
                            'ma_loai_kcb' => ['MA_LOAI_KCB', 'LOAI_KCB'],
                            'ngay_vao' => ['NGAY_VAO'],
                            'ngay_ra' => ['NGAY_RA'],
                            't_tongchi' => ['T_TONGCHI', 'T_TONGCHI_BH'],
                            't_bhtt' => ['T_BHTT'],
                        ],
                    ],
                    'details' => [
                        [
                            'source' => 'XML2',
                            'kind' => PrecheckDetail::KIND_THUOC,
                            'fields' => [
                                'ma_lk' => ['MA_LK'],
                                'stt' => ['STT'],
                                'ma_thuoc' => ['MA_THUOC'],
                                'ma_dich_vu' => ['MA_DICH_VU'],
                                'ten' => ['TEN_THUOC'],
                                'ma_bac_si' => ['MA_BAC_SI'],
                                'ma_khoa' => ['MA_KHOA'],
                                'so_luong' => ['SO_LUONG'],
                                'thanh_tien' => ['THANH_TIEN_BH', 'THANH_TIEN'],
                                'ngay_yl' => ['NGAY_YL'],
                                'ngay_th_yl' => ['NGAY_TH_YL'],
                            ],
                        ],
                        [
                            'source' => 'XML3',
                            'kind' => PrecheckDetail::KIND_DVKT,
                            'fields' => [
                                'ma_lk' => ['MA_LK'],
                                'stt' => ['STT'],
                                // 4/52 dòng trong mẫu trống MA_DICH_VU nhưng có MA_VAT_TU:
                                // giữ cả hai trường, không coi trống là lỗi.
                                'ma_dich_vu' => ['MA_DICH_VU'],
                                'ma_vat_tu' => ['MA_VAT_TU'],
                                'ten' => ['TEN_DICH_VU', 'TEN_VAT_TU'],
                                'ma_bac_si' => ['MA_BAC_SI'],
                                'nguoi_thuc_hien' => ['NGUOI_THUC_HIEN'],
                                'ma_may' => ['MA_MAY'],
                                'ma_khoa' => ['MA_KHOA'],
                                'so_luong' => ['SO_LUONG'],
                                'thanh_tien' => ['THANH_TIEN_BH', 'THANH_TIEN'],
                                'ngay_yl' => ['NGAY_YL'],
                                'ngay_th_yl' => ['NGAY_TH_YL'],
                                'ngay_kq' => ['NGAY_KQ'],
                            ],
                        ],
                        [
                            'source' => 'XML4',
                            'kind' => PrecheckDetail::KIND_CLS,
                            'fields' => [
                                'ma_lk' => ['MA_LK'],
                                'stt' => ['STT'],
                                'ma_dich_vu' => ['MA_DICH_VU'],
                                'ma_chi_so' => ['MA_CHI_SO'],
                                // KET_LUAN ở đây là kết luận chuyên môn cận lâm sàng,
                                // khác hẳn KET_LUAN của tệp phản hồi giám định.
                                'gia_tri' => ['GIA_TRI', 'KET_LUAN'],
                                'ma_bac_si' => ['MA_BS_DOC_KQ'],
                                'ngay_kq' => ['NGAY_KQ'],
                            ],
                        ],
                        [
                            'source' => 'XML5',
                            'kind' => PrecheckDetail::KIND_DIEN_BIEN,
                            'fields' => [
                                'ma_lk' => ['MA_LK'],
                                'stt' => ['STT'],
                                'nguoi_thuc_hien' => ['NGUOI_THUC_HIEN'],
                                'ngay_yl' => ['THOI_DIEM_DBIEN', 'NGAY_YL'],
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
