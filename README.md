# MediGuard — quản lý giải trình dữ liệu BHYT

Công cụ tiếp nhận phản hồi giám định BHYT, giao việc giải trình đến đúng người,
duyệt nội dung và **xuất lại từng tệp gốc kèm một cột mới**; kèm luồng **tự kiểm tra
dữ liệu HIS trước khi gửi giám định**.

Thiết kế nghiệp vụ nằm ở [`docs/DE_XUAT_CONG_CU_GIAI_TRINH_BHYT.md`](docs/DE_XUAT_CONG_CU_GIAI_TRINH_BHYT.md).

> **Dữ liệu người bệnh không nằm trong kho mã nguồn này.** Thư mục khảo sát
> (`Document/`) và mọi tệp XLSX/CSV/XML thật đã bị loại khỏi Git. Các tệp mẫu
> trong `tests/Fixtures/` là dữ liệu bịa, dùng riêng cho kiểm thử.

## Nguyên tắc đã được cài vào mã

| Nguyên tắc | Nơi thực thi |
|---|---|
| Giữ nguyên **mọi ô, mọi cột, đúng số dòng** của tệp gốc | [`ResponseFileImporter`](app/Services/Import/ResponseFileImporter.php) lưu mảng ô theo thứ tự cột; khóa bất biến là `(trang tính, số dòng)` |
| Tiêu đề **trùng tên** vẫn phân biệt được | `internal_code = tên chuẩn hóa + lần xuất hiện` ([`SheetInspector`](app/Services/Import/SheetInspector.php)) |
| Tiền có hai định dạng (`502549` và `502.549,00`) | [`Normalizer::money()`](app/Support/Normalizer.php) — ô không đọc được trả `null`, không quy về `0` |
| Mốc thời gian có **ba độ chính xác** | `Normalizer::dateTimeWithPrecision()` ghi lại độ chính xác; không tự suy giây bị thiếu là `00` |
| Một `ID_CP` ở nhiều tệp là **nhiều việc riêng** | `issue_cases` khóa theo `source_row_id`, không theo `ID_CP` |
| Dòng tổng hợp và dòng “Chấp nhận” **không chặn tiến độ** | `requires_explanation` chỉ bật cho dòng chi phí bị từ chối/cảnh báo |
| Không tự gán người chỉ định làm người thực hiện | [`AssignmentResolver`](app/Services/Assignment/AssignmentResolver.php) — thiếu mã người thực hiện thì vào hàng chờ ngoại trú |
| Xuất trả chỉ **thêm một cột**, giữ nguyên workbook | [`XlsxColumnAppender`](app/Services/Export/XlsxColumnAppender.php) sao chép gói OOXML, chỉ viết lại một `sheetN.xml` |
| Tự kiểm tra có **ba trạng thái**, thiếu dữ liệu ≠ đạt | [`RuleEvaluator`](app/Services/Rules/RuleEvaluator.php) |
| Quy tắc chạy đúng **phiên bản đã chụp** | `precheck_runs.ruleset_snapshot`; phiên bản đã dùng không bị ghi đè |
| Phân quyền chặn ở **truy vấn**, không chỉ ở nút bấm | `IssueCase::scopeVisibleTo()` + [`IssueCasePolicy`](app/Policies/IssueCasePolicy.php) |

## Yêu cầu môi trường

- PHP **8.3** với `pdo_mysql`, `xmlreader`, `zip`, `mbstring`, `intl`, `bcmath`
- MySQL **8.4**
- Composer 2, Node 20+ (chỉ để dựng giao diện)

Dev trên Windows/Laragon: PATH có thể đang trỏ tới PHP khác (ví dụ XAMPP 8.2).
Gọi đúng PHP của Laragon khi chạy Composer và Artisan:

```bash
/c/laragon/bin/php/php-8.3.*/php.exe artisan migrate
```

## Cài đặt

```bash
composer install
cp .env.example .env
php artisan key:generate

# Tạo CSDL MySQL rỗng rồi điền DB_* vào .env
php artisan migrate --seed

npm install && npm run build

# Không có tài khoản mặc định: tạo tài khoản đầu tiên bằng lệnh sau,
# mật khẩu nhập tại dòng lệnh và không hiển thị.
php artisan mediguard:create-user --role=admin

php artisan serve
php artisan queue:work --tries=1 --timeout=3600   # chạy ở cửa sổ riêng
```

## Sử dụng

### Luồng phản hồi BHYT

1. **Đợt dữ liệu → Tạo đợt mới**, chọn *Phản hồi BHYT*, điền kỳ (`202607`) và mã cơ sở.
2. Nạp các tệp `TACH_####_….xlsx`. Mã lỗi được nhận từ tên tệp; mã chưa có trong thư
   viện được ghi nhận là **lỗi mới chờ phân loại** và vẫn phân công được ngay.
3. Nếu nhận diện sai trang tính hoặc dòng tiêu đề: **Xem trước → chọn lại → nạp lại**.
   Việc nạp lại chỉ ảnh hưởng tệp đó.
4. **Công việc**: lọc theo mã lỗi, khoa, người được giao, trạng thái, `ID_CP`/`MA_LK`.
   Mở một dòng để xem nguyên văn ghi chú BHYT và **toàn bộ** dữ liệu nguồn.
5. Người phụ trách viết giải trình → gửi duyệt → trưởng khoa hoặc quản lý BHYT duyệt.
   Người viết không tự duyệt bản của mình.
