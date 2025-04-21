function initializeCharts(months, memberCounts, ratings) {
  // Membership Trends Chart
  const membershipCtx = document
    .getElementById("membershipTrends")
    .getContext("2d");
  new Chart(membershipCtx, {
    type: "line",
    data: {
      labels: months,
      datasets: [
        {
          label: "New Members",
          data: memberCounts,
          borderColor: "#007bff",
          tension: 0.1,
          fill: false,
        },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        title: {
          display: true,
          text: "Monthly New Members (Last 6 Months)",
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1,
          },
        },
      },
    },
  });

  // Rating Trends Chart
  const ratingCtx = document.getElementById("ratingTrends").getContext("2d");
  new Chart(ratingCtx, {
    type: "bar",
    data: {
      labels: months,
      datasets: [
        {
          label: "Average Rating",
          data: ratings,
          backgroundColor: "#28a745",
        },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        title: {
          display: true,
          text: "Monthly Average Ratings (Last 6 Months)",
        },
      },
      scales: {
        y: {
          min: 0,
          max: 5,
          ticks: {
            stepSize: 1,
          },
        },
      },
    },
  });
}

function showApplicationForm() {
  const form = document.getElementById("applicationForm");
  form.style.display = form.style.display === "none" ? "block" : "none";
}

let currentPage = 0;

document.addEventListener("DOMContentLoaded", function () {
  const pages = document.querySelectorAll(".carousel-page");
  if (pages.length <= 1) {
    document.querySelectorAll(".carousel-btn").forEach((btn) => {
      btn.style.display = "none";
    });
  }
  updateButtonStates();

  // Initialize carousel if it exists
  if (document.querySelector(".carousel-container")) {
    new Carousel();
  }
});

class Carousel {
  constructor() {
    this.container = document.querySelector(".carousel-container");
    if (!this.container) return;

    this.track = this.container.querySelector(".carousel-track");
    this.slides = this.container.querySelectorAll(".carousel-slide");
    this.prevBtn = this.container.querySelector(".carousel-btn.prev");
    this.nextBtn = this.container.querySelector(".carousel-btn.next");

    if (!this.track || !this.slides.length) return;

    // Initialize properties
    this.currentIndex = 0;
    this.slidesPerView = 3;
    this.totalSlides = this.slides.length;
    this.maxSlide = Math.max(
      0,
      Math.ceil(this.totalSlides / this.slidesPerView) - 1
    );

    console.log("Total slides:", this.totalSlides);
    console.log("Max slide index:", this.maxSlide);

    this.init();
  }

  init() {
    // Calculate slide width based on container width
    const containerWidth = this.container.clientWidth - 100; // Account for padding
    const slideWidth = containerWidth / this.slidesPerView - 20; // Account for gap

    // Set initial slide widths
    this.slides.forEach((slide) => {
      slide.style.width = `${slideWidth}px`;
      slide.style.marginRight = "20px";
    });

    // Add event listeners
    this.prevBtn.addEventListener("click", () => {
      console.log("Previous clicked, current index:", this.currentIndex);
      this.move("prev");
    });

    this.nextBtn.addEventListener("click", () => {
      console.log("Next clicked, current index:", this.currentIndex);
      this.move("next");
    });

    // Initialize carousel
    this.updateCarousel();

    // Add resize handler
    window.addEventListener("resize", () => {
      const containerWidth = this.container.clientWidth - 100;
      const slideWidth = containerWidth / this.slidesPerView - 20;

      this.slides.forEach((slide) => {
        slide.style.width = `${slideWidth}px`;
      });

      this.updateCarousel();
    });
  }

  move(direction) {
    if (direction === "prev" && this.currentIndex > 0) {
      this.currentIndex--;
      console.log("Moving prev to index:", this.currentIndex);
    } else if (direction === "next" && this.currentIndex < this.maxSlide) {
      this.currentIndex++;
      console.log("Moving next to index:", this.currentIndex);
    }
    this.updateCarousel();
  }

  updateCarousel() {
    // Calculate slide width and gap
    const slideWidth = this.slides[0].offsetWidth;
    const gap = 20;
    const moveAmount = -(
      this.currentIndex *
      (slideWidth + gap) *
      this.slidesPerView
    );

    console.log("Moving track by:", moveAmount, "pixels");

    // Apply transform
    this.track.style.transform = `translateX(${moveAmount}px)`;

    // Update button visibility
    this.prevBtn.style.display = this.currentIndex === 0 ? "none" : "flex";
    this.nextBtn.style.display =
      this.currentIndex >= this.maxSlide ? "none" : "flex";

    console.log(
      "Button states - Prev:",
      this.prevBtn.style.display,
      "Next:",
      this.nextBtn.style.display
    );
  }
}

// Remove any duplicate initialization
document.addEventListener("DOMContentLoaded", () => {
  // Remove previous event listeners
  const oldCarousel = document.querySelector(".carousel-container");
  if (oldCarousel) {
    const oldBtns = oldCarousel.querySelectorAll(".carousel-btn");
    oldBtns.forEach((btn) => {
      btn.replaceWith(btn.cloneNode(true));
    });
  }

  // Initialize new carousel
  new Carousel();
});
