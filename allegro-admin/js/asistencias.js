let asistenciasState = {
  grupos: [],
  grupoId: "",
  mes: "",
  matrix: null
};

async function renderAsistencias() {
  const app = document.getElementById("app");
  app.innerHTML = `<div class="text-center py-5">Cargando asistencias...</div>`;

  try {
    if (!asistenciasState.grupos.length) {
      const res = await apiFetch("/grupos");
      asistenciasState.grupos = res.data || [];
    }

    if (!asistenciasState.grupoId && asistenciasState.grupos.length) {
      asistenciasState.grupoId = String(asistenciasState.grupos[0].id);
    }

    if (!asistenciasState.mes) {
      asistenciasState.mes = getCurrentMonthValue();
    }

    await loadAsistenciasMatrix();
  } catch (err) {
    app.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
  }
}

async function loadAsistenciasMatrix() {
  const app = document.getElementById("app");
  renderAsistenciasShell(true);

  if (!asistenciasState.grupoId) {
    document.getElementById("asistenciasContent").innerHTML = `
      <div class="alert alert-warning mb-0">No hay grupos disponibles para tomar asistencia.</div>
    `;
    return;
  }

  try {
    const res = await apiFetch(`/asistencias/matriz?grupo_id=${encodeURIComponent(asistenciasState.grupoId)}&mes=${encodeURIComponent(asistenciasState.mes)}`);
    asistenciasState.matrix = res.data;
    renderAsistenciasShell(false);
  } catch (err) {
    asistenciasState.matrix = null;
    renderAsistenciasShell(false, err.message);
  }
}

function renderAsistenciasShell(isLoading, errorMessage = "") {
  const app = document.getElementById("app");
  const matrix = asistenciasState.matrix;
  const horariosTexto = matrix?.grupo?.horarios_texto || formatHorariosList(matrix?.grupo?.horarios || []);
  const summary = matrix
    ? `${matrix.grupo.actividad_nombre} · ${matrix.grupo.nombre}${horariosTexto ? ` · ${horariosTexto}` : ""}`
    : "Selecciona un grupo y un mes para editar asistencias.";

  app.innerHTML = `
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
      <div>
        <h2 class="mb-1">Asistencias</h2>
        <div class="text-muted">${summary}</div>
      </div>
      <div class="d-flex flex-column flex-md-row gap-2">
        <select class="form-select" id="asistenciasGrupoSelect">
          ${renderGrupoOptions()}
        </select>
        <input class="form-control" id="asistenciasMesInput" type="month" value="${asistenciasState.mes}">
        <button class="btn btn-primary" id="asistenciasReloadBtn">Ver mes</button>
      </div>
    </div>

    <div id="asistenciasContent">
      ${isLoading ? `<div class="card shadow-sm"><div class="card-body text-center py-5">Cargando matriz de asistencias...</div></div>` : renderAsistenciasContent(errorMessage)}
    </div>
  `;

  bindAsistenciasFilters();

  if (!isLoading && matrix) {
    bindAsistenciasCells();
  }
}

