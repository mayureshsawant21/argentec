/* Projects hub: filter cards by stage and location. Choices are kept in the
   URL (?stage=completed&region=western) so a filtered view can be shared. */
(function () {
  "use strict";

  var grid = document.getElementById("pj-grid");
  if (!grid) return;
  var cards = Array.prototype.slice.call(grid.querySelectorAll(".pj-card"));
  var countOut = document.getElementById("pj-count");
  var empty = document.getElementById("pj-empty");
  var stageBtns = Array.prototype.slice.call(document.querySelectorAll("[data-filter-stage]"));
  var regionBtns = Array.prototype.slice.call(document.querySelectorAll("[data-filter-region]"));

  var params = new URLSearchParams(window.location.search);
  var state = { stage: params.get("stage") || "all", region: params.get("region") || "all" };

  function valid(btns, attr, value) {
    return btns.some(function (b) { return b.getAttribute(attr) === value; }) ? value : "all";
  }
  state.stage = valid(stageBtns, "data-filter-stage", state.stage);
  state.region = valid(regionBtns, "data-filter-region", state.region);

  function render(updateUrl) {
    var shown = 0;
    cards.forEach(function (c) {
      var ok = (state.stage === "all" || c.getAttribute("data-stage") === state.stage) &&
               (state.region === "all" || c.getAttribute("data-region") === state.region);
      c.hidden = !ok;
      if (ok) { shown++; c.classList.add("is-in"); }
    });
    countOut.textContent = String(shown);
    empty.hidden = shown !== 0;
    stageBtns.forEach(function (b) {
      var on = b.getAttribute("data-filter-stage") === state.stage;
      b.classList.toggle("is-active", on);
      b.setAttribute("aria-pressed", on ? "true" : "false");
    });
    regionBtns.forEach(function (b) {
      var on = b.getAttribute("data-filter-region") === state.region;
      b.classList.toggle("is-active", on);
      b.setAttribute("aria-pressed", on ? "true" : "false");
    });
    if (updateUrl && window.history && history.replaceState) {
      var q = new URLSearchParams();
      if (state.stage !== "all") q.set("stage", state.stage);
      if (state.region !== "all") q.set("region", state.region);
      var qs = q.toString();
      history.replaceState(null, "", window.location.pathname + (qs ? "?" + qs : ""));
    }
  }

  stageBtns.forEach(function (b) {
    b.addEventListener("click", function () { state.stage = b.getAttribute("data-filter-stage"); render(true); });
  });
  regionBtns.forEach(function (b) {
    b.addEventListener("click", function () { state.region = b.getAttribute("data-filter-region"); render(true); });
  });
  render(false);
})();
