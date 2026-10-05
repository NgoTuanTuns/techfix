# style.md — Rừng Mạch (Circuit Grove)

> File này là nguồn sự thật duy nhất về giao diện. Claude Code đọc hết trước khi viết dòng code đầu tiên.
> Khi file này và thói quen mặc định của bạn mâu thuẫn, **file này thắng**.

---

## 0. Cách làm việc (dành cho Claude Code)

1. Đọc toàn bộ file. Điền các mục `[CHƯA CÓ]` ở phần 1 bằng giá trị mặc định đã ghi, **chỉ hỏi người dùng tối đa 3 câu** nếu thiếu thông tin quan trọng (tên thương hiệu, loại dịch vụ, stack). Không hỏi gì thì dùng mặc định.
2. Tạo `tokens.css` (phần 4) trước, rồi `base.css`, rồi mới tới từng section.
3. **Dựng hero trước**, chụp màn hình (desktop 1440 và mobile 390), tự phê bình theo phần 12, sửa xong mới làm tiếp.
4. Làm lần lượt các section ở phần 7, mỗi section có một chuyển động riêng (phần 8).
5. Cuối cùng chạy checklist phần 12. Trước khi giao, bỏ bớt một thứ trang trí: nếu thấy rườm thì giảm đi.

Không dùng lại nguyên ảnh tham khảo của người dùng. Mọi hình minh họa vẽ bằng **SVG inline / CSS**.

---

## 1. Tóm tắt dự án (điền / giữ mặc định)

| Mục | Giá trị |
|---|---|
| Tên thương hiệu | `[CHƯA CÓ]` mặc định: **Rừng Mạch** (dùng làm tên tạm, dễ đổi: biến `--brand-name`) |
| Loại web | `[CHƯA CÓ]` mặc định: dịch vụ IT, sửa chữa máy tính/điện thoại và giải pháp công nghệ, một trang landing nhiều section |
| Đối tượng | Sinh viên, dân văn phòng, doanh nghiệp nhỏ ở Việt Nam |
| Việc chính của trang | Khiến người xem tin tưởng và bấm **Đặt lịch** |
| Ngôn ngữ giao diện | Tiếng Việt (`<html lang="vi">`) |
| Stack | `[CHƯA CÓ]` mặc định: HTML + CSS + JS thuần, không cần build; hỏi lại nếu người dùng muốn React/Next/Tailwind |
| Thư viện animation | Mặc định không dùng (CSS + `IntersectionObserver` + Web Animations API). GSAP chỉ khi thật cần cho scroll-scrub |

---

## 2. Ý tưởng thiết kế

**Một câu:** một trang dịch vụ công nghệ gọn gàng, đáng tin (bố cục kiểu trang sửa chữa hiện đại) nhưng nằm trong một bức tranh khắc gỗ ban đêm: trăng tròn, cành cây đung đưa, mưa nhẹ, đom đóm. Điểm mới lạ là **mạch điện (PCB) mọc thành cành cây**.

**Hai nguồn tham khảo, lấy gì từ đâu**

| Lấy từ ảnh 1 (tranh thiên nhiên) | Lấy từ ảnh 2 (trang kiểu TechFix) |
|---|---|
| Nền sương xanh lá nhạt chuyển sang vàng kem ở dưới, có vân giấy | Cấu trúc trang: header dính, hàng cam kết có dấu tick, hero chia 2 cột |
| Vầng trăng tròn vàng kem lớn, phát sáng nhẹ | Nút hành động cam to, bo góc, chữ rõ |
| Cành hoa lá ô-liu vẽ tay, thân mảnh, lá nhỏ | Khối đánh giá sao + số lượng đánh giá |
| Chữ serif cổ điển màu xanh rêu đậm | Card dịch vụ nền pastel nhiều màu |
| Cảm giác tĩnh, chậm, yên tĩnh | Nút liên hệ nổi (gọi, Zalo, Messenger), thẻ ưu đãi sinh viên |

