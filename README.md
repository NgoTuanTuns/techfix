# TechFix — Hệ thống Đặt lịch & Quản lý Sửa chữa Thiết bị

> **Đồ án môn học:** 503073 — Lập trình Web và Ứng dụng  
> **Repository:** <https://github.com/NgoTuanTuns/techfix.git>

**TechFix** là ứng dụng web đặt lịch và quản lý quy trình sửa chữa Laptop, Điện thoại và Tablet. Hệ thống hỗ trợ đầy đủ luồng nghiệp vụ từ lúc khách hàng chọn gói dịch vụ, đặt lịch, theo dõi tiến độ theo thời gian thực, duyệt báo giá hai chiều, nhận thông báo email cho đến quản trị kho linh kiện, kỹ thuật viên và thống kê doanh thu.

---

## Mục lục

- [1. Yêu cầu hệ thống](#1-yêu-cầu-hệ-thống)
- [2. Hướng dẫn cài đặt & chạy trên XAMPP](#2-hướng-dẫn-cài-đặt--chạy-trên-xampp)
- [3. Tài khoản kiểm thử mặc định](#3-tài-khoản-kiểm-thử-mặc-định)
- [4. Tính năng chính](#4-tính-năng-chính)
- [5. Cấu trúc thư mục](#5-cấu-trúc-thư-mục)
- [6. Công nghệ sử dụng](#6-công-nghệ-sử-dụng)

---

## 1. Yêu cầu hệ thống

- **XAMPP** (Apache + MySQL / MariaDB `10.4+` + PHP `8.0+`)
- **PHP Extensions:** `pdo_mysql`, `openssl` (mặc định có sẵn trong XAMPP, dùng cho gửi email SMTP TLS)
- **Trình duyệt:** Chrome, Edge, Firefox hoặc Safari phiên bản mới

---

## 2. Hướng dẫn cài đặt & chạy trên XAMPP

### Bước 1: Đặt mã nguồn vào thư mục web root của XAMPP

- **Cách A (Chạy ở thư mục con — khuyến nghị):**  
  Copy dự án vào `C:\xampp\htdocs\techfix\` và truy cập:  
  `http://localhost/techfix/`
- **Cách B (Chạy ở thư mục gốc):**  
  Copy toàn bộ nội dung vào `C:\xampp\htdocs\` và truy cập:  
  `http://localhost/`

> **Lưu ý:** Toàn bộ liên kết nội bộ sử dụng đường dẫn tương đối (`../api/`, `../assets/`), nên ứng dụng chạy ổn định ở mọi cổng hoặc cấp thư mục. Truy cập thư mục gốc sẽ tự động chuyển hướng vào `public/index.html` thông qua `index.php`.

### Bước 2: Khởi động dịch vụ

Mở **XAMPP Control Panel** và bật **Apache** cùng **MySQL**.

### Bước 3: Khởi tạo Cơ sở dữ liệu

1. Mở **phpMyAdmin** tại `http://localhost/phpmyadmin`.
2. Chọn tab **Import** và nạp file `techfix_schema.sql`  
   *(Tự động tạo database `techfix`, các bảng, khóa ngoại, dữ liệu gói dịch vụ và kho linh kiện mẫu)*.
3. Tiếp tục **Import** file `seed_admin.sql` vào database `techfix`  
   *(Tạo sẵn các tài khoản mẫu cho Admin, Kỹ thuật viên và Khách hàng)*.

### Bước 4: Cấu hình biến môi trường (`.env`)

1. Copy file `.env.example` thành `.env` tại thư mục gốc dự án:
   ```bash
   cp .env.example .env
   ```
2. Cập nhật thông số trong `.env` nếu cần:
   ```env
   # Database (mặc định khớp cấu hình XAMPP)
   TECHFIX_DB_HOST=localhost
   TECHFIX_DB_NAME=techfix
   TECHFIX_DB_USER=root
   TECHFIX_DB_PASS=

   # Email thông báo (Gmail SMTP — tùy chọn)
   TECHFIX_SMTP_HOST=smtp.gmail.com
   TECHFIX_SMTP_PORT=465
   TECHFIX_SMTP_USER=your_email@gmail.com
   TECHFIX_SMTP_PASS=xxxx_xxxx_xxxx_xxxx
   TECHFIX_MAIL_FROM_NAME="TechFix — Hệ thống sửa chữa thiết bị"
   ```
   - Để lấy `TECHFIX_SMTP_PASS`: bật **Xác minh 2 bước** trên tài khoản Google, sau đó tạo **App Password** 16 ký tự tại <https://myaccount.google.com/apppasswords>.
   - Nếu chưa cấu hình SMTP, các email gửi đi vẫn được lưu bản sao HTML để kiểm tra tại thư mục `uploads/emails/`.

---

## 3. Tài khoản kiểm thử mặc định

Tất cả tài khoản mẫu được tạo từ `seed_admin.sql` đều sử dụng chung mật khẩu: **`admin123`**

| Vai trò | Số điện thoại (Đăng nhập) | Mật khẩu | Trang truy cập |
| :--- | :--- | :--- | :--- |
| **Admin** (Quản trị viên) | `0123456789` | `admin123` | `public/admin.html` |
| **Technician** (Kỹ thuật viên) | `0911222333` | `admin123` | `public/admin.html` |
| **Customer** (Khách hàng) | `0988777666` | `admin123` | `public/dashboard.html` |

---

## 4. Tính năng chính

### Quản lý tài khoản & Bảo mật
- **Đăng ký & Đăng nhập:** Kiểm tra email hợp lệ, mã hóa mật khẩu bằng `bcrypt`, xác nhận mật khẩu 2 lần và tự động đăng nhập sau khi đăng ký (`api/register.php`, `api/login.php`).
- **Kích hoạt tài khoản qua Email:** Gửi liên kết xác thực email; hiển thị banner nhắc nhở trên Dashboard nếu chưa kích hoạt và hỗ trợ gửi lại email (`api/activate_account.php`, `api/resend_activation.php`).
- **Quên mật khẩu:** Tạo token khôi phục có thời hạn 30 phút qua email và trang đặt lại mật khẩu riêng (`api/forgot_password.php`, `public/reset-password.html`, `api/reset_password.php`).
- **Phân quyền (RBAC):** Bảo vệ API và giao diện theo 3 vai trò `customer`, `technician`, `admin` (`includes/auth.php`).
- **Hồ sơ cá nhân & Giao diện:** Tải ảnh đại diện (kiểm tra MIME & dung lượng tối đa 2MB), đổi mật khẩu an toàn (`api/update_profile.php`), chuyển đổi chế độ **Sáng / Tối** lưu trong `localStorage`.

### Đặt lịch sửa chữa & Quy trình xử lý đơn
- **Chọn gói nhanh từ trang chủ:** Các gói dịch vụ và mục **Ưu đãi tuần này** trên `public/index.html` liên kết trực tiếp sang `public/booking.html?service=...`, tự động chọn loại thiết bị, hiển thị banner gói và điền sẵn mô tả lỗi.
- **Đặt lịch 2 bước:** Hỗ trợ 3 nhóm thiết bị (*Laptop, Điện thoại, Tablet*), đính kèm ảnh thiết bị, chọn **Mang trực tiếp đến cửa hàng** (kiểm tra khung giờ làm việc `07:30 – 21:00`) hoặc **Nhận máy tận nơi** (tự động cộng phí vận chuyển `20.000đ`).
- **Trạng thái đơn tuyến tính:** Cập nhật một chiều qua các bước `Chưa tiếp nhận` → `Đã tiếp nhận` → `Đang chẩn đoán` → `Chờ duyệt giá` → `Đang sửa chữa` → `Chờ thanh toán` → `Hoàn tất` (hoặc `Đã huỷ`) kèm hộp thoại xác nhận trước khi đổi trạng thái.
- **Duyệt báo giá hai chiều:** Kỹ thuật viên nhập **Giá ước tính** (hỗ trợ cả mức giá **`0đ (Miễn phí)`** cho các gói kiểm tra/sấy khô miễn phí); khách hàng có thể **Duyệt giá và sửa**, **Yêu cầu báo giá lại** hoặc **Hủy đơn** ngay trên `public/dashboard.html` (`api/approve_quote.php`).
- **Gửi email thông báo thanh toán:** Khi đơn ở trạng thái **Chờ thanh toán**, quản trị viên/kỹ thuật viên có thể bấm **Gửi mail** để thông báo cho khách đến kiểm tra máy và thanh toán (`includes/mailer.php`, `api/update_order_status.php`).

### Đánh giá khách hàng & Quản trị
- **Trang Liên hệ & Đánh giá (`public/contact.html`):**
  - Hiển thị điểm trung bình, biểu đồ phân bổ số sao (`5 ★` → `1 ★`), danh sách thẻ đánh giá kèm **ảnh đại diện khách hàng**, tên thiết bị và mã đơn đã xác thực (`api/get_reviews.php`).
  - Nút **Viết đánh giá** mở hộp thoại pop-up (`<dialog>`) cho phép khách hàng chọn đơn đã hoàn tất để gửi đánh giá (`api/submit_review.php`).
- **Hệ thống trang quản trị (`public/admin*.html`):**
  - `admin.html`: Quản lý danh sách đơn, lọc theo trạng thái, tìm kiếm nhanh, phân công kỹ thuật viên, cập nhật giá ước tính/giá cuối.
  - `admin-dashboard.html`: Thống kê tổng quan doanh thu, số lượng đơn và hiệu suất.
  - `admin-inventory.html`: Quản lý tồn kho linh kiện, tìm kiếm và cập nhật số lượng (`api/get_parts.php`, `api/update_part_stock.php`).
  - `admin-technicians.html`: Theo dõi danh sách kỹ thuật viên.

### Hỗ trợ ngoại tuyến (PWA)
- Tích hợp **Web App Manifest** (`public/manifest.json`) và **Service Worker** (`public/sw.js`) để lưu cache tài nguyên tĩnh.
- Đồng bộ dữ liệu đơn hàng qua `localStorage` trên `dashboard.html` và `admin.html`, cho phép xem lại đơn đã tải khi mất mạng và tự động đồng bộ lại khi có kết nối.

---

## 5. Cấu trúc thư mục

```text
techfix/
├── api/                     # Backend PHP REST API (auth, orders, inventory, reviews, profile)
├── assets/                  # Hình ảnh, biểu tượng, banner slider và minh họa SVG
├── config/                  # Cấu hình kết nối DB (database.php) và Mail (mail.php)
├── css/                     # Bảng kiểu chính (style.css) và style bổ sung trang admin (admin-extra.css)
├── includes/                # Middleware phân quyền (auth.php) và gửi mail SMTP (mailer.php)
├── js/                      # Script dùng chung (motion.js, auth-ui.js, admin-common.js, ...)
├── public/                  # Giao diện người dùng & quản trị (HTML, manifest.json, sw.js)
├── report/                  # Tài liệu báo cáo môn học (CSDL, giao diện)
├── uploads/                 # Thư mục lưu avatar người dùng, ảnh đơn hàng và log email
├── .env.example             # Mẫu cấu hình biến môi trường
├── index.php                # Điều hướng mặc định sang public/index.html
├── seed_admin.sql           # Dữ liệu tài khoản mẫu (Admin, Technician, Customer)
├── techfix_schema.sql       # Schema CSDL MariaDB/MySQL và dữ liệu mẫu ban đầu
└── README.md                # Tài liệu hướng dẫn dự án
```

---

## 6. Công nghệ sử dụng

- **Frontend:** HTML5, CSS3 (Custom Properties, Dark/Light Theme, Responsive Grid), Vanilla JavaScript, PWA Service Worker
- **Backend:** PHP 8+ (PDO Prepared Statements, Session Auth, `password_hash` bcrypt)
- **Cơ sở dữ liệu:** MySQL / MariaDB
- **Email:** SMTP TLS (Gmail) thông qua `includes/mailer.php`
