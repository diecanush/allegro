function requireLogin() {
  const token = getToken();
  if (!token) {
    window.location.href = "login.html";
  }
}

function setUserInfo() {
  const user = getUser();
  const el = document.getElementById("userInfo");
  if (user && el) {
    el.textContent = `${user.nombre} ${user.apellido} (${user.rol})`;
  }
}

async function logout() {
  try {
    await apiFetch("/auth/logout", { method: "POST" });
  } catch (_) {}
  clearSession();
  window.location.href = "login.html";
}

function bindNav() {
  document.querySelectorAll("[data-view]").forEach(link => {
    link.addEventListener("click", async (e) => {
      e.preventDefault();
      const view = e.target.dataset.view;
      if (view === "dashboard") await renderDashboard();
      if (view === "participantes") await renderParticipantes();
      if (view === "profesores") renderPlaceholder("Profesores");
      if (view === "grupos") await renderGrupos();
      if (view === "asistencias") await renderAsistencias();
      if (view === "pagos") renderPlaceholder("Pagos");
    });
  });

  document.getElementById("logoutBtn")?.addEventListener("click", logout);
}

function renderPlaceholder(title) {
  document.getElementById("app").innerHTML = `
    <div class="card shadow-sm">
      <div class="card-body">
        <h3>${title}</h3>
        <p class="text-muted mb-0">Vista en construcción.</p>
      </div>
    </div>
  `;
}

document.addEventListener("DOMContentLoaded", async () => {
  requireLogin();
  setUserInfo();
  bindNav();
  await renderDashboard();
});
