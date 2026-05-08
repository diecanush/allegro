document.getElementById("loginForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();

  const errorBox = document.getElementById("loginError");
  errorBox.classList.add("d-none");
  errorBox.textContent = "";

  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value;

  try {
    const res = await apiFetch("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password })
    });

    setSession(res.data.token, res.data.user);
    window.location.href = "index.html";
  } catch (err) {
    errorBox.textContent = err.message;
    errorBox.classList.remove("d-none");
  }
});