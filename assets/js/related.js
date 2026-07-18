const container = document.querySelector(".related-grid");
const leftButton = document.querySelector(".scroll-left");
const rightButton = document.querySelector(".scroll-right");

if (container && leftButton && rightButton) {
  leftButton.addEventListener("click", () => {
    container.scrollBy({ left: -300, behavior: "smooth" });
  });

  rightButton.addEventListener("click", () => {
    container.scrollBy({ left: 300, behavior: "smooth" });
  });
}
