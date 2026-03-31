(function () {
  "use strict";

  function initLexikonVideoPlaybackBinding() {
  if (window.__lmVideoPlaybackBound) return;
  window.__lmVideoPlaybackBound = true;

  document.addEventListener("click", function (event) {
    // Nur noch Shortcode-Videos — Block-Videos laufen über die lm-lightbox
    const button = event.target.closest(".lexikon-video-btn");
    if (!button) return;

    const wrapper = button.closest(".lexikon-video");
    if (!wrapper) return;

    const videoUrl = (button.dataset.videoUrl || "").trim();
    if (!videoUrl) return;

    event.preventDefault();

    wrapper.innerHTML =
      '<video src="' +
      videoUrl +
      '" controls autoplay playsinline webkit-playsinline preload="metadata"></video>';
  });
}

  function triggerExternalLexikonSearch(term, targetSelector, tabLetter) {
    const query = (term || "").trim();
    if (!query) return;

    const root =
      (targetSelector && document.querySelector(targetSelector)) ||
      document.querySelector(".lm-lexikon");
    if (!root) return;

    const searchInput = root.querySelector("#lm-lexikon-search");
    if (!searchInput) return;

    searchInput.value = query;
    searchInput.dispatchEvent(new Event("input", { bubbles: true }));
    searchInput.focus();

    const normalizedLetter = (tabLetter || "").trim().toUpperCase();
    if (normalizedLetter) {
      // Erst Suchfeld befüllen/filtern, danach zum gewünschten Tab springen.
      requestAnimationFrame(function () {
        const targetTab = root.querySelector(
          '.lm-lexikon-tab[data-letter="' + normalizedLetter + '"]',
        );
        if (targetTab) {
          targetTab.click();
          targetTab.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
        }
      });
    }
  }

  function initJumpToSearchBinding() {
    if (window.__lmJumpToSearchBound) return;
    window.__lmJumpToSearchBound = true;

    document.addEventListener("click", function (event) {
      const trigger = event.target.closest('[id^="jump-to-tab-"]');
      if (!trigger) return;

      event.preventDefault();

      const term =
        trigger.dataset.lexikonTerm ||
        trigger.dataset.term ||
        trigger.textContent ||
        "";
      const targetSelector = trigger.dataset.lexikonTarget || "";
      const tabLetter =
        trigger.dataset.lexikonTab ||
        (trigger.id && trigger.id.startsWith("jump-to-tab-")
          ? trigger.id.replace("jump-to-tab-", "")
          : "");

      triggerExternalLexikonSearch(term, targetSelector, tabLetter);
    });
  }

  function initLexikonInstance(root) {
    if (!root) return;

    const searchInput = root.querySelector("#lm-lexikon-search");
    const tabsWrap = root.querySelector(".lm-lexikon-tabs");
    const tabButtons = Array.from(root.querySelectorAll(".lm-lexikon-tab"));
    const groups = Array.from(root.querySelectorAll(".lm-lexikon-group"));

    function initMobileTabsToggle() {
      if (!tabsWrap || !tabButtons.length) return;
      if (window.innerWidth > 767) return;
      if (root.dataset.mobileTabsToggleInit === "1") return;

      root.dataset.mobileTabsToggleInit = "1";
      root.classList.add("lm-mobile-tabs-ready");

      const toggleButton = document.createElement("button");
      toggleButton.type = "button";
      toggleButton.className = "lm-mobile-tabs-toggle";
      toggleButton.setAttribute("aria-label", "ABC-Navigation öffnen");
      toggleButton.setAttribute("aria-expanded", "false");
      toggleButton.textContent = "ABC";
      root.appendChild(toggleButton);

      function setOpenState(isOpen) {
        root.classList.toggle("lm-mobile-tabs-open", isOpen);
        toggleButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
      }

      toggleButton.addEventListener("click", function () {
        const isOpen = root.classList.contains("lm-mobile-tabs-open");
        setOpenState(!isOpen);
      });

      tabButtons.forEach((btn) => {
        btn.addEventListener("click", function () {
          setOpenState(false);
        });
      });
    }

    function applyHighlight(element, query) {
      if (!element) return;

      if (!element.dataset.rawHtml) {
        element.dataset.rawHtml = element.innerHTML;
      }

      // Always reset first so highlights don't accumulate.
      element.innerHTML = element.dataset.rawHtml;

      if (!query) return;

      const escaped = query.replace(/[-\/\\^$*+?.()|[\]{}]/g, "\\$&");
      const re = new RegExp(escaped, "gi");

      const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
      const textNodes = [];

      while (walker.nextNode()) {
        textNodes.push(walker.currentNode);
      }

      textNodes.forEach((node) => {
        const text = node.nodeValue;
        if (!text || !re.test(text)) return;

        re.lastIndex = 0;
        const frag = document.createDocumentFragment();
        let lastIndex = 0;
        let match;

        while ((match = re.exec(text)) !== null) {
          if (match.index > lastIndex) {
            frag.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
          }

          const mark = document.createElement("mark");
          mark.textContent = match[0];
          frag.appendChild(mark);
          lastIndex = re.lastIndex;
        }

        if (lastIndex < text.length) {
          frag.appendChild(document.createTextNode(text.slice(lastIndex)));
        }

        node.parentNode.replaceChild(frag, node);
      });
    }

    function filterEntries(query) {
      const q = query.trim().toLowerCase();
      groups.forEach((group) => {
        let anyVisible = false;
        const entries = Array.from(group.querySelectorAll(".lm-entry"));
        entries.forEach((entry) => {
          const text = entry.textContent.toLowerCase();
          const visible = q === "" || text.indexOf(q) !== -1;
          entry.style.display = visible ? "" : "none";

          const title = entry.querySelector(".lm-entry-title");
          applyHighlight(title, q);

          const snippet = entry.querySelector(".lm-entry-snippet");
          if (snippet) {
            applyHighlight(snippet, q);
            snippet.style.display = q !== "" && visible ? "" : "none";
          }

          const content = entry.querySelector(".lm-entry-content");
          applyHighlight(content, q);

          if (visible) {
            anyVisible = true;
          }
        });
        group.style.display = anyVisible ? "" : "none";
      });
    }

    function setActiveTab(letter) {
      tabButtons.forEach((btn) => {
        const isActive = btn.dataset.letter === letter;
        btn.classList.toggle("is-active", isActive);
      });
      groups.forEach((group) => {
        group.style.display = group.dataset.letter === letter ? "" : "none";
      });
    }

    if (tabButtons.length && groups.length) {
      tabButtons.forEach((btn) => {
        btn.addEventListener("click", function () {
          setActiveTab(this.dataset.letter);
        });
      });
      setActiveTab(tabButtons[0].dataset.letter);
    }

    if (searchInput) {
      searchInput.addEventListener("input", function () {
        filterEntries(this.value);
      });
    }

    initMobileTabsToggle();

    if (window.innerWidth <= 768) {
      groups.forEach((group) => {
        group.querySelectorAll(".lm-entry").forEach((entry) => {
          const title = entry.querySelector(".lm-entry-title");
          if (title) {
            title.style.cursor = "pointer";
            title.addEventListener("click", function () {
              const content = entry.querySelector(".lm-entry-content");
              if (content) {
                content.style.display =
                  content.style.display === "none" ? "" : "none";
              }
            });
          }
        });
      });
    }
  }

  function initLexikon() {
    initLexikonVideoPlaybackBinding();

    const roots = document.querySelectorAll(".lm-lexikon");
    if (!roots.length) return;

    initJumpToSearchBinding();

    roots.forEach((root) => {
      initLexikonInstance(root);
    });
  }

  document.addEventListener("DOMContentLoaded", initLexikon);
})();
