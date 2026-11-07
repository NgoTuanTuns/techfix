/**
 * js/auth-ui.js
 * Updates navigation links based on authentication state:
 * - Authenticated: switches the topbar "Đăng nhập" link to "Đăng xuất" and hides the footer login link.
 * - Unauthenticated: leaves default links intact.
 *
 * Included across customer-facing pages alongside motion.js:
 *   <script src="../js/auth-ui.js" defer></script>
 */
(function () {
  // Resolve the api/ directory URL from the script's data-api attribute or relative path
  var script =
    document.currentScript ||
    document.querySelector('script[src*="auth-ui.js"]');
  var API = script && script.dataset.api
    ? new URL(script.dataset.api, window.location.href).href
    : new URL("../api/", window.location.href).href;

  var topLogin = document.querySelector('.topbar-links a[href="login.html"]');
  var footLogin = document.querySelector('.site-footer a[href="login.html"]');

  // Hide login links until session check completes to prevent layout/label flicker
  [topLogin, footLogin].forEach(function (a) {
    if (a) a.style.visibility = "hidden";
  });
  function reveal() {
    [topLogin, footLogin].forEach(function (a) {
      if (a) a.style.visibility = "";
    });
  }

  function openLogoutDialog(onConfirm) {
    var dlg = document.getElementById("tf-logout-dialog");
    if (!dlg) {
      dlg = document.createElement("dialog");
      dlg.id = "tf-logout-dialog";
      dlg.className = "tf-dialog";
      dlg.setAttribute("aria-labelledby", "tf-logout-title");
      dlg.setAttribute("aria-describedby", "tf-logout-body");
      dlg.innerHTML =
        '<h3 id="tf-logout-title">Bạn có chắc muốn đăng xuất?</h3>' +
        '<p id="tf-logout-body">Phiên đăng nhập hiện tại sẽ kết thúc. Bạn có thể đăng nhập lại bất cứ lúc nào.</p>' +
        '<div class="dialog-actions">' +
        '<button type="button" class="btn btn-outline btn-sm" data-logout-cancel>Ở lại</button>' +
        '<button type="button" class="btn btn-danger btn-sm" data-logout-confirm>Đăng xuất</button>' +
        "</div>";
      dlg.addEventListener("click", function (ev) {
        if (ev.target === dlg || ev.target.closest("[data-logout-cancel]")) {
          dlg.close();
        }
      });
      document.body.appendChild(dlg);
    }
    var confirmBtn = dlg.querySelector("[data-logout-confirm]");
    var nextBtn = confirmBtn.cloneNode(true);
    nextBtn.disabled = false;
    nextBtn.textContent = "Đăng xuất";
    confirmBtn.parentNode.replaceChild(nextBtn, confirmBtn);
    nextBtn.addEventListener("click", function () {
      nextBtn.disabled = true;
      nextBtn.textContent = "Đang đăng xuất...";
      onConfirm();
    });
    dlg.showModal();
  }

  function logout(e) {
    if (e) e.preventDefault();
    openLogoutDialog(function () {
      fetch(API + "logout.php", { method: "POST", credentials: "same-origin" })
        .catch(function () {})
        .then(function () {
          window.location.href = "index.html";
        });
    });
  }

  function applyLoggedIn(user) {
    if (topLogin) {
      // Reuse the existing anchor element so layout and typography remain unchanged
      topLogin.textContent = "Đăng xuất";
      topLogin.href = "#";
      topLogin.title = "Đăng xuất";
      topLogin.addEventListener("click", logout);
    }
    if (footLogin) {
      (footLogin.closest("li") || footLogin).remove();
    }
    // Restore visibility after updating link state
    reveal();
  }

  // Bind logout handler to sidebar logout links on customer dashboard
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
      // api/me.php returns the authenticated user object in res.data
      if (res && res.success === true && res.data && res.data.id) {
        applyLoggedIn(res.data);
      }
      else reveal();
    })
    .catch(function (err) {
      console.error("auth-ui: failed to fetch session status", err);
      reveal();
    });
})();
