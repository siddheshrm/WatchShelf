const container = document.getElementById("colorsContainer");
const template = document.getElementById("colorTemplate");
const addColorBtn = document.getElementById("addColorBtn");

addColorBtn.addEventListener("click", function () {
  const clone = template.content.cloneNode(true);

  const details = clone.querySelector(".color-block");
  const summary = clone.querySelector("summary strong");

  const colorIndex = container.querySelectorAll(".color-block").length;

  // Color
  const colorSelect = clone.querySelector(".color-select");
  colorSelect.name = `colors[${colorIndex}][color_name]`;

  colorSelect.addEventListener("change", function () {
    summary.textContent = this.value || "New Color";
  });

  // Default Color
  const defaultColor = clone.querySelector(".default-color");
  defaultColor.value = colorIndex;

  // Retailers
  const retailerBlocks = clone.querySelectorAll(".retailer-block");

  retailerBlocks.forEach((block, retailerIndex) => {
    block.querySelector(".retailer-name").name =
      `colors[${colorIndex}][retailers][${retailerIndex}][retailer_name]`;

    block.querySelector(".retailer-type").name =
      `colors[${colorIndex}][retailers][${retailerIndex}][retailer_type]`;

    block.querySelector(".base-url").name =
      `colors[${colorIndex}][retailers][${retailerIndex}][base_url]`;

    block.querySelector(".affiliate-url").name =
      `colors[${colorIndex}][retailers][${retailerIndex}][affiliate_url]`;

    block.querySelector(".price").name =
      `colors[${colorIndex}][retailers][${retailerIndex}][price]`;

    block.querySelector(".is-available").name =
      `colors[${colorIndex}][retailers][${retailerIndex}][is_available]`;
  });

  container.appendChild(clone);
});

// Add first color automatically
addColorBtn.click();
