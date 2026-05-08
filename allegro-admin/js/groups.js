let gruposState = {
  rows: [],
  actividades: [],
  profesores: [],
  editingId: null
};

async function renderGrupos() {
  const app = document.getElementById("app");
  app.innerHTML = `<div class="text-center py-5">Cargando grupos...</div>`;

  try {
    const [gruposRes, actividadesRes, profesoresRes] = await Promise.all([
      apiFetch("/grupos"),
      apiFetch("/actividades"),
      apiFetch("/profesores")
    ]);

    gruposState.rows = gruposRes.data || [];
    gruposState.actividades = actividadesRes.data || [];
    gruposState.profesores = profesoresRes.data || [];

    app.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="mb-0">Grupos</h2>
          <div class="text-muted">Cada grupo puede tener uno o más horarios semanales.</div>
        </div>
        <button class="btn btn-primary" id="grupoNuevoBtn">Nuevo grupo</button>
      </div>

      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Actividad</th>
                <th>Profesor</th>
                <th>Horarios</th>
                <th>Cupo</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              ${gruposState.rows.map(renderGrupoTableRow).join("")}
            </tbody>
          </table>
        </div>
      </div>
    `;

    document.getElementById("grupoNuevoBtn")?.addEventListener("click", () => openGrupoModal());
    bindGrupoActionButtons();
  } catch (err) {
    app.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
  }
}

function renderGrupoTableRow(grupo) {
  return `
    <tr>
      <td>${grupo.id}</td>
      <td>${escapeGrupoHtml(grupo.nombre)}</td>
      <td>${escapeGrupoHtml(grupo.actividad_nombre || "")}</td>
      <td>${escapeGrupoHtml(grupo.profesor_nombre || "")}</td>
      <td>${escapeGrupoHtml(grupo.horarios_texto || formatGrupoHorarios(grupo.horarios || []))}</td>
      <td>${grupo.cupo_maximo ?? ""}</td>
      <td>${renderGrupoEstadoBadge(grupo.estado)}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary grupo-edit-btn" data-id="${grupo.id}">Editar</button>
        <button class="btn btn-sm btn-outline-danger grupo-delete-btn" data-id="${grupo.id}">Eliminar</button>
      </td>
    </tr>
  `;
}

function bindGrupoActionButtons() {
  document.querySelectorAll(".grupo-edit-btn").forEach((button) => {
    button.addEventListener("click", () => openGrupoModal(Number(button.dataset.id)));
  });

  document.querySelectorAll(".grupo-delete-btn").forEach((button) => {
    button.addEventListener("click", async () => {
      const id = Number(button.dataset.id);
      if (!window.confirm("¿Eliminar este grupo?")) {
        return;
      }

      try {
        await apiFetch(`/grupos/${id}`, { method: "DELETE" });
        resetAsistenciasGroupCache();
        await renderGrupos();
      } catch (err) {
        window.alert(err.message);
      }
    });
  });
}

function openGrupoModal(id = null) {
  gruposState.editingId = id;
  const grupo = id ? gruposState.rows.find((row) => Number(row.id) === Number(id)) : null;
  const horarios = grupo?.horarios?.length
    ? grupo.horarios
    : [{ dia_semana: "lunes", hora_inicio: "", hora_fin: "" }];

  ensureGrupoModal();

  document.getElementById("grupoModalTitle").textContent = id ? "Editar grupo" : "Nuevo grupo";
  document.getElementById("grupoIdInput").value = id || "";
  document.getElementById("grupoNombreInput").value = grupo?.nombre || "";
  document.getElementById("grupoActividadInput").innerHTML = renderGrupoLookupOptions(gruposState.actividades, grupo?.actividad_id);
  document.getElementById("grupoProfesorInput").innerHTML = renderGrupoProfesorOptions(gruposState.profesores, grupo?.profesor_id);
  document.getElementById("grupoCupoInput").value = grupo?.cupo_maximo ?? "";
  document.getElementById("grupoEstadoInput").value = grupo?.estado || "activo";
  document.getElementById("grupoHorariosList").innerHTML = horarios.map(renderGrupoHorarioEditorRow).join("");

  bindGrupoHorarioEditorButtons();

  const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("grupoModal"));
  modal.show();
}

function ensureGrupoModal() {
  if (document.getElementById("grupoModal")) {
    return;
  }

  document.body.insertAdjacentHTML("beforeend", `
    <div class="modal fade" id="grupoModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="grupoModalTitle">Grupo</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <form id="grupoForm" class="row g-3">
              <input type="hidden" id="grupoIdInput">
              <div class="col-md-6">
                <label class="form-label">Actividad</label>
                <select class="form-select" id="grupoActividadInput" required></select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Profesor</label>
                <select class="form-select" id="grupoProfesorInput" required></select>
              </div>
              <div class="col-12">
                <label class="form-label">Nombre</label>
                <input class="form-control" id="grupoNombreInput" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Cupo máximo</label>
                <input class="form-control" id="grupoCupoInput" type="number" min="0">
              </div>
              <div class="col-md-6">
                <label class="form-label">Estado</label>
                <select class="form-select" id="grupoEstadoInput">
                  <option value="activo">activo</option>
                  <option value="inactivo">inactivo</option>
                </select>
              </div>
              <div class="col-12">
                <div class="border rounded p-3">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                      <div class="fw-semibold">Horarios semanales</div>
                      <div class="small text-muted">Agregá los días y horarios del grupo.</div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="grupoAgregarHorarioBtn">Agregar horario</button>
                  </div>
                  <div id="grupoHorariosList" class="d-flex flex-column gap-3"></div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="grupoGuardarBtn">Guardar</button>
          </div>
        </div>
      </div>
    </div>
  `);

  document.getElementById("grupoAgregarHorarioBtn")?.addEventListener("click", () => {
    const list = document.getElementById("grupoHorariosList");
    list.insertAdjacentHTML("beforeend", renderGrupoHorarioEditorRow({ dia_semana: "lunes", hora_inicio: "", hora_fin: "" }));
    bindGrupoHorarioEditorButtons();
  });

  document.getElementById("grupoGuardarBtn")?.addEventListener("click", saveGrupoModal);
}

function bindGrupoHorarioEditorButtons() {
  document.querySelectorAll(".grupo-horario-remove-btn").forEach((button) => {
    button.onclick = () => {
      const rows = document.querySelectorAll(".grupo-horario-editor-row");
      if (rows.length <= 1) {
        window.alert("El grupo necesita al menos un horario.");
        return;
      }
      button.closest(".grupo-horario-editor-row")?.remove();
    };
  });
}

function renderGrupoHorarioEditorRow(horario) {
  const dias = ["lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"];
  return `
    <div class="grupo-horario-editor-row border rounded p-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Día</label>
          <select class="form-select" data-grupo-horario-field="dia_semana">
            ${dias.map((dia) => `<option value="${dia}" ${horario?.dia_semana === dia ? "selected" : ""}>${dia}</option>`).join("")}
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Hora inicio</label>
          <input class="form-control" type="time" data-grupo-horario-field="hora_inicio" value="${escapeGrupoHtml((horario?.hora_inicio || "").slice(0, 5))}">
        </div>
        <div class="col-md-3">
          <label class="form-label">Hora fin</label>
          <input class="form-control" type="time" data-grupo-horario-field="hora_fin" value="${escapeGrupoHtml((horario?.hora_fin || "").slice(0, 5))}">
        </div>
        <div class="col-md-2">
          <button class="btn btn-outline-danger w-100 grupo-horario-remove-btn" type="button">Quitar</button>
        </div>
      </div>
    </div>
  `;
}

async function saveGrupoModal() {
  try {
    const payload = collectGrupoFormPayload();
    const id = document.getElementById("grupoIdInput").value;

    if (id) {
      await apiFetch(`/grupos/${id}`, {
        method: "PUT",
        body: JSON.stringify(payload)
      });
    } else {
      await apiFetch("/grupos", {
        method: "POST",
        body: JSON.stringify(payload)
      });
    }

    bootstrap.Modal.getInstance(document.getElementById("grupoModal"))?.hide();
    resetAsistenciasGroupCache();
    await renderGrupos();
  } catch (err) {
    window.alert(err.message);
  }
}

function collectGrupoFormPayload() {
  const horarios = Array.from(document.querySelectorAll(".grupo-horario-editor-row")).map((row) => ({
    dia_semana: row.querySelector('[data-grupo-horario-field="dia_semana"]')?.value || "",
    hora_inicio: row.querySelector('[data-grupo-horario-field="hora_inicio"]')?.value || "",
    hora_fin: row.querySelector('[data-grupo-horario-field="hora_fin"]')?.value || ""
  })).filter((horario) => horario.dia_semana && horario.hora_inicio && horario.hora_fin);

  if (!horarios.length) {
    throw new Error("Debes cargar al menos un horario.");
  }

  return {
    actividad_id: Number(document.getElementById("grupoActividadInput").value || 0),
    profesor_id: Number(document.getElementById("grupoProfesorInput").value || 0),
    nombre: document.getElementById("grupoNombreInput").value.trim(),
    cupo_maximo: normalizeGrupoNumber(document.getElementById("grupoCupoInput").value),
    estado: document.getElementById("grupoEstadoInput").value,
    horarios
  };
}

function renderGrupoLookupOptions(rows, selectedId) {
  return rows.map((row) => {
    return `<option value="${row.id}" ${Number(selectedId) === Number(row.id) ? "selected" : ""}>${escapeGrupoHtml(row.nombre || "")}</option>`;
  }).join("");
}

function renderGrupoProfesorOptions(rows, selectedId) {
  return rows.map((row) => {
    return `<option value="${row.id}" ${Number(selectedId) === Number(row.id) ? "selected" : ""}>${escapeGrupoHtml(`${row.apellido}, ${row.nombre}`)}</option>`;
  }).join("");
}

function renderGrupoEstadoBadge(estado) {
  const klass = estado === "activo" ? "bg-success-subtle text-success-emphasis" : "bg-secondary-subtle text-secondary-emphasis";
  return `<span class="badge ${klass}">${escapeGrupoHtml(estado || "")}</span>`;
}

function formatGrupoHorarios(horarios) {
  return (horarios || []).map((horario) => {
    return `${capitalizeGrupoText(horario.dia_semana)} ${formatGrupoTimeRange(horario.hora_inicio, horario.hora_fin)}`;
  }).join(", ");
}

function formatGrupoTimeRange(horaInicio, horaFin) {
  if (!horaInicio && !horaFin) {
    return "";
  }
  if (horaInicio && horaFin) {
    return `${horaInicio.slice(0, 5)} a ${horaFin.slice(0, 5)}`;
  }
  return (horaInicio || horaFin || "").slice(0, 5);
}

function capitalizeGrupoText(text) {
  if (!text) {
    return "";
  }
  return text.charAt(0).toUpperCase() + text.slice(1);
}

function normalizeGrupoNumber(value) {
  return value === "" ? null : Number(value);
}

function escapeGrupoHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function resetAsistenciasGroupCache() {
  if (typeof asistenciasState !== "undefined") {
    asistenciasState.grupos = [];
  }
}
