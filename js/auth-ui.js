/**
 * js/auth-ui.js
 * Khi đã đăng nhập: topbar đổi "Đăng nhập" thành "<tên> | Đăng xuất",
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
  var script = document.currentScript;
  var API = script.dataset.api
    ? new URL(script.dataset.api, window.location.href).href
    : new URL("../../api/", script.src).href;

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
      topLogin.textContent = user.full_name; // textContent: an toàn XSS
      topLogin.href = user.role === "customer" ? "dashboard.html" : "admin.html";
      topLogin.title = "Tài khoản của tôi";
      topLogin.style.cssText =
        "display:inline-block;max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom";

      var out = document.createElement("a");
      out.href = "#";
      out.textContent = "Đăng xuất";
      out.addEventListener("click", logout);
      topLogin.insertAdjacentElement("afterend", out);
    }
    if (footLogin) {
      (footLogin.closest("li") || footLogin).remove();
    }
  }

  // Link "Đăng xuất" ở sidebar dashboard phải thật sự huỷ phiên
  document
    .querySelectorAll('.dash-side a[href="login.html"]')
    .forEach(function (a) {
      a.addEventListener("click", logout);
    });

  fetch(API + "me.php", { credentials: "same-origin" })
    .then(function (r) {
      return r.json();
    })
    .then(function (res) {
      if (res.success && res.data.logged_in) applyLoggedIn(res.data.user);
      else reveal();
    })
    .catch(function (err) {
      console.error("auth-ui: không lấy được trạng thái đăng nhập", err);
      reveal();
    });
})();
