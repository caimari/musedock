(function () {
  "use strict";

  // ======= Sticky Header
  window.onscroll = function () {
    const ud_header = document.querySelector(".ud-header");

    if (!ud_header) return;

    if (window.pageYOffset > 50) {
      ud_header.classList.add("sticky");
    } else {
      ud_header.classList.remove("sticky");
    }

    // show or hide the back-to-top button
    const backToTop = document.querySelector(".back-to-top");
    if (backToTop) {
      if (
        document.body.scrollTop > 50 ||
        document.documentElement.scrollTop > 50
      ) {
        backToTop.style.display = "flex";
      } else {
        backToTop.style.display = "none";
      }
    }
  };

  // ===== Mobile Menu Toggle
  const navbarToggler = document.querySelector(".navbar-toggler");
  const navbarCollapse = document.querySelector(".navbar-collapse");

  if (navbarToggler && navbarCollapse) {
    navbarToggler.addEventListener("click", function () {
      navbarToggler.classList.toggle("active");
      navbarCollapse.classList.toggle("show");
    });

    // Close menu when clicking outside
    document.addEventListener("click", function (e) {
      if (!navbarToggler.contains(e.target) && !navbarCollapse.contains(e.target)) {
        navbarToggler.classList.remove("active");
        navbarCollapse.classList.remove("show");
      }
    });
  }

  // ===== Close navbar when menu item is clicked (for scroll links)
  document.querySelectorAll(".ud-menu-scroll").forEach((e) =>
    e.addEventListener("click", () => {
      if (navbarToggler && navbarCollapse) {
        navbarToggler.classList.remove("active");
        navbarCollapse.classList.remove("show");
      }
    })
  );

  // ===== Submenu Accordion (Mobile) & Hover (Desktop)
  const submenuItems = document.querySelectorAll(".nav-item-has-children");

  submenuItems.forEach((item) => {
    const link = item.querySelector("a");
    const submenu = item.querySelector(".ud-submenu");

    if (link && submenu) {
      link.addEventListener("click", function(e) {
        // Only prevent default and toggle on mobile
        if (window.innerWidth < 992) {
          e.preventDefault();

          // Close other open submenus
          submenuItems.forEach((otherItem) => {
            if (otherItem !== item && otherItem.classList.contains("open")) {
              otherItem.classList.remove("open");
            }
          });

          // Toggle current submenu
          item.classList.toggle("open");
        }
      });
    }
  });

  // ===== Desktop: Show submenu on hover
  if (window.innerWidth >= 992) {
    submenuItems.forEach((item) => {
      item.addEventListener("mouseenter", function() {
        const submenu = item.querySelector(".ud-submenu");
        if (submenu) {
          submenu.classList.add("show");
        }
      });

      item.addEventListener("mouseleave", function() {
        const submenu = item.querySelector(".ud-submenu");
        if (submenu) {
          submenu.classList.remove("show");
        }
      });
    });
  }

  // ===== WOW.js Initialization
  if (typeof WOW !== 'undefined') {
    new WOW().init();
  }

  // ====== Scroll to Top
  function scrollTo(element, to = 0, duration = 500) {
    const start = element.scrollTop;
    const change = to - start;
    const increment = 20;
    let currentTime = 0;

    const animateScroll = () => {
      currentTime += increment;
      const val = Math.easeInOutQuad(currentTime, start, change, duration);
      element.scrollTop = val;

      if (currentTime < duration) {
        setTimeout(animateScroll, increment);
      }
    };

    animateScroll();
  }

  Math.easeInOutQuad = function (t, b, c, d) {
    t /= d / 2;
    if (t < 1) return (c / 2) * t * t + b;
    t--;
    return (-c / 2) * (t * (t - 2) - 1) + b;
  };

  const backToTopBtn = document.querySelector(".back-to-top");
  if (backToTopBtn) {
    backToTopBtn.onclick = () => {
      scrollTo(document.documentElement);
    };
  }

  // ===== Handle window resize for submenu behavior
  window.addEventListener("resize", function() {
    // Close mobile menu on resize to desktop
    if (window.innerWidth >= 992) {
      if (navbarToggler && navbarCollapse) {
        navbarToggler.classList.remove("active");
        navbarCollapse.classList.remove("show");
      }
      // Close all open submenus
      submenuItems.forEach((item) => {
        item.classList.remove("open");
      });
    }
  });

  // ===== Nice selects (language selector)
  const initNiceSelects = () => {
    if (typeof Choices === "undefined") return;

    const selects = document.querySelectorAll(
      "select.lang-select, select.mobile-lang-select"
    );

    selects.forEach((select) => {
      if (select.dataset.choicesInitialized === "1") return;
      select.dataset.choicesInitialized = "1";

      try {
        new Choices(select, {
          allowHTML: false,
          searchEnabled: false,
          shouldSort: false,
          itemSelectText: "",
          position: "bottom",
        });
      } catch (e) {
        // If Choices fails, keep native select
        select.dataset.choicesInitialized = "0";
      }
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initNiceSelects);
  } else {
    initNiceSelects();
  }
})();
