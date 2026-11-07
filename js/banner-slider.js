/* TechFix — Hero promo slider at the top of the home page.
   Slide timing is driven by the CSS fill animation on the active pagination dot:
   when the progress bar animation ends, the slider advances to the next slide.
   Pausing (on hover, focus, off-screen, or tab switch) simply pauses that CSS animation.
   Respects prefers-reduced-motion: disables auto-advance and slide transitions while keeping manual controls active. */
(function () {
  "use strict";

  function initSlider(root) {
    var track = root.querySelector(".slider-track");
    var slides = Array.prototype.slice.call(track.children);
    var dotsHost = root.querySelector(".slider-dots");
    var count = slides.length;
    if (count < 2) return;

    var interval = parseInt(root.dataset.interval, 10) || 5000;
    root.style.setProperty("--slide-interval", interval + "ms");

    // Clone the first slide at the end of the track for seamless infinite forward looping
    var clone = slides[0].cloneNode(true);
    clone.setAttribute("aria-hidden", "true");
    clone.inert = true;
    clone.classList.add("is-clone");
    track.appendChild(clone);

    var dots = slides.map(function (_, i) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "slider-dot";
      b.setAttribute("aria-label", "Xem ảnh " + (i + 1));
      b.innerHTML = '<span class="fill"></span>';
      b.addEventListener("click", function () {
        goTo(i);
      });
      dotsHost.appendChild(b);
      return b;
    });

    var index = 0;
    var pauseReasons = {};
    // Transparent overlay header: adapt text contrast based on slide brightness (data-tone)
    var overlayHeader = document.querySelector(".header-overlay");

    function setPosition(i, animate) {
      track.classList.toggle("no-anim", !animate);
      track.style.transform = "translate3d(" + i * -100 + "%,0,0)";
      if (!animate) void track.offsetWidth; // Force reflow before re-enabling CSS transitions
    }

    function markActive() {
      var real = index % count;
      slides.forEach(function (s, i) {
        s.classList.toggle("is-active", i === real);
        // Mark off-screen slides as inert so inner buttons are excluded from tab order and screen readers
        s.inert = i !== real;
      });
      clone.classList.toggle("is-active", index === count);
      if (overlayHeader) {
        overlayHeader.classList.toggle(
          "on-light",
          slides[real].dataset.tone === "light",
        );
      }
      dots.forEach(function (d, i) {
        var on = i === real;
        d.classList.toggle("active", on);
        d.setAttribute("aria-current", on ? "true" : "false");
      });
    }

    function goTo(i) {
      index = i;
      setPosition(index, true);
      markActive();
    }

    function next() {
      // If currently resting on the trailing clone, snap back to slide 0 before advancing
      if (index >= count) {
        index = 0;
        setPosition(0, false);
      }
      goTo(index + 1);
    }

    function prev() {
      if (index <= 0) {
        index = count;
        setPosition(count, false);
      }
      goTo(index - 1);
    }

    track.addEventListener("transitionend", function (e) {
      if (e.target !== track || index !== count) return;
      index = 0;
      setPosition(0, false);
      markActive();
    });

    // Advance to the next slide when the active dot's progress fill completes
    dotsHost.addEventListener("animationend", function (e) {
      if (e.target.classList.contains("fill")) next();
    });

    function setPaused(reason, on) {
      if (on) pauseReasons[reason] = true;
      else delete pauseReasons[reason];
      root.classList.toggle("is-paused", Object.keys(pauseReasons).length > 0);
    }

    root.querySelector(".prev").addEventListener("click", prev);
    root.querySelector(".next").addEventListener("click", next);

    root.addEventListener("mouseenter", function () {
      setPaused("hover", true);
    });
    root.addEventListener("mouseleave", function () {
      setPaused("hover", false);
    });
    root.addEventListener("focusin", function () {
      setPaused("focus", true);
    });
    root.addEventListener("focusout", function (e) {
      if (!root.contains(e.relatedTarget)) setPaused("focus", false);
    });
    root.addEventListener("keydown", function (e) {
      if (e.key === "ArrowRight") next();
      else if (e.key === "ArrowLeft") prev();
    });

    document.addEventListener("visibilitychange", function () {
      setPaused("hidden", document.hidden);
    });
    if ("IntersectionObserver" in window) {
      new IntersectionObserver(
        function (entries) {
          setPaused("offscreen", !entries[0].isIntersecting);
        },
        { threshold: 0.25 },
      ).observe(root);
    }

    // Touch swipe support on mobile devices
    var startX = null;
    var startY = 0;
    track.addEventListener(
      "touchstart",
      function (e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        setPaused("touch", true);
      },
      { passive: true },
    );
    track.addEventListener("touchend", function (e) {
      setPaused("touch", false);
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX;
      var dy = e.changedTouches[0].clientY - startY;
      startX = null;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
        if (dx < 0) next();
        else prev();
      }
    });

    // Set initial position without .no-anim so the first slide's headline reveal animation plays
    track.style.transform = "translate3d(0,0,0)";
    markActive();
    root.classList.add("is-ready");
  }

  function init() {
    document.querySelectorAll(".promo-slider").forEach(initSlider);
  }

  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", init);
  else init();
})();
