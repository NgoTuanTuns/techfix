/**
 * js/auth-ui.js
 * Khi đã đăng nhập: topbar đổi "Đăng nhập" thành "Đăng xuất",
 * link "Đăng nhập" ở footer bị ẩn. Chưa đăng nhập: giữ nguyên.
 *
 * Nhúng vào MỌI trang, cạnh motion.js (cùng thư mục js, cùng tiền tố đường dẫn):
 *   <script src="js/auth-ui.js" defer></script>
 *
 * Đường dẫn API tự tính từ vị trí file này:  .../js/auth-ui.js -> .../api/
 */
(function () {
  // Đường dẫn tới thư mục api/:
  //  - ưu tiên thuộc tính data-api trên thẻ <script> (tính theo URL của TRANG)
  //  - nếu không có thì suy ra từ vị trí file này (.../js/auth-ui.js -> .../api/)
  // currentScript có thể là null ở một số cách nhúng/defer; lấy dự phòng theo src.
  var script =
    document.currentScript ||
    document.querySelector('script[src*="auth-ui.js"]');
  var API = script && script.dataset.api
    ? new URL(script.dataset.api, window.location.href).href
    : new URL("../api/", window.location.href).href;

  var topLogin = document.querySelector('.topbar-links a[href="login.html"]');
  var footLogin = document.querySelector('.site-footer a[href="login.html"]');

  // Ẩn tạm trong lúc chờ server trả lời để không bị nháy chữ "Đăng nhập"
  [topLogin, footLogin].forEach(function (a) {
    if (a) a.style.visibility = "hidden";
  });
  function reveal() {
    [topLogin, footLogin].forEach(function (a) {
      if (a) a.style.visibility = "";
    });
  }

  function logout(e) {
    if (e) e.preventDefault();
    fetch(API + "logout.php", { method: "POST", credentials: "same-origin" })
      .catch(function () {})
      .then(function () {
        window.location.href = "index.html";
      });
  }

  function applyLoggedIn(user) {
    if (topLogin) {
      // Thay chính liên kết đăng nhập để vị trí và kiểu hiển thị không đổi.
      topLogin.textContent = "Đăng xuất";
      topLogin.href = "#";
      topLogin.title = "Đăng xuất";
      topLogin.addEventListener("click", logout);
    }
    if (footLogin) {
      (footLogin.closest("li") || footLogin).remove();
    }
    // Liên kết đã bị ẩn khi chờ API; phải hiện lại sau khi đổi nhãn.
    reveal();
  }

  // Link "Đăng xuất" ở sidebar dashboard phải thật sự huỷ phiên
  document
    .querySelectorAll('.dash-side a[href="login.html"]')
    .forEach(function (a) {
      a.addEventListener("click", logout);
    });

  fetch(API + "me.php", {
    credentials: "same-origin",
    cache: "no-store",
  })
    .then(function (r) {
      if (!r.ok) throw new Error("Phiên đăng nhập không hợp lệ");
      return r.json();
    })
    .then(function (res) {
      // api/me.php trả thẳng thông tin người dùng tại res.data.
      if (res && res.success === true && res.data && res.data.id) {
        applyLoggedIn(res.data);
      }
      else reveal();
    })
    .catch(function (err) {
      console.error("auth-ui: không lấy được trạng thái đăng nhập", err);
      reveal();
    });
})();
