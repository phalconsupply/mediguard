# MediGuard — hướng dẫn cho người và trợ lý viết mã

Ứng dụng Laravel 13 / PHP 8.3 / MySQL 8.4 quản lý việc giải trình phản hồi giám định
BHYT và tự kiểm tra dữ liệu HIS trước khi gửi. Thiết kế nghiệp vụ: `docs/DE_XUAT_CONG_CU_GIAI_TRINH_BHYT.md`.

## Điều không được phá vỡ

1. **Không bao giờ commit dữ liệu người bệnh.** `Document/` và mọi `*.xlsx`/`*.csv`
   đã bị `.gitignore` chặn. Tệp mẫu để kiểm thử nằm ở `tests/Fixtures/` và là dữ liệu bịa.
2. **Tệp nguồn là bất biến.** Bước xuất trả sao chép gói gốc rồi chỉ thêm một cột;
   không dựng lại workbook từ dữ liệu trong CSDL.
3. **Không gộp dữ liệu theo tên cột.** Khóa của một dòng là `(source_sheet_id, row_number)`.
   `ID_CP` chỉ để tra cứu: cùng một `ID_CP` có thể nằm ở nhiều tệp và là nhiều việc riêng.
4. **Ô không đọc được trả `null`, không quy về `0` hay chuỗi rỗng.** Với quy tắc tự
   kiểm tra, thiếu dữ liệu phải ra `insufficient_data`, không bao giờ là `not_detected`.
5. **Phiên bản quy tắc đã dùng để chạy không bị ghi đè.** Mọi thay đổi tạo phiên bản mới.
6. **Phân quyền chặn ở truy vấn.** Mọi danh sách vụ việc đi qua `IssueCase::scopeVisibleTo()`.
7. **Không có tài khoản mặc định.** Tài khoản đầu tiên tạo bằng `mediguard:create-user`.

## Lệnh thường dùng

```bash
php artisan test                 # toàn bộ bài kiểm thử
php artisan test --filter=X      # một nhóm
vendor/bin/pint                  # định dạng mã trước khi commit
php artisan migrate --seed
php artisan queue:work --tries=1 --timeout=3600
php artisan mediguard:import-response <đường-dẫn> --period=202607
```

Trên Windows/Laragon, PATH có thể trỏ tới PHP khác. Gọi trực tiếp:
`/c/laragon/bin/php/php-8.3.*/php.exe artisan …`

## Bẫy dữ liệu đã gặp trong mẫu thật

- Cấu trúc thay đổi **115–137 cột** giữa các tệp; tháng sau có thể thêm cột mới.
- Tiêu đề **trùng nhau** (`NGAY_YL`, `NGAY_RA`, `MOI_MA_CP`) và tiêu đề có
  **khoảng trắng bao quanh** (` TIEN_DE_NGHI_KIEM_TRA `).
- Tiền ở cả hai dạng `502549` và `502.549,00`.
- Mốc thời gian ba độ chính xác: `Y-m-d H:i:s`, `Y-m-d H:i`, `YmdHi` (XML).
- `KET_LUAN` của tệp `5073` chứa cả dòng **“Chấp nhận”** — không giao như khoản bị từ chối.
- Ba tệp có **dòng tổng hợp** không có `ID_CP`; phải giữ nguyên khi xuất lại.
- `MA_KHOA=K01` với `TEN_KHOA` trống là **khám ngoại trú**, không phải dữ liệu lỗi.
- `MA_BSI_THUC_HIEN` thiếu ở khoảng một nửa số dòng `K01` của mã `9065`.
- `KET_LUAN` của XML4 là kết luận **cận lâm sàng**, khác `KET_LUAN` của tệp phản hồi
  (kết luận **giám định**). Hai trường này không được ánh xạ lẫn nhau.
- `NOIDUNGFILE` của mẫu XML chứa XML con **lồng trực tiếp**, không phải base64.

## Quy ước viết mã

- Bình luận viết bằng tiếng Việt, giải thích **vì sao**, không mô tả lại cú pháp.
- Dịch vụ nghiệp vụ nằm ở `app/Services/<Miền>/`; controller chỉ điều phối.
- Đọc và ghi tệp luôn theo luồng; không nạp cả tệp vào RAM.
- Job chia theo **tệp hoặc khối**, không tạo một job cho mỗi dòng.
- Thêm bài kiểm thử cho mọi thay đổi ở bộ đọc, bộ xuất, bộ quy tắc hoặc phân quyền.