**Họa tiết kết hợp (dùng nhất quán, đừng thêm họa tiết khác)**
- **Mạch → cành:** đường mạch điện 1,5px, rẽ góc 90°/45°, có chấm "via" ở điểm nối; đoạn cuối uốn mềm thành cành có lá. Đây là họa tiết ký tên của trang.
- **Mưa:** các nét dọc rất mảnh, rơi chậm, màu rêu trên nền sáng (kiểu "data rain" nhưng dịu).
- **Đom đóm = đèn LED:** chấm sáng vàng nhạt, nhấp nháy chậm như đèn trạng thái.
- **Lá có gân là đường mạch:** gân lá vẽ bằng nét thẳng gấp khúc thay vì cong.
- **Dấu triện (hanko) cam đỏ:** dùng cho ưu đãi / huy hiệu, thay cho nhãn "HOT" hay "SALE".

**Điểm nhấn duy nhất (chỉ tiêu "độ táo bạo" vào đây):** hero có trăng mọc, cành-mạch mọc ngang qua trăng và một cửa sổ code nổi. Các phần còn lại giữ yên tĩnh, kỷ luật.

---

## 3. Màu sắc

Sáng là mặc định. Có chế độ "đêm trăng" tùy chọn (phần 9).

| Tên | Hex | Dùng cho |
|---|---|---|
| Sương sớm | `#B9C9B0` | Dải trời phía trên hero, nền section xen kẽ |
| Giấy washi | `#EEF0D6` | Nền chính (hơi ngả xanh lá, **không** dùng kem ấm `#F4F1EA`) |
| Trăng | `#FFF4B8` | Vầng trăng, glow, điểm sáng |
| Mực rêu | `#1E3B2F` | Chữ chính, tiêu đề, viền nhấn |
| Lá ô-liu | `#77744A` | Cành lá, icon, chữ phụ đậm |
| Cam hồng kaki | `#C2540F` | Nút chính, dấu triện, link nhấn (chữ trắng trên nền này đạt ~4.6:1) |

**Màu phụ**
- Cam sáng `#F0A35E`: viền hover, tia sáng, **không** dùng làm màu chữ trên nền sáng.
- Pastel cho card dịch vụ (nhạt, hơi xỉn như mực in): Đào `#F5D7BA`, Sương lam `#CBDDE0`, Matcha `#D3E2BC`, Tử đằng `#DCD2EA`.
- Chữ phụ: `#4A5B4F` (trên Giấy washi đạt > 6:1).
- Lỗi `#A63A2B`, thành công `#3F7A4F`.

**Quy tắc**
- Cam chỉ xuất hiện ở: nút hành động chính, dấu triện, sao đánh giá, trạng thái hover. Dưới 8% diện tích màn hình.
- Không dùng gradient màu mè. Chỉ cho phép: dải sương → washi trong hero, và glow của trăng.
- Bóng đổ phải **nhuốm màu rêu**, không dùng xám trung tính (xem token bên dưới).

---

## 4. Design tokens (`tokens.css`)

```css
:root {
  /* màu */
  --mist:        #B9C9B0;
  --washi:       #EEF0D6;
  --moon:        #FFF4B8;
  --moss-ink:    #1E3B2F;
  --olive:       #77744A;
  --persimmon:   #C2540F;
  --persimmon-lt:#F0A35E;
  --ink-soft:    #4A5B4F;
  --card-peach:  #F5D7BA;
  --card-mist:   #CBDDE0;
  --card-matcha: #D3E2BC;
  --card-wisteria:#DCD2EA;

  /* chữ */
  --font-display: "Fraunces", "Noto Serif Display", Georgia, serif;
  --font-body:    "Be Vietnam Pro", system-ui, sans-serif;
  --font-code:    "JetBrains Mono", ui-monospace, monospace;

  /* bo góc theo cấp bậc, KHÔNG dùng một giá trị cho mọi thứ */
  --r-chip: 999px;
  --r-btn:  14px;
  --r-card: 22px;
  --r-hero: 36px;   /* khung hero lớn */
  --r-leaf: 4px 22px 4px 22px; /* hình lá cho vài thẻ nhấn */

  /* bóng nhuốm rêu */
  --shadow-1: 0 1px 2px rgba(30,59,47,.10), 0 6px 16px -6px rgba(30,59,47,.18);
  --shadow-2: 0 2px 4px rgba(30,59,47,.10), 0 24px 48px -16px rgba(30,59,47,.28);

  /* khoảng cách (thang 4px) */
  --s-1: 4px; --s-2: 8px; --s-3: 12px; --s-4: 16px; --s-5: 24px;
  --s-6: 32px; --s-7: 48px; --s-8: 72px; --s-9: 112px;

  /* chuyển động */
  --ease-leaf:  cubic-bezier(.22,.8,.3,1);   /* vào cảnh, mềm cuối */
  --ease-drift: cubic-bezier(.4,0,.2,1);     /* trôi, đung đưa */
  --ease-spring:cubic-bezier(.34,1.56,.64,1);/* chỉ cho dấu triện, nảy lá */
  --t-fast: 160ms; --t-base: 420ms; --t-slow: 900ms; --t-scene: 1600ms;

  /* bố cục */
  --container: 1200px;
  --gutter: clamp(20px, 4vw, 48px);
}
```