function renderAsistenciasContent(errorMessage) {
  if (errorMessage) {
    return `<div class="alert alert-danger mb-0">${errorMessage}</div>`;
  }

  const matrix = asistenciasState.matrix;
  if (!matrix) {
    return `<div class="alert alert-secondary mb-0">Todavia no hay datos para mostrar.</div>`;
  }

  if (!matrix.clases.length) {
    return `
      <div class="alert alert-warning mb-0">
        Este grupo no tiene clases registradas ni un dia semanal configurado para el mes seleccionado.
      </div>
    `;
  }

  return `
    <div class="card shadow-sm">
      <div class="card-body border-bottom">
        <div class="small text-muted">
          Las celdas se guardan al cambiar el valor. Si una fecha aun no tiene clase creada, se genera automaticamente usando el horario del grupo.
        </div>
      </div>
      <div class="table-responsive attendance-table-wrap">
        <table class="table table-hover align-middle mb-0 attendance-table">
          <thead>
            <tr>
              <th class="attendance-sticky-col attendance-name-col">Integrante</th>
              ${matrix.clases.map(renderClaseHeader).join("")}
            </tr>
          </thead>
          <tbody>
            ${matrix.participantes.length ? matrix.participantes.map(renderParticipanteRow).join("") : `
              <tr>
                <td class="text-muted" colspan="${matrix.clases.length + 1}">No hay integrantes activos o pausados en este grupo para el mes seleccionado.</td>
              </tr>
            `}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderGrupoOptions() {
  return asistenciasState.grupos.map(grupo => `
    <option value="${grupo.id}" ${String(grupo.id) === String(asistenciasState.grupoId) ? "selected" : ""}>
      ${grupo.actividad_nombre} · ${grupo.nombre}
    </option>
  `).join("");
}

function renderClaseHeader(clase) {
  const [year, month, day] = clase.fecha.split("-");
  const date = new Date(`${year}-${month}-${day}T00:00:00`);
  const dayLabel = date.toLocaleDateString("es-AR", { weekday: "short" });
  const dateLabel = date.toLocaleDateString("es-AR", { day: "2-digit", month: "2-digit" });
  const classBadge = clase.clase_id ? "bg-success-subtle text-success-emphasis" : "bg-secondary-subtle text-secondary-emphasis";

  return `
    <th class="text-center attendance-date-col">
      <div class="fw-semibold text-uppercase small">${dayLabel}</div>
      <div>${dateLabel}</div>
      <div><span class="badge ${classBadge}">${clase.clase_id ? "Clase" : "Programada"}</span></div>
    </th>
  `;
}

function renderParticipanteRow(participante) {
  const statusBadge = participante.inscripcion_estado === "activa" ? "bg-success-subtle text-success-emphasis" : "bg-warning-subtle text-warning-emphasis";

  return `
    <tr>
      <td class="attendance-sticky-col attendance-name-col">
        <div class="fw-semibold">${participante.apellido}, ${participante.nombre}</div>
        <div class="small text-muted">${participante.email || ""}</div>
        <div class="mt-1"><span class="badge ${statusBadge}">${participante.inscripcion_estado}</span></div>
      </td>
      ${asistenciasState.matrix.clases.map(clase => renderAttendanceCell(participante, clase)).join("")}
    </tr>
  `;
}

function renderAttendanceCell(participante, clase) {
  const attendance = participante.asistencias?.[clase.fecha] || null;
  const currentValue = attendance?.estado || "";

  return `
    <td class="text-center">
      <select
        class="form-select form-select-sm attendance-select ${getAttendanceSelectClass(currentValue)}"
        data-participante-id="${participante.participante_id}"
        data-fecha="${clase.fecha}"
      >
        <option value="" ${currentValue === "" ? "selected" : ""}>-</option>
        <option value="presente" ${currentValue === "presente" ? "selected" : ""}>P</option>
        <option value="ausente" ${currentValue === "ausente" ? "selected" : ""}>A</option>
      </select>
    </td>
  `;
}

function bindAsistenciasFilters() {
  document.getElementById("asistenciasGrupoSelect")?.addEventListener("change", (event) => {
    asistenciasState.grupoId = event.target.value;
  });

  document.getElementById("asistenciasMesInput")?.addEventListener("change", (event) => {
    asistenciasState.mes = event.target.value || getCurrentMonthValue();
  });

  document.getElementById("asistenciasReloadBtn")?.addEventListener("click", async () => {
    await loadAsistenciasMatrix();
  });
}

function bindAsistenciasCells() {
  document.querySelectorAll(".attendance-select").forEach(select => {
    select.addEventListener("change", async (event) => {
      const target = event.target;
      const previousValue = target.dataset.previousValue ?? "";
      const nextValue = target.value;

      try {
        setAttendanceSelectBusy(target, true);
        await saveAttendanceValue({
          participanteId: Number(target.dataset.participanteId),
          fecha: target.dataset.fecha,
          estado: nextValue
        });
        target.dataset.previousValue = nextValue;
        updateAttendanceSelectStyle(target, nextValue);
      } catch (err) {
        target.value = previousValue;
        updateAttendanceSelectStyle(target, previousValue);
        alert(err.message);
      } finally {
        setAttendanceSelectBusy(target, false);
      }
    });

    select.dataset.previousValue = select.value;
  });
}

async function saveAttendanceValue({ participanteId, fecha, estado }) {
  const clase = asistenciasState.matrix.clases.find(item => item.fecha === fecha);
  if (!clase) {
    throw new Error("No se encontro la clase asociada a esa fecha.");
  }

  const participante = asistenciasState.matrix.participantes.find(item => item.participante_id === participanteId);
  if (!participante) {
    throw new Error("No se encontro el participante.");
  }

  const existente = participante.asistencias?.[fecha] || null;

  if (!estado) {
    if (!existente?.id) {
      return;
    }

    await apiFetch(`/asistencias/${existente.id}`, { method: "DELETE" });
    participante.asistencias[fecha] = null;
    return;
  }

  let claseId = clase.clase_id;
  if (!claseId) {
    const creada = await apiFetch("/clases", {
      method: "POST",
      body: JSON.stringify({
        grupo_id: Number(asistenciasState.matrix.grupo.id),
        fecha,
        hora_inicio: clase.hora_inicio || asistenciasState.matrix.grupo.hora_inicio || "",
        hora_fin: clase.hora_fin || asistenciasState.matrix.grupo.hora_fin || "",
        estado: "programada"
      })
    });

    claseId = creada.data.id;
    clase.clase_id = claseId;
    clase.origen = "real";
    refreshAttendanceHeaderBadges();
  }

  const response = await apiFetch("/asistencias", {
    method: "POST",
    body: JSON.stringify({
      clase_id: claseId,
      participante_id: participanteId,
      estado
    })
  });

  participante.asistencias[fecha] = {
    ...(existente || {}),
    id: response.data?.id || existente?.id || null,
    clase_id: claseId,
    estado,
    hora_registro: response.data?.hora_registro || existente?.hora_registro || null
  };
}

function refreshAttendanceHeaderBadges() {
  const content = document.getElementById("asistenciasContent");
  if (!content || !asistenciasState.matrix) {
    return;
  }

  content.innerHTML = renderAsistenciasContent("");
  bindAsistenciasCells();
}

function setAttendanceSelectBusy(select, isBusy) {
  select.disabled = isBusy;
  select.classList.toggle("opacity-75", isBusy);
}

function updateAttendanceSelectStyle(select, value) {
  select.classList.remove("attendance-empty", "attendance-present", "attendance-absent");
  select.classList.add(getAttendanceSelectClass(value));
}

function getAttendanceSelectClass(value) {
  if (value === "presente") return "attendance-present";
  if (value === "ausente") return "attendance-absent";
  return "attendance-empty";
}

function getCurrentMonthValue() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  return `${year}-${month}`;
}

function capitalizeText(text) {
  if (!text) return "";
  return text.charAt(0).toUpperCase() + text.slice(1);
}

function formatTimeRange(horaInicio, horaFin) {
  if (!horaInicio && !horaFin) {
    return "";
  }

  if (horaInicio && horaFin) {
    return `${horaInicio.slice(0, 5)} a ${horaFin.slice(0, 5)}`;
  }

  return (horaInicio || horaFin || "").slice(0, 5);
}

function formatHorariosList(horarios) {
  if (!Array.isArray(horarios) || !horarios.length) {
    return "";
  }

  return horarios.map((horario) => {
    return `${capitalizeText(horario.dia_semana)} ${formatTimeRange(horario.hora_inicio, horario.hora_fin)}`;
  }).join(", ");
}
