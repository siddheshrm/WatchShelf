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
