# Kế hoạch Triển khai: Xác thực & Quản lý Tài khoản qua Email (TechFix)

> **Mục tiêu:** Đáp ứng trọn vẹn 100% tiêu chí **Account Management (2.0 điểm)** trong đề cương môn học *503073 – Web Programming & Applications (TDTU)*, bao gồm:
> - User registration (0.25đ) kèm Email
> - Account activation qua link email (0.25đ)
> - User login/logout (0.25đ) — dọn dẹp các nút chưa dùng (Google login)
> - Password reset qua link email (0.25đ)

---

## 1. Phân tích Hiện trạng & Yêu cầu mới

| Hạng mục | Hiện trạng TechFix | Yêu cầu cải tiến |
| :--- | :--- | :--- |
| **Đăng ký** | Chỉ nhập Họ tên, SĐT, Mật khẩu (2 lần). | Thêm trường **Email** (bắt buộc, định dạng hợp lệ, duy nhất). Tự động dùng email này làm email nhận thông báo, kích hoạt tài khoản và đặt lịch. |
| **Kích hoạt tài khoản** | Chưa có. Đăng ký xong là tài khoản dùng bình thường ngay. | Sau khi đăng ký, hệ thống gửi email chứa **Link kích hoạt**. Tài khoản vẫn được dùng dịch vụ nhưng hiện banner thông báo *"Tài khoản chưa kích hoạt, vui lòng kiểm tra email..."*. Bấm link sẽ kích hoạt và ẩn banner. |
| **Quên mật khẩu** | Chưa có form / API quên mật khẩu. | Thêm link *"Quên mật khẩu?"* tại form đăng nhập -> Nhập email -> Gửi link reset có token thời hạn (ví dụ 15-30 phút) -> Nhấp vào link dẫn đến trang đặt lại mật khẩu mới (nhập 2 lần). |
| **Nút Google Login** | Còn nút *"Tiếp tục với Google"* (tĩnh, chưa tích hợp OAuth). | **Xóa bỏ hoàn toàn** nút này để giao diện gọn gàng, tránh thầy thắc mắc tính năng chưa hoàn thiện. |

---

## 2. Kế hoạch Chi tiết từng Bước

### Bước 1: Nâng cấp Cơ sở dữ liệu (`techfix_schema.sql` & Migration)
Cần bổ sung các trường vào bảng `users`:
1. `is_active TINYINT(1) DEFAULT 0`: Trạng thái kích hoạt tài khoản (0 = chưa kích hoạt, 1 = đã kích hoạt).
2. `activation_token VARCHAR(64) NULL`: Token băm bảo mật cho link kích hoạt tài khoản.
3. `reset_token VARCHAR(64) NULL`: Token cho việc đặt lại mật khẩu.
4. `reset_token_expires_at DATETIME NULL`: Thời gian hết hạn của link reset mật khẩu (ví dụ: `NOW() + INTERVAL 30 MINUTE`).

> *Lưu ý:* Tài khoản mẫu (Admin, KTV, Khách mẫu) trong `seed_admin.sql` sẽ được đặt sẵn `is_active = 1` để thầy đăng nhập chấm bài không bị vướng kích hoạt.

---

### Bước 2: Bổ sung Mẫu Email & Hàm gửi (`includes/mailer.php`)
Tận dụng SMTP socket có sẵn trong `includes/mailer.php`:
1. **`send_activation_email($toEmail, $fullName, $token)`**:
   - Gửi thư chào mừng TechFix kèm nút bấm: *"Kích hoạt tài khoản ngay"*.
   - Link: `http://<domain>/techfix/public/activate.html?token=...` (hoặc gọi qua `api/activate_account.php`).
2. **`send_password_reset_email($toEmail, $fullName, $token)`**:
   - Gửi thư yêu cầu đổi mật khẩu kèm nút: *"Đặt lại mật khẩu"*.
   - Link: `http://<domain>/techfix/public/reset-password.html?token=...`.

---

### Bước 3: Cập nhật & Xây dựng các Endpoint API (`api/`)