---

## 5. Chữ

**Hai họ chữ, khác nhau rõ ràng.**

- **Tiêu đề:** *Fraunces* (serif mềm, có cảm giác mực in, hợp tranh khắc). Dùng trục `opsz` lớn cho H1, `SOFT` ~50, weight 600.
- **Nội dung:** *Be Vietnam Pro* (thiết kế cho tiếng Việt, đọc rõ).
- **Code:** *JetBrains Mono*, chỉ dùng bên trong cửa sổ code, không dùng làm nhãn nhỏ rải rác.

> Nạp từ Google Fonts với subset `latin, latin-ext, vietnamese`, `font-display: swap`.
> **Bắt buộc kiểm tra dấu tiếng Việt** bằng chuỗi `Máy hư đừng lo, ươ ế ữ ặ ỡ ầ` ở cả 3 họ chữ. Nếu Fraunces thiếu glyph thì thay bằng *Noto Serif Display*.

**Thang chữ**

| Cấp | Cỡ | Line-height | Ghi chú |
|---|---|---|---|
| H1 | `clamp(2.6rem, 6vw, 4.75rem)` | 1.04 | Fraunces **nghiêng toàn bộ câu** (như câu slogan trong ảnh 2), tracking `-0.02em` |
| H2 | `clamp(1.9rem, 3.6vw, 3rem)` | 1.12 | Fraunces roman 600 |
| H3 | `1.35rem` | 1.3 | Be Vietnam Pro 600 |
| Body | `1.0625rem` (17px) | 1.65 | Be Vietnam Pro 400, dòng tối đa 62ch |
| Phụ | `0.9375rem` | 1.55 | màu `--ink-soft` |
| Nút | `1.0625rem` | 1 | Be Vietnam Pro 600 |

**Cấm (đây là dấu hiệu "web do AI làm"):**
- Nhãn nhỏ IN HOA giãn chữ phía trên mỗi tiêu đề.
- Tô màu/nghiêng riêng **một từ** trong tiêu đề để làm điểm nhấn.
- Thêm mũi tên `→` vào cuối mọi nút và link.
- Chuỗi meta nối bằng dấu chấm giữa (`A · B · C`).
- Đánh số `01 / 02 / 03` ở nơi nội dung không phải một chuỗi bước. (Chỉ cho phép ở section **Quy trình**.)

---

## 6. Bố cục và thành phần

**Khung chung**
- Container `--container`, lề `--gutter`. Căn trái là mặc định; chỉ căn giữa tiêu đề của vài section ngắn.
- Nhịp dọc giữa section: `--s-9` desktop, `--s-8` tablet, `--s-7` mobile.
- Nền section xen kẽ rất nhẹ: Giấy washi ↔ Giấy washi phủ 40% Sương sớm. Phân cách section bằng **đường chân trời gợn** (SVG path lượn nhẹ), không dùng vạch kẻ thẳng.
- Nền toàn trang có lớp **vân giấy** (SVG `feTurbulence`, opacity 0.06, `mix-blend-mode: multiply`, phủ bằng `position: fixed; pointer-events: none`).

**Nút**
- Chính: nền `--persimmon`, chữ `#FFF`, `--r-btn`, padding `18px 28px`, `--shadow-1`. Hover: nền sáng hơn một bậc, viền `--persimmon-lt` 2px lộ ra từ trong ra ngoài.
- Phụ: viền `--moss-ink` 1.5px, nền trong suốt. Hover: nền `--card-matcha`.
- Focus: viền 2px `--moss-ink` + vòng ngoài 3px `--persimmon-lt`, offset 2px.
- Không dùng mũi tên ở cuối nhãn nút. Nhãn là động từ cụ thể: "Đặt lịch sửa chữa", "Xem bảng giá", "Gửi yêu cầu".

