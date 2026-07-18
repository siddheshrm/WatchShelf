let currentImage = 0;

const mainImage = document.getElementById("main-image");

// Display the next image, wrapping to the first image after the last
document.querySelector(".next-image").addEventListener("click", () => {
  currentImage = (currentImage + 1) % images.length;
  mainImage.src = images[currentImage];
});

// Display the previous image, wrapping to the last image before the first
document.querySelector(".prev-image").addEventListener("click", () => {
  currentImage = (currentImage - 1 + images.length) % images.length;
  mainImage.src = images[currentImage];
});
