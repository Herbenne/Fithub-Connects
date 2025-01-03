const mainMenu = document.querySelector(".mainMenu");
const closeMenu = document.querySelector(".closeMenu");
const openMenu = document.querySelector(".openMenu");
const menuItems = document.querySelectorAll("nav .mainMenu li a");
const navLinks = document.querySelector(".mainMenu li a");

openMenu.addEventListener("click", show);
closeMenu.addEventListener("click", close);

menuItems.forEach((item) => {
  item.addEventListener("click", () => {
    close();
  });
});

function show() {
  mainMenu.style.display = "flex";
  mainMenu.style.top = "0px";
}

function close() {
  mainMenu.style.top = "-100%";
}