**Chip cam kết (hàng dưới header trong hero)**
- Dấu tick vẽ bằng SVG, chữ nhỏ, một cụm số in đậm màu `--persimmon` (ví dụ **6 tháng**, **2 giờ**).
- Mặc định 3 chip: "Báo giá trước khi sửa", "Bảo hành 6 tháng", "Lấy trong 2 giờ". Sửa theo thực tế dịch vụ.

**Card dịch vụ (kiểu pastel của ảnh 2, nhưng không đều nhau)**
- Bố cục bento: 1 card lớn (2 cột × 2 hàng) + 3–4 card nhỏ. Mỗi card một màu pastel, một hình minh họa SVG riêng (pin, màn hình, SSD, laptop).
- Card lớn dùng `--r-leaf` (góc lá), card nhỏ dùng `--r-card`. Không để tất cả cùng một bo góc.
- Bên trong card: tiêu đề H3, 1 câu mô tả cụ thể, giá "từ …" nếu có, link nhỏ.

**Khối đánh giá:** 5 sao (cam), điểm lớn `4.8/5`, "2.350 đánh giá trên Google" ở chữ phụ. Số liệu thay bằng số thật của người dùng.

**Thẻ ưu đãi:** hình chữ nhật góc lá, kèm **dấu triện** tròn cam đỏ ("-15%" hoặc chữ "Sinh viên"). Dấu triện hơi nghiêng 8°, viền mực không đều (dùng SVG filter `feTurbulence` + `feDisplacementMap` nhẹ).

**Nút liên hệ nổi (góc phải dưới):** gọi (cam), Zalo (xanh), Messenger (xanh lam). Tròn 56px, cách nhau 12px, `position: fixed`, tránh che nội dung quan trọng trên mobile (dịch lên khi có thanh bottom).

---

## 7. Cấu trúc trang

Từ trên xuống. Tên section là gợi ý, đổi theo dịch vụ thật.

1. **Header (dính):** logo (dấu lá + khối vuông bo góc nhỏ), menu: Trang chủ, Dịch vụ, Quy trình, Đặt lịch, Liên hệ. Mobile: menu trượt từ phải.
2. **Hero:** hàng chip cam kết → H1 (3 dòng) → mô tả 1–2 câu → nút chính + khối đánh giá. Cột phải: **khung hero** `--r-hero` chứa trăng, cành-mạch, cửa sổ code nổi.
3. **Dịch vụ** (bento pastel).
4. **Quy trình** (5 bước, nối bằng dây leo): Đặt lịch, Kiểm tra, Báo giá, Sửa chữa, Nhận máy.
5. **Vì sao chọn chúng tôi:** 3 lý do, mỗi lý do kèm một con số thật.
6. **Số liệu / đánh giá:** vòng trăng tăng dần.
7. **Khách hàng nói gì:** carousel đánh giá.
8. **Ưu đãi sinh viên:** thẻ + dấu triện.
9. **Câu hỏi thường gặp:** accordion.
10. **CTA cuối trang:** cảnh đêm, trăng tròn, một form ngắn (tên, số điện thoại, thiết bị hỏng).
11. **Footer:** đường nét bụi cỏ/tre, thông tin liên hệ, giờ mở cửa.

**Wireframe hero (desktop)**
```
┌────────────────────────────────────────────────────────────────┐
│ ◧ Logo           Trang chủ  Dịch vụ  Quy trình  Đặt lịch  Liên hệ│  ← header
├────────────────────────────────────────────────────────────────┤
│  ✓ chip  ✓ chip  ✓ chip                           ◯ trăng      │
│                                                (nhô ra ngoài   │
│  H1 nghiêng, 3 dòng              ┌────────────┐  khung)        │
│  serif lớn                       │ cành-mạch  │                │
│                                  │  ┌──code─┐ │                │
│  Mô tả ngắn                      │  │ ▍▍▍   │ │                │
│  [ Đặt lịch ]  ★★★★★ 4.8/5       │  └───────┘ │                │
│                                  └────────────┘                │
└────────────────────────────────────────────────────────────────┘
 Mobile: chip → H1 → mô tả → nút → khung hero (trăng nhỏ lại, nằm sau H1).
```

