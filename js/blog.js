/* Blog article: copy-link button. */
(function () {
  "use strict";
  var btn = document.querySelector("[data-copy-link]");
  if (!btn) return;
  var label = btn.querySelector("[data-copy-label]");
  btn.addEventListener("click", function () {
    var url = window.location.href.split("#")[0];
    var done = function () {
      label.textContent = "Link copied";
      btn.classList.add("is-copied");
      setTimeout(function () { label.textContent = "Copy link"; btn.classList.remove("is-copied"); }, 2000);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(url).then(done, function () { window.prompt("Copy this link", url); });
    } else {
      window.prompt("Copy this link", url);
    }
  });
})();
