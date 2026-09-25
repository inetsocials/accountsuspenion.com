/* Applies the saved color theme before first paint. */
(function () {
  var t = "auto";
  try { t = localStorage.getItem("ascv-theme") || "auto"; } catch (e) { /* storage blocked */ }
  if (t !== "light" && t !== "dark") { t = "auto"; }
  document.documentElement.setAttribute("data-theme", t);
})();
