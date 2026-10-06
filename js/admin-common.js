/* TechFix — helper dùng chung cho các trang quản trị (đặt trước script của từng trang). */
(function () {
  "use strict";
  var TF = (window.TF = {});

  TF.OPEN_STATUS = function (s) { return s !== "completed" && s !== "cancelled"; };
  TF.STATUS = {
    received: { label: "Đã tiếp nhận", cls: "badge-received" },
    diagnosing: { label: "Đang chẩn đoán", cls: "badge-received" },
    awaiting_approval: { label: "Chờ duyệt giá", cls: "badge-progress" },
    repairing: { label: "Đang sửa chữa", cls: "badge-progress" },
    completed: { label: "Hoàn tất", cls: "badge-done" },
    cancelled: { label: "Đã huỷ", cls: "badge-out" },
  };

  TF.tryExtractJson = function (raw) {
    try { return JSON.parse(raw); } catch (e) {
      var s = raw.indexOf("{"), t = raw.lastIndexOf("}");
      if (s !== -1 && t > s) { try { return JSON.parse(raw.slice(s, t + 1)); } catch (e2) {} }
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
  // 12500000 -> "12,5tr", 850000 -> "850k" (khớp regex đếm số của motion.js)
  TF.shortMoney = function (n) {
    n = Number(n) || 0;
    if (n >= 1e6) return String(+(n / 1e6).toFixed(1)).replace(".", ",") + "tr";
    if (n >= 1e3) return Math.round(n / 1e3) + "k";
    return String(n);
  };
  TF.thisMonth = function (str) {
    if (!str) return false;
    var d = new Date(String(str).replace(" ", "T")), now = new Date();
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  };
  TF.deviceLabel = function (o) { return ((o.brand || "") + " " + (o.model || "")).trim(); };

  TF.get = async function (url) {
    try { return TF.tryExtractJson(await (await fetch(url)).text()); } catch (e) { return null; }
  };
  TF.post = async function (url, fields) {
    var fd = new FormData();
    Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
    try {
      var r = TF.tryExtractJson(await (await fetch(url, { method: "POST", body: fd })).text());
      return r || { success: false, message: "Máy chủ trả về dữ liệu không đọc được." };
    } catch (e) {
      return { success: false, message: "Không kết nối được tới máy chủ. Kiểm tra lại XAMPP đã bật chưa." };
    }
  };

  // Chặn người không phải admin. Trả về thông tin user, hoặc null nếu đã chuyển trang.
  TF.guard = async function (page) {
    var me = await TF.get("../api/me.php");
    if (!me || !me.success) { location.href = "login.html?next=" + page; return null; }
    if (me.data.role !== "admin") {
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
    toastTimer = setTimeout(function () { t.classList.remove("show"); }, 3200);
  };

  // Animate thanh tiến độ: đặt width sau khi phần tử đã vào DOM
  TF.growBars = function (root) {
    setTimeout(function () {
      (root || document).querySelectorAll(".bar-fill[data-w]").forEach(function (b) {
        b.style.width = b.dataset.w + "%";
      });
    }, 30);
  };

  document.addEventListener("DOMContentLoaded", function () {
    var out = document.getElementById("nav-logout");
    if (!out) return;
    out.addEventListener("click", async function (e) {
      e.preventDefault();
      try { await fetch("../api/logout.php"); } catch (err) {}
      location.href = "login.html";
    });
  });
})();
