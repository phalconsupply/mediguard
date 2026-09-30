<?php

return [
    /*
     * Kho tệp nguồn và tệp kết quả.
     *
     * Tệp chứa dữ liệu bệnh nhân chỉ nằm trong đĩa riêng tư, không nằm dưới web root.
     * Đường dẫn, hash và quyền truy cập được lưu ở DB; tệp gốc là bất biến.
     */
    'storage' => [
        'disk' => env('MEDIGUARD_DISK', 'local'),
        'root' => env('MEDIGUARD_STORAGE_ROOT', 'mediguard'),
        'max_upload_mb' => (int) env('MEDIGUARD_MAX_UPLOAD_MB', 512),
    ],

    'import' => [
        // Số dòng ghi mỗi lần khi nạp; tăng lên cần đo lại RAM của worker.
        'chunk_size' => (int) env('MEDIGUARD_IMPORT_CHUNK', 500),
        'allowed_response_formats' => ['xlsx', 'csv'],
        'allowed_precheck_formats' => ['xml', 'xlsx', 'csv'],
    ],

    'export' => [
        // Tên cột cuối trên tệp trả lại BHYT. Cần bệnh viện xác nhận trước khi chốt.
        'column_header' => env('MEDIGUARD_EXPORT_COLUMN', 'ĐÃ GIẢI TRÌNH'),
        // Dòng tổng hợp và dòng đã được BHYT đánh dấu "Chấp nhận" để trống theo quy tắc xuất.
        'fill_accepted_rows' => (bool) env('MEDIGUARD_FILL_ACCEPTED_ROWS', false),
        'fill_summary_rows' => (bool) env('MEDIGUARD_FILL_SUMMARY_ROWS', false),
    ],

    'workflow' => [
        'default_due_days' => (int) env('MEDIGUARD_DUE_DAYS', 7),
        // Số dòng tối đa hiển thị một trang danh sách công việc.
        'page_size' => (int) env('MEDIGUARD_PAGE_SIZE', 50),
    ],

    /*
     * Cột hiển thị mặc định khi chưa có cấu hình nào của quản lý.
     * Chỉ tác động giao diện; tệp xuất trả vẫn giữ đủ mọi cột nguồn.
     */
    'default_columns' => [
        'ID_CP', 'MA_LK', 'KET_LUAN', 'GHI_CHU', 'MA_CP', 'TEN_CP',
        'TIEN_DE_NGHI_KIEM_TRA', 'MA_KHOA', 'TEN_KHOA',
        'MA_BAC_SI', 'TEN_NHAN_VIEN_CHI_DINH', 'MA_BSI_THUC_HIEN', 'TEN_NVYT_THUC_HIEN',
    ],

    /*
     * Gợi ý cột thêm theo nhóm lỗi. Quản lý vẫn tick bỏ được từng cột.
     */
    'columns_by_group' => [
        'thoi_gian' => ['NGAY_YL', 'NGAY_TH_YL', 'NGAY_KQ', 'NGAY_VAO', 'NGAY_RA'],
        'chi_dinh' => ['MA_BENH', 'MA_BENHKHAC', 'TEN_BENH', 'MA_BENH_DTL'],
        'thuoc' => ['SO_LUONG', 'LIEU_DUNG', 'CACH_DUNG', 'HAM_LUONG', 'DUONG_DUNG', 'SO_DANG_KY'],
        'dvkt' => ['MA_VAT_TU', 'MA_VATTU', 'MA_MAY', 'VI_TRI_TH_DVKT', 'SO_LUONG'],
    ],
];
