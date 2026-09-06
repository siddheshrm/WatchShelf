const themeToggle = document.querySelector(".theme-toggle");

if (themeToggle) {
  const updateToggleState = () => {
    const isDark = document.documentElement.dataset.theme === "dark";

    themeToggle.setAttribute("aria-pressed", String(isDark));

    const label = isDark ? "Switch to light theme" : "Switch to dark theme";

    themeToggle.setAttribute("aria-label", label);
    themeToggle.setAttribute("title", label);
  };

  updateToggleState();

  themeToggle.addEventListener("click", () => {
    const currentTheme =
      document.documentElement.dataset.theme === "dark" ? "dark" : "light";

    const newTheme = currentTheme === "dark" ? "light" : "dark";

    document.documentElement.dataset.theme = newTheme;

    try {
      localStorage.setItem("watchshelf-theme", newTheme);
    } catch (error) {
      // Theme still works for the current page if storage is unavailable.
    }

    updateToggleState();
  });
}