6. **Xuất trả**: mỗi tệp nguồn sinh đúng một tệp kết quả có thêm cột cuối
   `ĐÃ GIẢI TRÌNH`. Hệ thống chặn việc xuất khi còn dòng chưa có bản giải trình
   được duyệt, và đọc lại chính tệp vừa sinh để đối chiếu số dòng, số cột,
   giá trị ô nguồn lấy mẫu và số ô đã ghi.

Nạp hàng loạt từ dòng lệnh (dùng cho bài đo tải):

```bash
php artisan mediguard:import-response ./duong-dan-thu-muc --period=202607 --facility=68001
```

### Luồng tự kiểm tra trước khi gửi

1. Tạo đợt loại *Tự kiểm tra*, nạp XML `GIAMDINHHS` kèm cấu hình ánh xạ.
   Bộ đọc hỗ trợ XML con **lồng trực tiếp** trong `NOIDUNGFILE` (biến thể của mẫu
   được cung cấp) và cả biến thể base64; không mặc định giải mã base64.
2. **Quy tắc**: soạn điều kiện dạng JSON, tạo phiên bản, đi qua vòng đời
   `Mới ghi nhận → Đang phân tích → Đang thử nghiệm → Được duyệt → Đang áp dụng`.
   Chưa có bộ ca kiểm thử thì chưa duyệt được.
3. **Chạy tự kiểm tra**: chọn các quy tắc đang áp dụng. Kết quả tách bạch
   *phát hiện nguy cơ* / *không phát hiện theo quy tắc đã chạy* / *không đủ dữ liệu
   để kiểm tra*, kèm độ phủ theo từng quy tắc.

## Kiến trúc

```
app/
  Services/Import/        đọc XLSX/CSV theo luồng, nhận diện trang tính và cột
  Services/Import/Xml/    bộ đọc GIAMDINHHS, chặn thực thể ngoài, xử lý theo luồng
  Services/Precheck/      đưa mọi định dạng về bộ trường chuẩn dùng chung
  Services/Rules/         định nghĩa, đánh giá và chạy quy tắc theo phiên bản
  Services/Assignment/    chọn người chịu trách nhiệm theo mã lỗi
  Services/Export/        nối cột vào XLSX/CSV và kiểm soát chất lượng
  Services/Presentation/  cấu hình cột hiển thị
  Jobs/                   job theo tệp, không theo dòng
```

Đọc và ghi tệp đều theo luồng: mỗi lúc chỉ giữ một dòng (XLSX/CSV) hoặc một
hồ sơ (XML) trong bộ nhớ.

## Kiểm thử

```bash
php artisan test        # 104 bài, gồm vòng đời nạp → giải trình → xuất trả
vendor/bin/pint         # định dạng mã
```

Bài kiểm thử bao trùm: các định dạng tiền và thời gian trong dữ liệu thật, tiêu đề
trùng, giữ nguyên byte khi nối cột vào CSV, giữ nguyên workbook khi nối cột vào XLSX,
văn bản bắt đầu bằng ký tự công thức, ba trạng thái của tự kiểm tra, và phân quyền
theo khoa.

## Triển khai

```bash
cp .env.example .env    # đặt DB_PASSWORD, DB_ROOT_PASSWORD, APP_KEY
docker compose up -d --build
docker compose exec app php artisan migrate --seed --force
docker compose exec app php artisan mediguard:create-user --role=admin
```

Cần bổ sung khi vận hành thật: TLS trước Nginx, sao lưu định kỳ cả CSDL lẫn kho
tệp, thử khôi phục trên bản sao, giám sát hàng đợi và dung lượng đĩa.

## Việc cần bệnh viện xác nhận trước khi dùng cho kỳ thật

Những điểm sau đã cài đặt theo giả định trong đề xuất và **phải được xác nhận**:

1. **Tên chính xác của cột cuối** trong tệp gửi trả (hiện là `ĐÃ GIẢI TRÌNH`,
   đổi qua `MEDIGUARD_EXPORT_COLUMN`), và dòng “Chấp nhận”/dòng tổng hợp có cần
   điền nội dung hay không (`MEDIGUARD_FILL_*`).
2. **Danh mục mã bác sĩ/NVYT gắn với tài khoản**, mã khoa và đơn vị thực hiện
   dịch vụ. Chưa có danh mục thì vụ việc rơi vào hàng chờ, không bị gán nhầm người.
3. **Một tệp XLSX gốc và một tệp kết quả đã từng được BHYT chấp nhận** để đối chiếu
   tính tương thích. Thư mục khảo sát hiện chỉ có CSV chuyển đổi.
4. **Danh mục lỗi và quy tắc phân công** trong `ErrorCatalogSeeder` là bản gợi ý từ
   bảng khảo sát, đang ở trạng thái *chờ phân loại*.
5. **Quy tắc tự kiểm tra** trong `CheckRuleSeeder` đang ở trạng thái *Đang phân tích*
   và **chưa chạy trên dữ liệu thật**. Tên tệp và câu ghi chú của BHYT mới là gợi ý,
   chưa đủ để khẳng định điều kiện đúng cho mọi hồ sơ.
6. **Bài đo tải** với bộ thử 1 triệu và 5 triệu dòng để chốt cấu hình máy chủ,
   kích thước khối và số worker.
