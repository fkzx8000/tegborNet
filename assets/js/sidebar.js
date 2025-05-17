(function () {
  const sidebar = document.querySelector(".sidebar");
  const sidebarToggle = document.querySelector(".sidebar-toggle");
  const content = document.querySelector(".content");
  const body = document.querySelector("body");

  function toggleSidebar() {
    if (window.innerWidth <= 768) {
      sidebar.classList.toggle("active");

      if (sidebar.classList.contains("active")) {
        createOverlay();
      } else {
        removeOverlay();
      }
    } else {
      body.classList.toggle("sidebar-collapsed");

      if (body.classList.contains("sidebar-collapsed")) {
        sidebarToggle.innerHTML = '<i class="material-icons">menu</i>';
      } else {
        sidebarToggle.innerHTML = '<i class="material-icons">chevron_right</i>';
      }
    }
  }

  function createOverlay() {
    removeOverlay();

    const overlay = document.createElement("div");
    overlay.className = "sidebar-overlay";
    overlay.style.position = "fixed";
    overlay.style.top = "0";
    overlay.style.left = "0";
    overlay.style.right = "0";
    overlay.style.bottom = "0";
    overlay.style.backgroundColor = "rgba(0, 0, 0, 0.5)";
    overlay.style.zIndex = "999";
    overlay.style.transition = "opacity 0.3s ease";

    overlay.addEventListener("click", function () {
      sidebar.classList.remove("active");
      removeOverlay();
    });

    document.body.appendChild(overlay);

    setTimeout(() => {
      overlay.style.opacity = "1";
    }, 10);
  }

  function removeOverlay() {
    const overlay = document.querySelector(".sidebar-overlay");
    if (overlay) {
      overlay.style.opacity = "0";

      setTimeout(() => {
        document.body.removeChild(overlay);
      }, 300);
    }
  }

  function checkScreenSize() {
    if (window.innerWidth <= 768) {
      body.classList.remove("sidebar-collapsed");
      sidebar.classList.remove("active");
      removeOverlay();

      if (sidebarToggle) {
        sidebarToggle.innerHTML = '<i class="material-icons">menu</i>';
      }
    } else {
      sidebar.classList.remove("active");
      removeOverlay();

      if (sidebarToggle) {
        if (body.classList.contains("sidebar-collapsed")) {
          sidebarToggle.innerHTML = '<i class="material-icons">menu</i>';
        } else {
          sidebarToggle.innerHTML =
            '<i class="material-icons">chevron_right</i>';
        }
      }
    }
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", toggleSidebar);
  }

  window.addEventListener("resize", checkScreenSize);

  document.addEventListener("DOMContentLoaded", function () {
    if (!sidebarToggle && sidebar) {
      const newToggle = document.createElement("button");
      newToggle.className = "sidebar-toggle";
      newToggle.innerHTML = '<i class="material-icons">menu</i>';
      document.body.appendChild(newToggle);

      newToggle.addEventListener("click", toggleSidebar);
    }

    checkScreenSize();
  });

  const sidebarLinks = document.querySelectorAll(".sidebar .nav-link");
  sidebarLinks.forEach((link) => {
    link.addEventListener("click", function () {
      if (window.innerWidth <= 768) {
        sidebar.classList.remove("active");
        removeOverlay();
      }
    });
  });
})();