---

## 8. Chuyển động (mỗi section một "tính cách" riêng)

**Nguyên tắc**
- **Không** dùng một hiệu ứng "mờ dần + trượt lên" lặp lại cho mọi section. Mỗi section có **một** chuyển động ký tên, lấy từ thiên nhiên hoặc mạch điện (bảng dưới).
- Chỉ animate `transform`, `opacity`, `clip-path`, `stroke-dashoffset`. Tránh animate `width/height/top/left`.
- Tạo hiệu ứng khi vào màn hình bằng `IntersectionObserver` (`threshold: 0.25`, `once`). Vòng lặp nền (mưa, đom đóm, đung đưa) **tạm dừng** khi section ra khỏi màn hình.
- Tối đa 2 vòng lặp nền chạy cùng lúc trong khung nhìn. Mobile: giảm hạt mưa/đom đóm còn 40%.
- `prefers-reduced-motion: reduce` → tắt hết chuyển động nền, mọi thứ hiển thị ngay ở trạng thái cuối, chỉ giữ chuyển màu rất ngắn.

| Section | Chuyển động ký tên | Cách làm gợi ý |
|---|---|---|
| **Tải trang / Hero** *(khoảnh khắc chính)* | Trăng mọc → cành-mạch mọc → chữ hiện → nút | Trình tự 0 → 2,4s: nền sương hiện (0,5s); **trăng** trượt từ mép dưới khung lên vị trí, glow nở dần (`--t-scene`); đường mạch vẽ ra bằng `stroke-dashoffset` rồi chuyển thành cành, lá bật ra lần lượt (`scale 0→1`, `--ease-spring`, cách nhau 70ms); **H1 hiện kiểu mực loang**: `clip-path: inset(0 100% 0 0)` → `inset(0)` từng dòng, cách nhau 140ms; chip tick vẽ nét; nút chính hiện cuối. Sau đó: cành đung đưa ±1,2° chu kỳ 7s (`--ease-drift`), mưa rơi chậm, 2–3 đom đóm nhấp nháy |
| **Header** | Trong suốt trên hero, khi cuộn thì phủ washi mờ | `backdrop-filter: blur(10px)`, viền dưới rêu 1px hiện dần. Link menu hover: gạch chân vẽ ra như gân lá (SVG, 240ms) |
| **Hàng chip cam kết** | Dấu tick được vẽ từng cái | `stroke-dashoffset` 320ms, cách nhau 90ms |
| **Dịch vụ (bento)** | Card **giở ra như tờ giấy washi** | `clip-path: inset(100% 0 0 0)` → `inset(0)`, từ góc dưới-trái, cách nhau 110ms. Hover từng card có vi chuyển động **riêng theo nội dung**: pin đầy dần, đèn SSD nhấp nháy, màn hình hiện hình lá. Chỉ card lớn mới nghiêng nhẹ 1° khi hover |
| **Quy trình** | **Dây leo mọc** nối 5 bước theo tiến độ cuộn | Một path SVG dài, `stroke-dashoffset` gắn với tiến độ cuộn (scroll-driven animation nếu trình duyệt hỗ trợ, ngược lại dùng JS + `requestAnimationFrame`). Đến mỗi bước thì nút bước **nở hoa** (`scale` + 5 cánh xoay). Một đom đóm chạy dọc dây leo. Đây là chỗ duy nhất được đánh số 1–5 |
| **Vì sao chọn chúng tôi** | Mỗi lý do có icon vẽ nét | Icon SVG tự vẽ khi vào màn hình, 600ms |
| **Số liệu / đánh giá** | **Vòng trăng** tăng dần | Số đếm lên (1,2s, `easeOut`); quanh số là vòng tròn SVG tăng từ trăng khuyết lên trăng tròn bằng mask |
| **Khách hàng nói gì** | Chuyển thẻ như **gợn nước** | Thẻ cũ mờ + nhòe nhẹ (`filter: blur(4px)`), thẻ mới mở rộng từ một vòng tròn (`clip-path: circle()`). Tự chạy mỗi 6s, **dừng khi hover/focus**, có nút trước/sau và chấm trang |
| **Ưu đãi sinh viên** | **Đóng dấu triện** | Dấu triện thả từ `scale 1.6, rotate 20°, opacity 0` về `scale 1, rotate 8°` bằng `--ease-spring`, 520ms, kèm vòng mực lan nhẹ. Chỉ chạy một lần |
| **Câu hỏi thường gặp** | Mở như **lá cuộn** | Accordion dùng `grid-template-rows: 0fr → 1fr` (320ms); biểu tượng chiếc lá xoay 90°. Hỗ trợ bàn phím (Enter/Space) |
| **CTA cuối trang** | **Trăng tròn dần** theo cuộn | Khi cuộn vào section, nền chuyển mượt sang tông đêm (`#12211A`), trăng lớn dần lên vị trí; nút khi bấm tạo **vòng gợn nước** lan ra từ điểm bấm |
| **Footer** | Bụi cỏ lay + đom đóm | 3 lớp bụi cỏ/tre SVG đung đưa lệch pha, lệch vận tốc nhẹ (parallax khi cuộn); đom đóm là các chấm LED nhấp nháy. Nút "lên đầu trang" là chiếc lá bay nhẹ |
| **Nút liên hệ nổi** | Nhấp nhô rất nhẹ | `translateY(±3px)` 3s, lệch pha; vòng xung nhịp mỗi 8s (một lần mỗi 8s, không liên tục). Hover: mở rộng ra nhãn chữ |

