document.addEventListener("click", (e) => {
  if (!e.target.classList.contains("open-url")) {
    return;
  }

  const input = e.target.previousElementSibling;
  const url = input.value.trim();

  if (!url) {
    return;
  }

  window.open(url, "_blank", "noopener,noreferrer");
});
