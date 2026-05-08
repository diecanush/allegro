async function renderParticipantes() {
  const app = document.getElementById("app");
  app.innerHTML = `<div class="text-center py-5">Cargando participantes...</div>`;

  try {
    const res = await apiFetch("/participantes");
    const rows = res.data || [];

    app.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Participantes</h2>
      </div>

      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Apellido y nombre</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>DNI</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              ${rows.map(r => `
                <tr>
                  <td>${r.id}</td>
                  <td>${r.apellido}, ${r.nombre}</td>
                  <td>${r.email ?? ""}</td>
                  <td>${r.telefono ?? ""}</td>
                  <td>${r.dni ?? ""}</td>
                  <td>${r.estado}</td>
                </tr>
              `).join("")}
            </tbody>
          </table>
        </div>
      </div>
    `;
  } catch (err) {
    app.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
  }
}