**Chi tiết hoạt họa cho hero (mô tả cụ thể)**
- **Trăng:** `radial-gradient(circle, #FFF8CC 0%, var(--moon) 55%, rgba(255,244,184,0) 72%)`, kèm `box-shadow` glow 0 0 120px rgba(255,244,184,.7). Thở nhẹ (scale 1 → 1,02, 9s).
- **Mưa:** 18–24 `<span>` cao 24–48px, rộng 1px, `opacity .25–.5`, rơi 3–6s, trễ ngẫu nhiên. Chỉ nằm trong khung hero.
- **Cửa sổ code nổi:** nền kính (`rgba(238,240,214,.75)` + `backdrop-filter: blur(8px)`), `--r-card`, `--shadow-2`. Gõ chữ từng dòng (typewriter 28ms/ký tự) với con trỏ nhấp nháy. Chủ đề màu cú pháp: từ khóa `#C2540F`, chuỗi `#4F7A4A`, ghi chú `#77744A`, chữ thường `#1E3B2F`. Nội dung mẫu: vài dòng chẩn đoán thiết bị, ví dụ `diagnose(device)` → `status: "ok"`.

---

## 9. Chế độ "đêm trăng" (tùy chọn nhưng nên làm)

- Công tắc ở header: mặt trời ↔ trăng, chuyển bằng cung tròn trong 600ms.
- Tông đêm: nền `#12211A`, thẻ `#1B3027`, chữ `#EEF0D6`, chữ phụ `#B9C9B0`, trăng giữ nguyên, cam hồng kaki giữ nguyên (kiểm tra lại độ tương phản).
- Tôn trọng `prefers-color-scheme` lần đầu vào, lưu lựa chọn bằng `localStorage` (bọc `try/catch`).
- Mưa và đom đóm đậm hơn ở chế độ đêm, cành lá chuyển sang `#A9B08A`.

---

## 10. Minh họa SVG (chỉ dẫn vẽ)

- **Cành chính:** một đường cong tay vẽ, nét 2–3px, màu `--olive`, hơi gấp khúc ở các nút. Lá nhỏ hình oval dài, mọc xen kẽ, đôi lúc 3 lá ghép như hoa hagi; màu `--olive` opacity .85 xen `#A9B08A`.
- **Mạch điện:** nét 1,5px màu `--moss-ink` opacity .7, chỉ rẽ góc 90° hoặc 45°, chấm "via" bán kính 3px. Đường mạch bắt đầu từ mép cửa sổ code, dần mềm đi thành cành.
- **Icon dịch vụ** (pin, màn hình, SSD, laptop): kiểu nét + mảng màu phẳng, cùng một độ dày nét 2px, có một chi tiết lá hoặc mạch nhỏ trên mỗi icon.
- **Đường chân trời gợn:** path với 2–3 sóng nhẹ, ngăn cách các section, màu trùng nền section kế tiếp.
- Tất cả SVG trang trí đặt `aria-hidden="true"`; SVG có nghĩa (logo, icon trạng thái) có `<title>`.
- Không dùng ảnh thật ở hero. Nếu cần ảnh người thật (thợ), chừa một ô giữ chỗ có `TODO` và bo `--r-hero`, người dùng sẽ tự thay.

