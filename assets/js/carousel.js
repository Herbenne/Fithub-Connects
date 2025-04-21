class Carousel {
  constructor() {
    this.currentSlide = 0;
    this.track = document.querySelector(".carousel-track");
    this.slides = document.querySelectorAll(".carousel-slide");
    this.dotsContainer = document.getElementById("carousel-dots");
    this.prevBtn = document.querySelector(".carousel-btn.prev");
    this.nextBtn = document.querySelector(".carousel-btn.next");
    this.slidesPerView = Math.floor(window.innerWidth / 320);

    this.initDots();
    this.initButtons();
    this.updateCarousel();
    this.initResizeHandler();
  }

  initDots() {
    const totalDots = Math.ceil(this.slides.length / this.slidesPerView);
    for (let i = 0; i < totalDots; i++) {
      const dot = document.createElement("span");
      dot.className = "dot";
      dot.addEventListener("click", () => this.goToSlide(i));
      this.dotsContainer.appendChild(dot);
    }
  }

  initButtons() {
    this.prevBtn.addEventListener("click", () => this.moveCarousel(-1));
    this.nextBtn.addEventListener("click", () => this.moveCarousel(1));
  }

  moveCarousel(direction) {
    const maxSlide = Math.ceil(this.slides.length / this.slidesPerView) - 1;
    this.currentSlide =
      (this.currentSlide + direction + maxSlide + 1) % (maxSlide + 1);
    this.updateCarousel();
  }

  goToSlide(index) {
    this.currentSlide = index;
    this.updateCarousel();
  }

  updateCarousel() {
    if (!this.slides.length) return;

    const slideWidth = this.slides[0].offsetWidth + 20; // Width + margin
    this.track.style.transform = `translateX(${
      -this.currentSlide * slideWidth * this.slidesPerView
    }px)`;

    // Update dots
    const dots = document.querySelectorAll(".dot");
    dots.forEach((dot, index) => {
      dot.classList.toggle("active", index === this.currentSlide);
    });

    // Update button visibility
    const maxSlide = Math.ceil(this.slides.length / this.slidesPerView) - 1;
    this.prevBtn.style.display = this.currentSlide === 0 ? "none" : "flex";
    this.nextBtn.style.display =
      this.currentSlide >= maxSlide ? "none" : "flex";
  }

  initResizeHandler() {
    window.addEventListener("resize", () => {
      const newSlidesPerView = Math.floor(window.innerWidth / 320);
      if (newSlidesPerView !== this.slidesPerView) {
        location.reload();
      }
    });
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new Carousel();
});