1. **`api/register.php`**:
   - Nhận thêm `email`.
   - Validate định dạng email & kiểm tra không trùng lặp trong bảng `users`.
   - Sinh `activation_token = bin2hex(random_bytes(32))`.
   - Tạo user với `is_active = 0`.
   - Gửi email kích hoạt.
   - Vẫn tự động đăng nhập (session) theo đúng rubric và trả về `is_active: false`.

2. **`api/activate_account.php`** *(Mới)*:
   - Nhận `token` qua GET hoặc POST.
   - Tìm user có `activation_token` khớp.
   - Cập nhật `is_active = 1`, xoá `activation_token = NULL`.
   - Trả về thông báo thành công để chuyển hướng người dùng vào Dashboard.

3. **`api/forgot_password.php`** *(Mới)*:
   - Nhận `email`.
   - Kiểm tra email tồn tại -> Sinh `reset_token = bin2hex(random_bytes(32))` và `expires_at = NOW() + 30 phút`.
   - Gửi email kèm link reset mật khẩu.
   - Phản hồi thông báo hướng dẫn người dùng kiểm tra hòm thư.

4. **`api/reset_password.php`** *(Mới)*:
   - Nhận `token`, `new_password`, `new_password2`.
   - Kiểm tra token hợp lệ và chưa hết hạn.
   - Cập nhật `password_hash = password_hash(...)`, xoá `reset_token = NULL`.
   - Huỷ session cũ nếu có để buộc đăng nhập lại bằng mật khẩu mới (đúng quy chuẩn rubric).

5. **`api/me.php`**:
   - Trả thêm trường `is_active` để giao diện biết có cần hiện banner nhắc nhở kích hoạt hay không.

---

### Bước 4: Chỉnh sửa & Bổ sung Giao diện (`public/`)

1. **`public/login.html`**:
   - **Form Đăng ký**: Thêm trường nhập `Email` (input type `email`, required).
   - **Form Đăng nhập**: Thêm link bấm *"Quên mật khẩu?"* mở view Quên mật khẩu.
   - **Loại bỏ**: Xóa hoàn toàn nút *"Tiếp tục với Google"* (và vạch kẻ *"hoặc đăng nhập bằng"*).
   - **Thêm Form / Tab Quên mật khẩu**: Nhập email nhận link đặt lại mật khẩu.

2. **`public/reset-password.html`** *(Mới)*:
   - Màn hình nhập Mật khẩu mới và Nhập lại mật khẩu mới khi người dùng bấm vào link trong email.
   - Sau khi đổi thành công, tự động chuyển về trang đăng nhập.

3. **`public/dashboard.html` & các trang liên quan**:
   - Thêm thanh thông báo màu vàng/cam ở đầu trang khi `user.is_active === 0`:
     > ⚠️ **Tài khoản của bạn chưa được kích hoạt.** Vui lòng kiểm tra email để nhấn vào liên kết kích hoạt tài khoản. [Gửi lại link kích hoạt]
   - Khi tài khoản đã kích hoạt (`is_active === 1`), thanh thông báo này tự động ẩn hoàn toàn.
   - Ô email trong tab Hồ sơ hiển thị sẵn email đã đăng ký.

4. **`public/booking.html`**:
   - Ô email nhận thông báo tự động điền sẵn email từ tài khoản đã đăng ký (khách không phải gõ lại).

---

## 5. Lộ trình Thực hiện theo Thứ tự Ưu tiên

1. **Bước 1**: Cập nhật Database Schema (`techfix_schema.sql` và migration cột `is_active`, `activation_token`, `reset_token`).
2. **Bước 2**: Viết hàm gửi mail kích hoạt & reset mật khẩu trong `includes/mailer.php`.
3. **Bước 3**: Cập nhật `api/register.php` (thêm Email), tạo các API `api/activate_account.php`, `api/forgot_password.php`, `api/reset_password.php`.
4. **Bước 4**: Sửa `public/login.html` (loại bỏ nút Google, thêm trường Email vào đăng ký, thêm luồng quên mật khẩu).
5. **Bước 5**: Tạo trang `public/reset-password.html` và thêm Banner nhắc kích hoạt tài khoản trên `public/dashboard.html`.
6. **Bước 6**: Kiểm thử toàn diện và cập nhật `Readme.txt`.
