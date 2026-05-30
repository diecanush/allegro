let profesoresState = {
  rows: [],
  editingId: null
};

async function renderProfesores() {
  const app = document.getElementById("app");
  app.innerHTML = `<div class="text-center py-5">Cargando profesores...</div>`;

  try {
    const res = await apiFetch("/profesores");
    profesoresState.rows = res.data || [];

    app.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="mb-0">Profesores</h2>
          <div class="text-muted">Gestion de docentes y sus accesos.</div>
        </div>
        <button class="btn btn-primary" id="profesorNuevoBtn">Nuevo profesor</button>
      </div>

      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Apellido y nombre</th>
                <th>Email</th>
                <th>Telefono</th>
                <th>Especialidad</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              ${profesoresState.rows.map(renderProfesorRow).join("") || `<tr><td colspan="7" class="text-muted text-center py-4">Sin profesores cargados.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
    `;

    document.getElementById("profesorNuevoBtn")?.addEventListener("click", () => openProfesorModal());
    bindProfesorButtons();
  } catch (err) {
    app.innerHTML = `<div class="alert alert-danger">${escapeProfesorHtml(err.message)}</div>`;
  }
}

function renderProfesorRow(profesor) {
  return `
    <tr>
      <td>${profesor.id}</td>
      <td>${escapeProfesorHtml(profesor.apellido)}, ${escapeProfesorHtml(profesor.nombre)}</td>
      <td>${escapeProfesorHtml(profesor.email || "")}</td>
      <td>${escapeProfesorHtml(profesor.telefono || "")}</td>
      <td>${escapeProfesorHtml(profesor.especialidad || "")}</td>
      <td>${renderProfesorEstadoBadge(profesor.estado)}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary profesor-edit-btn" data-id="${profesor.id}">Editar</button>
        <button class="btn btn-sm btn-outline-danger profesor-delete-btn" data-id="${profesor.id}">Eliminar</button>
      </td>
    </tr>
  `;
}

function bindProfesorButtons() {
  document.querySelectorAll(".profesor-edit-btn").forEach((button) => {
    button.addEventListener("click", () => openProfesorModal(Number(button.dataset.id)));
  });

  document.querySelectorAll(".profesor-delete-btn").forEach((button) => {
    button.addEventListener("click", async () => {
      const id = Number(button.dataset.id);
      if (!window.confirm("Eliminar este profesor?")) {
        return;
      }

      try {
        await apiFetch(`/profesores/${id}`, { method: "DELETE" });
        await renderProfesores();
      } catch (err) {
        window.alert(err.message);
      }
    });
  });
}

function openProfesorModal(id = null) {
  profesoresState.editingId = id;
  const profesor = id ? profesoresState.rows.find((row) => Number(row.id) === Number(id)) : null;

  ensureProfesorModal();

  document.getElementById("profesorModalTitle").textContent = id ? "Editar profesor" : "Nuevo profesor";
  document.getElementById("profesorIdInput").value = id || "";
  document.getElementById("profesorNombreInput").value = profesor?.nombre || "";
  document.getElementById("profesorApellidoInput").value = profesor?.apellido || "";
  document.getElementById("profesorEmailInput").value = profesor?.email || "";
  document.getElementById("profesorTelefonoInput").value = profesor?.telefono || "";
  document.getElementById("profesorEspecialidadInput").value = profesor?.especialidad || "";
  document.getElementById("profesorObservacionesInput").value = profesor?.observaciones || "";
  document.getElementById("profesorEstadoInput").value = profesor?.estado || "activo";
  document.getElementById("profesorPasswordInput").value = "";
  document.getElementById("profesorPasswordHelp").textContent = id ? "Dejar vacio para mantener la clave actual." : "Obligatoria para crear el acceso.";

  bootstrap.Modal.getOrCreateInstance(document.getElementById("profesorModal")).show();
}

function ensureProfesorModal() {
  if (document.getElementById("profesorModal")) {
    return;
  }

  document.body.insertAdjacentHTML("beforeend", `
    <div class="modal fade" id="profesorModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="profesorModalTitle">Profesor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <form id="profesorForm" class="row g-3">
              <input type="hidden" id="profesorIdInput">
              <div class="col-md-6">
                <label class="form-label">Nombre</label>
                <input class="form-control" id="profesorNombreInput" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Apellido</label>
                <input class="form-control" id="profesorApellidoInput" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input class="form-control" id="profesorEmailInput" type="email" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Telefono</label>
                <input class="form-control" id="profesorTelefonoInput">
              </div>
              <div class="col-md-6">
                <label class="form-label">Especialidad</label>
                <input class="form-control" id="profesorEspecialidadInput">
              </div>
              <div class="col-md-6">
                <label class="form-label">Estado</label>
                <select class="form-select" id="profesorEstadoInput">
                  <option value="activo">activo</option>
                  <option value="inactivo">inactivo</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Password</label>
                <input class="form-control" id="profesorPasswordInput" type="password">
                <div class="form-text" id="profesorPasswordHelp"></div>
              </div>
              <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" id="profesorObservacionesInput" rows="3"></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="profesorGuardarBtn">Guardar</button>
          </div>
        </div>
      </div>
    </div>
  `);

  document.getElementById("profesorGuardarBtn")?.addEventListener("click", saveProfesorModal);
}

async function saveProfesorModal() {
  try {
    const id = document.getElementById("profesorIdInput").value;
    const payload = {
      nombre: document.getElementById("profesorNombreInput").value.trim(),
      apellido: document.getElementById("profesorApellidoInput").value.trim(),
      email: document.getElementById("profesorEmailInput").value.trim(),
      telefono: document.getElementById("profesorTelefonoInput").value.trim(),
      especialidad: document.getElementById("profesorEspecialidadInput").value.trim(),
      observaciones: document.getElementById("profesorObservacionesInput").value.trim() || null,
      estado: document.getElementById("profesorEstadoInput").value,
      password: document.getElementById("profesorPasswordInput").value
    };

    if (!payload.nombre || !payload.apellido || !payload.email) {
      throw new Error("Nombre, apellido y email son obligatorios.");
    }
    if (!id && !payload.password) {
      throw new Error("El password es obligatorio para crear un profesor.");
    }

    await apiFetch(id ? `/profesores/${id}` : "/profesores", {
      method: id ? "PUT" : "POST",
      body: JSON.stringify(payload)
    });

    bootstrap.Modal.getInstance(document.getElementById("profesorModal"))?.hide();
    await renderProfesores();
  } catch (err) {
    window.alert(err.message);
  }
}

function renderProfesorEstadoBadge(estado) {
  const klass = estado === "activo" ? "bg-success-subtle text-success-emphasis" : "bg-secondary-subtle text-secondary-emphasis";
  return `<span class="badge ${klass}">${escapeProfesorHtml(estado || "")}</span>`;
}

function escapeProfesorHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
