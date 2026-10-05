/* TechFix — "Rừng Mạch": chuyển động & trang trí dùng chung cho mọi trang.
   Không phụ thuộc thư viện. Tôn trọng prefers-reduced-motion. */
(function () {
  "use strict";

  var NS = "http://www.w3.org/2000/svg";
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var small = window.matchMedia("(max-width: 700px)").matches;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var clamp = function (v, a, b) { return Math.min(b, Math.max(a, v)); };

  function el(name, attrs, parent) {
    var n = document.createElementNS(NS, name);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }
  function div(cls, parent) {
    var d = document.createElement("div");
    d.className = cls;
    d.setAttribute("aria-hidden", "true");
    if (parent) parent.appendChild(d);
    return d;
  }

  /* ---------- 1. Ngày / đêm ---------- */
  var THEME_KEY = "tf-theme";
  function setTheme(t) { document.documentElement.setAttribute("data-theme", t); }
  function currentTheme() { return document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light"; }

  function initThemeToggle() {
    var wrap = $(".site-header .wrap");
    if (!wrap) return;
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "theme-toggle";
    btn.setAttribute("aria-label", "Chuyển chế độ ngày / đêm");
    btn.title = "Ngày / đêm";
    btn.innerHTML =
      '<svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">' +
      '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.4M12 19.1v2.4M2.5 12h2.4M19.1 12h2.4M5.3 5.3l1.7 1.7M17 17l1.7 1.7M5.3 18.7 7 17M17 7l1.7-1.7"/></svg>' +
      '<svg class="i-moon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.4 14.6A8.5 8.5 0 0 1 9.4 3.6a.6.6 0 0 0-.8-.7A9.5 9.5 0 1 0 21.1 15.4a.6.6 0 0 0-.7-.8Z"/></svg>';
    btn.addEventListener("click", function () {
      var next = currentTheme() === "dark" ? "light" : "dark";
      setTheme(next);
      try { localStorage.setItem(THEME_KEY, next); } catch (e) {}
    });
    wrap.appendChild(btn);
  }

  /* ---------- 2. Header: viền khi cuộn + menu mobile ---------- */
  function initHeader() {
    var header = $(".site-header");
    if (!header) return;
    var onScroll = function () { header.classList.toggle("scrolled", window.scrollY > 8); };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });

    var nav = $(".main-nav", header);
    var wrap = $(".wrap", header);
    if (!nav || !wrap) return;
    var burger = document.createElement("button");
    burger.type = "button";
    burger.className = "nav-burger";
    burger.setAttribute("aria-label", "Mở menu");
    burger.setAttribute("aria-expanded", "false");
    burger.innerHTML = "<span></span><span></span><span></span>";
    wrap.appendChild(burger);
    var close = function () { nav.classList.remove("open"); burger.setAttribute("aria-expanded", "false"); };
    burger.addEventListener("click", function (e) {
      e.stopPropagation();
      var open = nav.classList.toggle("open");
      burger.setAttribute("aria-expanded", open ? "true" : "false");
    });
    document.addEventListener("click", function (e) { if (!nav.contains(e.target)) close(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") close(); });
    $$("a", nav).forEach(function (a) { a.addEventListener("click", close); });
  }

  /* ---------- 3. Gợn nước khi bấm ---------- */
  function initRipple() {
    document.addEventListener("click", function (e) {
      var t = e.target.closest && e.target.closest(".btn, .chip");
      if (!t || reduce) return;
      var r = t.getBoundingClientRect();
      var s = document.createElement("span");
      s.className = "ripple";
      s.style.left = e.clientX - r.left + "px";
      s.style.top = e.clientY - r.top + "px";
      t.appendChild(s);
      setTimeout(function () { s.remove(); }, 750);
    });
  }

  /* ---------- 4. Cành mọc từ mạch điện ---------- */
  function bez(p0, c1, c2, p1, t) {
    var u = 1 - t;
    return [
      u * u * u * p0[0] + 3 * u * u * t * c1[0] + 3 * u * t * t * c2[0] + t * t * t * p1[0],
      u * u * u * p0[1] + 3 * u * u * t * c1[1] + 3 * u * t * t * c2[1] + t * t * t * p1[1]
    ];
  }
  function dbez(p0, c1, c2, p1, t) {
    var u = 1 - t;
    return [
      3 * u * u * (c1[0] - p0[0]) + 6 * u * t * (c2[0] - c1[0]) + 3 * t * t * (p1[0] - c2[0]),
      3 * u * u * (c1[1] - p0[1]) + 6 * u * t * (c2[1] - c1[1]) + 3 * t * t * (p1[1] - c2[1])
    ];
  }
  var LEAF = "M0 0C8-9 22-9 32 0C22 9 8 9 0 0Z";
  var JIT = [0.1, 0.8, 0.4, 0.95, 0.25, 0.6, 0.05, 0.7, 0.35, 0.9, 0.5, 0.2];

  function buildBranch() {
    var svg = el("svg", { viewBox: "0 0 560 360", class: "branch", "aria-hidden": "true", focusable: "false" });
    var k = 0;
    function leaf(parent, pt, angle, scale, light) {
      var g = el("g", { transform: "translate(" + pt[0].toFixed(1) + " " + pt[1].toFixed(1) + ") rotate(" + angle.toFixed(1) + ") scale(" + scale.toFixed(2) + ")" }, parent);
      var p = el("path", { d: LEAF, class: "leaf" + (light ? " lt" : "") }, g);
      p.style.setProperty("--k", k++);
    }
    // 1) đường mạch 90°/45°
    var circuit = el("path", { class: "circuit", pathLength: 1, d: "M0 320H84L124 280V222L164 186H214" }, svg);
    [[124, 280], [124, 222], [164, 186]].forEach(function (c) { el("circle", { class: "via", cx: c[0], cy: c[1], r: 3.5 }, svg); });
    el("circle", { class: "via", cx: 214, cy: 186, r: 5 }, svg);

    // 2) thân cành (2 đoạn Bézier)
    var g = el("g", { class: "sway" }, svg);
    var P0 = [214, 186], C1 = [262, 178], C2 = [300, 140], P1 = [344, 118];
    var D1 = [388, 96], D2 = [440, 78], P2 = [536, 36];
    el("path", { class: "stem", pathLength: 1, d: "M214 186C262 178 300 140 344 118S440 78 536 36" }, g);

    var segs = [[P0, C1, C2, P1], [P1, D1, D2, P2]];
    var n = 0;
    segs.forEach(function (s, si) {
      for (var i = 1; i <= 6; i++) {
        var t = i / 7;
        var pt = bez(s[0], s[1], s[2], s[3], t);
        var d = dbez(s[0], s[1], s[2], s[3], t);
        var base = (Math.atan2(d[1], d[0]) * 180) / Math.PI;
        var side = n % 2 ? 1 : -1;
        var j = JIT[n % JIT.length];
        leaf(g, pt, base + side * (34 + j * 22), 0.72 + j * 0.42, n % 3 === 0);
        n++;
      }
    });
    leaf(g, P2, -30, 0.95, false);

    // 3) hai cành nhỏ có lá
    [[segs[0], 0.62, -1], [segs[1], 0.42, 1]].forEach(function (cfg) {
      var s = cfg[0], t = cfg[1], dir = cfg[2];
      var o = bez(s[0], s[1], s[2], s[3], t);
      var d = dbez(s[0], s[1], s[2], s[3], t);
      var len = Math.hypot(d[0], d[1]);
      var nx = (-d[1] / len) * dir, ny = (d[0] / len) * dir, tx = d[0] / len, ty = d[1] / len;
      var q1 = [o[0] + nx * 22 + tx * 14, o[1] + ny * 22 + ty * 14];
      var q2 = [o[0] + nx * 52 + tx * 46, o[1] + ny * 52 + ty * 46];
      el("path", { class: "twig", pathLength: 1, d: "M" + o[0].toFixed(1) + " " + o[1].toFixed(1) + "Q" + q1[0].toFixed(1) + " " + q1[1].toFixed(1) + " " + q2[0].toFixed(1) + " " + q2[1].toFixed(1) }, g);
      for (var i = 1; i <= 3; i++) {
        var tt = i / 3.4;
        var u = 1 - tt;
        var px = u * u * o[0] + 2 * u * tt * q1[0] + tt * tt * q2[0];
        var py = u * u * o[1] + 2 * u * tt * q1[1] + tt * tt * q2[1];
        var dx = 2 * u * (q1[0] - o[0]) + 2 * tt * (q2[0] - q1[0]);
        var dy = 2 * u * (q1[1] - o[1]) + 2 * tt * (q2[1] - q1[1]);
        var a = (Math.atan2(dy, dx) * 180) / Math.PI;
        leaf(g, [px, py], a + (i % 2 ? 42 : -42), 0.6 + JIT[(i + 3) % JIT.length] * 0.3, i === 2);
      }
      leaf(g, q2, (Math.atan2(q2[1] - q1[1], q2[0] - q1[0]) * 180) / Math.PI, 0.7, false);
    });
    return svg;
  }

  /* ---------- 5. Mưa, đom đóm, cỏ ---------- */
  function rainInto(host, count) {
    var box = div("rain", host);
    var h = host.clientHeight || 700;
    box.style.setProperty("--h", h + "px");
    for (var i = 0; i < count; i++) {
      var d = document.createElement("i");
      d.style.left = ((i * 97.3) % 100) + Math.random() * 3 + "%";
      d.style.height = 24 + Math.random() * 26 + "px";
      d.style.setProperty("--o", (0.22 + Math.random() * 0.28).toFixed(2));
      d.style.animationDuration = 3.4 + Math.random() * 3 + "s";
      d.style.animationDelay = "-" + (Math.random() * 6).toFixed(2) + "s";
      box.appendChild(d);
    }
  }
  function firefliesInto(host, spots) {
    var box = div("fireflies", host);
    spots.forEach(function (s, i) {
      var d = document.createElement("i");
      d.style.left = s[0] + "%";
      d.style.top = s[1] + "%";
      d.style.animationDelay = "-" + (i * 1.3).toFixed(1) + "s, -" + (i * 2.7).toFixed(1) + "s";
      d.style.animationDuration = 3.2 + (i % 3) * 0.7 + "s, " + (9 + (i % 4) * 2) + "s";
      box.appendChild(d);
    });
  }
  function grassInto(host) {
    var cs = getComputedStyle(host);
    var pt = parseFloat(cs.paddingTop) || 100;
    var box = div("grass", host);
    box.style.height = clamp(pt, 70, 110) + "px";
    var svg = el("svg", { viewBox: "0 0 1200 110", preserveAspectRatio: "none" }, box);
    var layers = [
      { fill: "#a9c1ab", op: 0.4, step: 17, h: 64, seed: 3 },
      { fill: "#6f8f7a", op: 0.34, step: 23, h: 78, seed: 7 },
      { fill: "#1b2b4b", op: 0.22, step: 31, h: 52, seed: 11 }
    ];
    layers.forEach(function (L) {
      var g = el("g", { class: "grass-layer" }, svg);
      var d = "";
      for (var x = -10, i = 0; x < 1210; x += L.step, i++) {
        var r = JIT[(i + L.seed) % JIT.length];
        var h = L.h * (0.55 + r * 0.7);
        var lean = (r - 0.5) * 34;
        d += "M" + x + " 110C" + (x + 2) + " " + (110 - h * 0.5) + " " + (x + lean * 0.6) + " " + (110 - h * 0.85) + " " + (x + lean) + " " + (110 - h) +
             "C" + (x + 6 + lean * 0.3) + " " + (110 - h * 0.6) + " " + (x + 8) + " " + (110 - h * 0.3) + " " + (x + 9) + " 110Z";
      }
      el("path", { d: d, fill: L.fill, "fill-opacity": L.op }, g);
    });
  }

  /* ---------- 6. Trang trí từng trang ---------- */
  function initDecor() {
    // bộ lọc mực cho dấu triện
    var defs = document.createElementNS(NS, "svg");
    defs.setAttribute("width", "0"); defs.setAttribute("height", "0");
    defs.setAttribute("style", "position:absolute"); defs.setAttribute("aria-hidden", "true");
    defs.innerHTML = '<filter id="ink-edge"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="2.4"/></filter>';
    document.body.appendChild(defs);

    var rainN = small ? 9 : 22;

    var hero = $(".hero");
    if (hero) {
      if (!reduce) { rainInto(hero, rainN); firefliesInto(hero, small ? [[12, 62], [86, 38]] : [[8, 58], [44, 82], [91, 30], [63, 12]]); }
      var photo = $(".hero-photo", hero);
      if (photo) {
        div("moon", photo);
        photo.appendChild(buildBranch());
      }
      watchPause(hero);
    }

    var auth = $(".auth-shell");
    if (auth) {
      div("moon", auth);
      auth.appendChild(buildBranch());
      if (!reduce) { rainInto(auth, rainN); firefliesInto(auth, [[14, 70], [84, 60], [50, 20]]); }
      watchPause(auth);
    }

    $$(".page-header").forEach(function (ph) {
      var deco = div("ph-deco", ph);
      div("moon", deco);
      deco.appendChild(buildBranch());
    });

    var cta = $(".cta-band");
    if (cta) {
      div("cta-moon", cta);
      cta.appendChild(buildBranch());
      var ticking = false;
      var upd = function () {
        var r = cta.getBoundingClientRect();
        var p = clamp((window.innerHeight - r.top) / (window.innerHeight * 0.9), 0, 1);
        cta.style.setProperty("--p", p.toFixed(3));
        ticking = false;
      };
      upd();
      if (!reduce) window.addEventListener("scroll", function () { if (!ticking) { ticking = true; requestAnimationFrame(upd); } }, { passive: true });
      else cta.style.setProperty("--p", 1);
    }

    var foot = $(".site-footer");
    if (foot) {
      grassInto(foot);
      if (!reduce) firefliesInto(foot, [[10, 12], [38, 30], [72, 18], [92, 40]]);
      watchPause(foot);
    }

    // dấu triện "Free sấy khô"
    $$(".promo-card .corner").forEach(function (c) { c.classList.add("stamp-ready"); });
  }

  function watchPause(host) {
    if (!("IntersectionObserver" in window)) return;
    new IntersectionObserver(function (es) {
      es.forEach(function (e) { host.classList.toggle("paused", !e.isIntersecting); });
    }).observe(host);
  }

  /* ---------- 7. Reveal khi cuộn tới ---------- */
  var REVEAL = [
    ".promo-card", ".pkg-card", ".order-card", ".stat-box", ".review-card", ".process-step",
    ".banner", ".section-head", ".device-section-head", ".form-card", ".dash-side",
    ".rating-summary", ".auth-card", ".page-header", ".cta-band",
    "table.orders tbody tr", ".info-list li", ".map-box"
  ];
  function initReveal() {
    var items = [];
    REVEAL.forEach(function (sel) {
      var seen = new Map();
      $$(sel).forEach(function (n) {
        var idx = seen.get(n.parentElement) || 0;
        seen.set(n.parentElement, idx + 1);
        n.style.setProperty("--i", Math.min(idx, 6));
        n.classList.add("rv");
        items.push(n);
      });
    });
    // chỉ số bước cho thanh tiến độ đơn hàng
    $$(".tracker").forEach(function (t) {
      $$(".tracker-step", t).forEach(function (s, i) { s.style.setProperty("--k", i); });
    });
    if (reduce || !("IntersectionObserver" in window)) {
      items.forEach(function (n) { n.classList.add("in"); });
      return;
    }
    // phần tử bị clip-path cắt về 0 thì IntersectionObserver coi như không thấy,
    // nên với nhóm này ta quan sát khối cha rồi bật .in cho các con.
    var CLIPPED = ".promo-card, .pkg-card, .order-card, .stat-box, .review-card, .map-box";
    var groups = new Map();
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        var kids = groups.get(e.target);
        (kids || [e.target]).forEach(function (k) { k.classList.add("in"); });
        io.unobserve(e.target);
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" });
    items.forEach(function (n) {
      if (n.matches(CLIPPED) && n.parentElement) {
        var p = n.parentElement;
        if (!groups.has(p)) { groups.set(p, []); io.observe(p); }
        groups.get(p).push(n);
      } else {
        io.observe(n);
      }
    });
  }

  /* ---------- 8. Số đếm lên + vòng trăng ---------- */
  function countUp(node) {
    var raw = node.textContent.trim();
    var m = raw.match(/^([\d.,]+)(.*)$/);
    if (!m) return;
    var numStr = m[1], suffix = m[2];
    var dec = (numStr.split(".")[1] || "").length;
    var target = parseFloat(numStr.replace(",", "."));
    if (isNaN(target) || reduce) return;
    var dur = 1200, start = null;
    node.textContent = (0).toFixed(dec) + suffix;
    function step(ts) {
      if (start === null) start = ts;
      var p = clamp((ts - start) / dur, 0, 1);
      var e = 1 - Math.pow(1 - p, 3);
      node.textContent = (target * e).toFixed(dec) + suffix;
      if (p < 1) requestAnimationFrame(step);
      else node.textContent = raw;
    }
    requestAnimationFrame(step);
  }
  function initCounters() {
    var nodes = $$(".rating-summary .num, .stat-box .n");
    if (!nodes.length) return;

    // vòng trăng quanh điểm đánh giá
    $$(".rating-summary").forEach(function (rs) {
      var num = $(".num", rs);
      if (!num) return;
      var w = document.createElement("div");
      w.className = "moon-wrap";
      w.setAttribute("style", "position:relative;width:96px;height:96px;display:grid;place-items:center;flex-shrink:0");
      var ring = el("svg", { class: "moon-ring", viewBox: "0 0 100 100", "aria-hidden": "true" });
      ring.setAttribute("style", "position:absolute;inset:0;width:100%;height:100%");
      el("circle", { class: "ring-bg", cx: 50, cy: 50, r: 44 }, ring);
      el("circle", { class: "ring-fg", cx: 50, cy: 50, r: 44, pathLength: 1 }, ring);
      rs.insertBefore(w, num);
      w.appendChild(ring);
      w.appendChild(num);
      num.style.fontSize = "2.1rem";
      num.style.position = "relative";
    });

    if (!("IntersectionObserver" in window)) return;
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        countUp(e.target);
        io.unobserve(e.target);
      });
    }, { threshold: 0.6 });
    nodes.forEach(function (n) { io.observe(n); });
  }

  /* ---------- 9. Quy trình: dây leo mọc theo cuộn ---------- */
  function initVine() {
    var track = $(".process-track");
    if (!track) return;
    var steps = $$(".process-step", track);
    var nums = steps.map(function (s) { return $(".num", s); });
    var svg = el("svg", { class: "vine", "aria-hidden": "true" });
    track.insertBefore(svg, track.firstChild);
    var ghost = el("path", { class: "vine-ghost" }, svg);
    var live = el("path", { class: "vine-live" }, svg);
    var dot = el("circle", { class: "firefly-dot", r: 5 }, svg);
    var len = 0, single = false;

    function layout() {
      var tr = track.getBoundingClientRect();
      var pts = nums.map(function (n) {
        var r = n.getBoundingClientRect();
        return [r.left - tr.left + r.width / 2, r.top - tr.top + r.height / 2];
      });
      var ys = pts.map(function (p) { return p[1]; });
      single = Math.max.apply(null, ys) - Math.min.apply(null, ys) < 70;
      svg.style.display = single ? "" : "none";
      if (!single) return;
      svg.setAttribute("viewBox", "0 0 " + tr.width + " " + tr.height);
      var d = "M" + pts[0][0] + " " + pts[0][1];
      for (var i = 1; i < pts.length; i++) {
        var x0 = pts[i - 1][0], y0 = pts[i - 1][1], x1 = pts[i][0], y1 = pts[i][1];
        var dx = x1 - x0;
        d += " C" + (x0 + dx * 0.3) + " " + (y0 - 46) + " " + (x1 - dx * 0.3) + " " + (y1 - 46) + " " + x1 + " " + y1;
      }
      ghost.setAttribute("d", d);
      live.setAttribute("d", d);
      len = live.getTotalLength();
      live.style.strokeDasharray = len;
      render();
    }
    function progress() {
      var r = track.getBoundingClientRect();
      return reduce ? 1 : clamp((window.innerHeight * 0.88 - r.top) / (window.innerHeight * 0.5), 0, 1);
    }
    function render() {
      var p = progress();
      steps.forEach(function (s, i) {
        var at = steps.length > 1 ? i / (steps.length - 1) : 0;
        s.classList.toggle("bloom", p >= at - 0.02 && p > 0.02);
      });
      if (!single) return;
      live.style.strokeDashoffset = len * (1 - p);
      var pt = live.getPointAtLength(len * p);
      dot.setAttribute("cx", pt.x);
      dot.setAttribute("cy", pt.y);
      dot.style.opacity = p > 0.02 && p < 0.995 ? 1 : 0;
    }
    var ticking = false;
    window.addEventListener("scroll", function () {
      if (!ticking) { ticking = true; requestAnimationFrame(function () { render(); ticking = false; }); }
    }, { passive: true });
    var rt;
    window.addEventListener("resize", function () { clearTimeout(rt); rt = setTimeout(layout, 150); });
    // chờ font + ảnh ổn định rồi mới đo
    (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(function () { layout(); setTimeout(layout, 400); });
    layout();
  }

  /* ---------- Khởi động ---------- */
  function init() {
    initThemeToggle();
    initHeader();
    initRipple();
    initDecor();
    initReveal();
    initCounters();
    initVine();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