---

## 11. Nội dung (giọng văn)

- Tiếng Việt thân thiện, câu ngắn, động từ rõ, **không** văn quảng cáo rỗng ("tối ưu", "đột phá", "giải pháp toàn diện").
- Gọi tên theo cái người dùng hiểu ("Thay pin", không phải "Dịch vụ năng lượng thiết bị").
- Nút = hành động cụ thể: "Đặt lịch sửa chữa". Cùng một hành động giữ nguyên tên suốt luồng (nút "Gửi yêu cầu" → thông báo "Đã gửi yêu cầu").
- Lỗi và trống: nói chuyện gì xảy ra và cách sửa, không xin lỗi chung chung. Ví dụ: "Số điện thoại cần 10 chữ số. Kiểm tra lại giúp mình."
- Nội dung mẫu (thay khi có nội dung thật):
  - H1: *"Máy trục trặc cứ mang qua, kiểm tra xong mới báo giá."*
  - Mô tả: "Thợ kiểm tra ngay trước mặt bạn, báo giá rõ ràng, bạn đồng ý rồi mới sửa."
  - Mọi con số (đánh giá, bảo hành, thời gian) phải lấy từ người dùng. Nếu chưa có, dùng giá trị rõ ràng là giả (`[số]`) thay vì bịa số trông như thật.

---

## 12. Điều không làm, kiểm tra trước khi giao

**Tránh những "dấu hiệu AI" này**
- Nền kem ấm `#F4F1EA` + serif tương phản cao + màu đất nung `#D97757`.
- Một bo góc và một loại bóng xám cho mọi card.
- Gradient tím/xanh làm trang trí.
- Hiệu ứng "mờ dần + trượt lên" cho mọi section, hoặc hover nâng card cho mọi card.
- Emoji làm icon.

**Checklist chất lượng (phải đạt)**
- [ ] Responsive: 390, 768, 1024, 1440. Không cuộn ngang.
- [ ] Tương phản chữ ≥ 4.5:1 (chữ lớn ≥ 3:1) ở cả chế độ sáng và đêm.
- [ ] Focus bàn phím luôn nhìn thấy; thứ tự tab hợp lý; vùng bấm ≥ 44×44px.
- [ ] `prefers-reduced-motion` tắt hết chuyển động nền.
- [ ] Hero có animation chạy mượt 60fps trên máy trung bình; LCP < 2,5s; JS tổng < 60KB (không tính font).
- [ ] Font tiếng Việt hiển thị đủ dấu, không rơi về font hệ thống xấu.
- [ ] Ảnh/SVG có `alt` hoặc `aria-hidden` đúng chỗ; form có nhãn, thông báo lỗi rõ.
- [ ] Mọi vòng lặp nền tạm dừng khi ra khỏi màn hình.
- [ ] Tự hỏi: "Thứ nào trang trí mà không phục vụ gì?" → bỏ đi.

---

## 13. Cấu trúc thư mục gợi ý (stack thuần)

```
/
├─ index.html
├─ css/
│  ├─ tokens.css      /* phần 4 */
│  ├─ base.css        /* reset, chữ, vân giấy, focus */
│  ├─ components.css  /* nút, chip, card, form */
│  └─ sections.css    /* từng section + animation riêng */
├─ js/
│  ├─ reveal.js       /* IntersectionObserver, bật/tắt vòng lặp nền */
│  ├─ hero.js         /* trình tự mở màn, typewriter, mưa, đom đóm */
│  ├─ vine.js         /* dây leo theo tiến độ cuộn */
│  ├─ carousel.js
│  └─ theme.js        /* ngày/đêm */
└─ assets/svg/        /* cành, mạch, icon, đường chân trời */
```

Nếu dùng framework: giữ nguyên token (CSS variables), tách mỗi section thành một component, giữ chuyển động riêng của từng section như phần 8.
