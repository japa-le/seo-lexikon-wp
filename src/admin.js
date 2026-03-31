import Alpine from "alpinejs";

(function () {
  "use strict";

  Alpine.data("lmSnippetStatus", () => ({
    searchTerm: "",
    matches(value) {
      const haystack = String(value || "").toLowerCase();
      const needle = this.searchTerm.trim().toLowerCase();
      if (!needle) return true;
      return haystack.includes(needle);
    },
  }));

  Alpine.data("lmLexikonMetaHelper", (initialKurzdefinition = "") => ({
    kurzdefinition: initialKurzdefinition,
    showHints: false,
  }));

  window.Alpine = Alpine;

  document.addEventListener("DOMContentLoaded", function () {
    Alpine.start();
  });
})();
