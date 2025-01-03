document.addEventListener("DOMContentLoaded", function () {
  const loginButton = document.querySelector(".login-button");
  const signupButton = document.querySelector(".signup-button");
  const joinNowButton = document.querySelector(".join-now-button");

  if (loginButton) {
    loginButton.addEventListener("click", function () {
      window.location.href = "../../Login&Registration/login_form.php";
    });
  }

  if (signupButton) {
    signupButton.addEventListener("click", function () {
      window.location.href = "../../Login&Registration/register_form.php";
    });
  }

  if (joinNowButton) {
    joinNowButton.addEventListener("click", function () {
      window.location.href = "../../Login&Registration/register_form.php";
    });
  }
});



