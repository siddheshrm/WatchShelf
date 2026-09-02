const container = document.querySelector(".related-grid");
const leftButton = document.querySelector(".carousel-btn-left");
const rightButton = document.querySelector(".carousel-btn-right");

if (container && leftButton && rightButton) {
  const scrollAmount = 300;

  function updateButtons() {
    const maxScrollLeft = container.scrollWidth - container.clientWidth;

    leftButton.disabled = container.scrollLeft <= 1;
    rightButton.disabled = container.scrollLeft >= maxScrollLeft - 1;
  }

  leftButton.addEventListener("click", () => {
    container.scrollBy({
      left: -scrollAmount,
      behavior: "smooth",
    });
  });

  rightButton.addEventListener("click", () => {
    container.scrollBy({
      left: scrollAmount,
      behavior: "smooth",
    });
  });

  container.addEventListener("scroll", updateButtons);

  updateButtons();
}
