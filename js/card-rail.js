/* TechFix — Horizontal card carousel ("Phone Repair" section on the home page).
   Mouse wheel, touch, and keyboard scrolling use native CSS scroll-snap;
   JS wires up the prev/next buttons and disables them at the start/end bounds. */
(function () {
  "use strict";
  var behavior = window.matchMedia("(prefers-reduced-motion: reduce)").matches
    ? "auto"
    : "smooth";

  function initRail(section) {
    var rail = section.querySelector(".card-rail");
    var prev = section.querySelector("[data-rail-prev]");
    var next = section.querySelector("[data-rail-next]");
    if (!rail || !prev || !next) return;

    function step() {
      var card = rail.querySelector(".rail-card");
      var gap = parseFloat(getComputedStyle(rail).columnGap) || 0;
      return card ? card.getBoundingClientRect().width + gap : rail.clientWidth;
    }

    function updateButtons() {
      var max = rail.scrollWidth - rail.clientWidth - 2;
      prev.disabled = rail.scrollLeft <= 2;
      next.disabled = rail.scrollLeft >= max;
      // Hide navigation buttons completely if all cards fit within the container
      section.classList.toggle("rail-fits", max <= 0);
    }

    prev.addEventListener("click", function () {
      rail.scrollBy({ left: -step(), behavior: behavior });
    });
    next.addEventListener("click", function () {
      rail.scrollBy({ left: step(), behavior: behavior });
    });
    rail.addEventListener("scroll", updateButtons, { passive: true });
    window.addEventListener("resize", updateButtons);
    updateButtons();
  }

  function init() {
    document.querySelectorAll(".card-rail").forEach(function (rail) {
      initRail(rail.closest("section") || rail.parentElement);
    });
  }

  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", init);
  else init();
})();
