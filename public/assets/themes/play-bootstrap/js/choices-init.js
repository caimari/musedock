(() => {
  "use strict";

  const init = (root = document) => {
    if (typeof window.Choices === "undefined") return;

    const selects = root.querySelectorAll(
      "select.lang-select, select.mobile-lang-select"
    );

    selects.forEach((select) => {
      // Already converted (Choices wraps with a .choices container)
      if (select.closest(".choices")) return;
      if (select.dataset.choicesInitialized === "1") return;

      try {
        select.dataset.choicesInitialized = "1";
        // eslint-disable-next-line no-new
        new window.Choices(select, {
          allowHTML: false,
          searchEnabled: false,
          shouldSort: false,
          itemSelectText: "",
          position: "bottom",
        });
      } catch (e) {
        select.dataset.choicesInitialized = "0";
      }
    });
  };

  const initSoon = () => init(document);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSoon);
  } else {
    initSoon();
  }

  window.addEventListener("load", () => {
    initSoon();
    setTimeout(initSoon, 250);
  });
})();

