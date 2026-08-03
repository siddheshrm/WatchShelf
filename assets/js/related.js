const container = document.querySelector(".related-grid");
const leftButton = document.querySelector(".carousel-btn-left");
const rightButton = document.querySelector(".carousel-btn-right");

if (container && leftButton && rightButton) {
  leftButton.addEventListener("click", () => {
    container.scrollBy({ left: -300, behavior: "smooth" });
  });

  rightButton.addEventListener("click", () => {
    container.scrollBy({ left: 300, behavior: "smooth" });
  });
}
