// Filter group Show More / Show Less
document
  .querySelectorAll(".filter-group.filter-group-collapsible")
  .forEach((group) => {
    const options = [...group.querySelectorAll(".filter-option")];
    const toggle = group.querySelector(".filter-toggle");

    const visibleCount = 5;

    if (options.length <= visibleCount) {
      return;
    }

    function collapse() {
      let hiddenCount = 0;

      options.forEach((option, index) => {
        const checked = option.querySelector("input")?.checked;

        if (index < visibleCount || checked) {
          option.classList.remove("hidden-option");
        } else {
          option.classList.add("hidden-option");
          hiddenCount++;
        }
      });

      group.classList.remove("expanded");
      toggle.textContent = `Show ${hiddenCount} more option${hiddenCount === 1 ? "" : "s"}`;
    }

    function expand() {
      options.forEach((option) => {
        option.classList.remove("hidden-option");
      });

      group.classList.add("expanded");
      toggle.textContent = "Show less";
    }

    collapse();

    if (toggle.textContent === "Show 0 more") {
      toggle.hidden = true;
      return;
    }

    toggle.hidden = false;

    toggle.addEventListener("click", () => {
      if (group.classList.contains("expanded")) {
        collapse();
      } else {
        expand();
      }
    });
  });

// Tablet / Mobile Off-Canvas Filters
const filterOpenButton = document.querySelector(".filter-open-button");
const filterCloseButton = document.querySelector(".filter-close-button");
const filterSidebar = document.querySelector(".filter-sidebar");
const filterBackdrop = document.querySelector(".filter-backdrop");

if (filterOpenButton && filterCloseButton && filterSidebar && filterBackdrop) {
  const openFilters = () => {
    filterSidebar.classList.add("is-open");
    filterBackdrop.classList.add("is-visible");

    filterOpenButton.setAttribute("aria-expanded", "true");

    document.body.style.overflow = "hidden";

    filterCloseButton.focus();
  };

  const closeFilters = () => {
    filterSidebar.classList.remove("is-open");
    filterBackdrop.classList.remove("is-visible");

    filterOpenButton.setAttribute("aria-expanded", "false");

    document.body.style.overflow = "";

    filterOpenButton.focus();
  };

  filterOpenButton.addEventListener("click", openFilters);
  filterCloseButton.addEventListener("click", closeFilters);
  filterBackdrop.addEventListener("click", closeFilters);

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && filterSidebar.classList.contains("is-open")) {
      closeFilters();
    }
  });
}
