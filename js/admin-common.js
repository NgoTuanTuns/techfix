/* TechFix — Shared utilities for admin pages (loaded before page-specific scripts). */
(function () {
  "use strict";
  var TF = (window.TF = {});

  TF.OPEN_STATUS = function (s) {
    return s !== "completed" && s !== "cancelled";
  };
  TF.STATUS = {
    pending_receipt: { label: "Chưa tiếp nhận máy", cls: "badge-pending" },
    received: { label: "Đã tiếp nhận", cls: "badge-received" },
    diagnosing: { label: "Đang chẩn đoán", cls: "badge-received" },
    awaiting_approval: { label: "Chờ duyệt giá", cls: "badge-progress" },
    repairing: { label: "Đang sửa chữa", cls: "badge-progress" },
    awaiting_payment: { label: "Chờ thanh toán", cls: "badge-progress" },
    completed: { label: "Hoàn tất", cls: "badge-done" },
    cancelled: { label: "Đã huỷ", cls: "badge-out" },
  };

  TF.tryExtractJson = function (raw) {
    try {
      return JSON.parse(raw);
    } catch (e) {
      var s = raw.indexOf("{"),
        t = raw.lastIndexOf("}");
      if (s !== -1 && t > s) {
        try {
          return JSON.parse(raw.slice(s, t + 1));
        } catch (e2) {}
      }
      return null;
    }
  };
  TF.esc = function (v) {
    var d = document.createElement("div");
    d.textContent = v == null ? "" : v;
    return d.innerHTML;
  };
  TF.formatMoney = function (n) {
    if (n === null || n === undefined) return null;
    return Number(n).toLocaleString("vi-VN") + "đ";
  };
  // Format compact currency: 12500000 -> "12,5tr", 850000 -> "850k" (matches motion.js counter regex)
  TF.shortMoney = function (n) {
    n = Number(n) || 0;
    if (n >= 1e6) return String(+(n / 1e6).toFixed(1)).replace(".", ",") + "tr";
    if (n >= 1e3) return Math.round(n / 1e3) + "k";
    return String(n);
  };
  TF.thisMonth = function (str) {
    if (!str) return false;
    var d = new Date(String(str).replace(" ", "T")),
      now = new Date();
    return (
      d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()
    );
  };
  TF.deviceLabel = function (o) {
    return ((o.brand || "") + " " + (o.model || "")).trim();
  };

  TF.get = async function (url) {
    var cacheKey = "tf_api_cache_" + url;
    try {
      var parsed = TF.tryExtractJson(await (await fetch(url)).text());
      if (parsed && parsed.success) {
        try {
          localStorage.setItem(cacheKey, JSON.stringify(parsed));
        } catch (err) {}
      }
      return parsed;
    } catch (e) {
      try {
        var cached = localStorage.getItem(cacheKey);
        if (cached) return JSON.parse(cached);
      } catch (err2) {}
      return null;
    }
  };
  TF.post = async function (url, fields) {
    var fd = new FormData();
    Object.keys(fields).forEach(function (k) {
      fd.append(k, fields[k]);
    });
    try {
      var r = TF.tryExtractJson(
        await (await fetch(url, { method: "POST", body: fd })).text(),
      );
      return (
        r || {
          success: false,
          message: "Máy chủ trả về dữ liệu không đọc được.",
        }
      );
    } catch (e) {
      return {
        success: false,
        message:
          "Không kết nối được tới máy chủ. Kiểm tra lại XAMPP đã bật chưa.",
      };
    }
  };

  // Enforce role-based access on page load. Defaults to admin and technician.
  TF.guard = async function (page, allowedRoles) {
    var me = await TF.get("../api/me.php");
    if (!me || !me.success) {
      location.href = "login.html";
      return null;
    }
    allowedRoles = allowedRoles || ["admin", "technician"];
    if (!allowedRoles.includes(me.data.role)) {
      alert("Tài khoản của bạn không có quyền truy cập trang quản trị.");
      location.href = "dashboard.html";
      return null;
    }
    return me.data;
  };

  var toastTimer;
  TF.toast = function (msg, isError) {
    var t = document.getElementById("tf-toast");
    if (!t) {
      t = document.createElement("div");
      t.id = "tf-toast";
      t.className = "toast";
      t.setAttribute("role", "status");
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.toggle("err", !!isError);
    t.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      t.classList.remove("show");
    }, 3200);
  };

  // Animate progress bars: apply target width after elements are mounted in the DOM
  TF.growBars = function (root) {
    setTimeout(function () {
      (root || document)
        .querySelectorAll(".bar-fill[data-w]")
        .forEach(function (b) {
          b.style.width = b.dataset.w + "%";
        });
    }, 30);
  };

  TF.openLogoutDialog = function (onConfirm) {
    var dlg = document.getElementById("tf-logout-dialog");
    if (!dlg) {
      dlg = document.createElement("dialog");
      dlg.id = "tf-logout-dialog";
      dlg.className = "tf-dialog";
      dlg.setAttribute("aria-labelledby", "tf-logout-title");
      dlg.setAttribute("aria-describedby", "tf-logout-body");
      dlg.innerHTML =
        '<h3 id="tf-logout-title">Bạn có chắc muốn đăng xuất?</h3>' +
        '<p id="tf-logout-body">Phiên làm việc hiện tại sẽ kết thúc. Bạn sẽ cần đăng nhập lại để tiếp tục quản lý hệ thống.</p>' +
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
  };

  document.addEventListener("DOMContentLoaded", function () {
    var out = document.getElementById("nav-logout");
    if (!out) return;
    out.addEventListener("click", function (e) {
      e.preventDefault();
      TF.openLogoutDialog(async function () {
        try {
          await fetch("../api/logout.php");
        } catch (err) {}
        location.href = "login.html";
      });
    });
  });
})();
