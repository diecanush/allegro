let pagosState = {
  rows: [],
  participantes: [],
  editingId: null
};

async function renderPagos() {
  const app = document.getElementById("app");
  app.innerHTML = `<div class="text-center py-5">Cargando pagos...</div>`;

  try {
    const [pagosRes, participantesRes] = await Promise.all([
      apiFetch("/pagos"),
      apiFetch("/participantes")
    ]);

    pagosState.rows = pagosRes.data || [];
    pagosState.participantes = participantesRes.data || [];

    app.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="mb-0">Pagos</h2>
          <div class="text-muted">Registro y seguimiento de pagos recibidos.</div>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-primary" id="generarCargosBtn">Generar cargos</button>
          <button class="btn btn-primary" id="pagoNuevoBtn">Nuevo pago</button>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Participante</th>
                <th>Medio</th>
                <th>Referencia</th>
                <th>Estado</th>
                <th class="text-end">Importe</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              ${pagosState.rows.map(renderPagoRow).join("") || `<tr><td colspan="8" class="text-muted text-center py-4">Sin pagos registrados.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
    `;

    document.getElementById("pagoNuevoBtn")?.addEventListener("click", () => openPagoModal());
    document.getElementById("generarCargosBtn")?.addEventListener("click", () => openGenerarCargosModal());
    bindPagoButtons();
  } catch (err) {
    app.innerHTML = `<div class="alert alert-danger">${escapePagoHtml(err.message)}</div>`;
  }
}

function renderPagoRow(pago) {
  return `
    <tr>
      <td>${pago.id}</td>
      <td>${escapePagoHtml(formatPagoDate(pago.fecha_pago))}</td>
      <td>${escapePagoHtml(pago.apellido || "")}, ${escapePagoHtml(pago.nombre || "")}</td>
      <td>${escapePagoHtml(pago.medio_pago || "")}</td>
      <td>${escapePagoHtml(pago.referencia || "")}</td>
      <td>${renderPagoEstadoBadge(pago.estado)}</td>
      <td class="text-end">$ ${Number(pago.importe || 0).toLocaleString("es-AR")}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-danger pago-delete-btn" data-id="${pago.id}">Eliminar</button>
      </td>
    </tr>
  `;
}

function bindPagoButtons() {
  document.querySelectorAll(".pago-delete-btn").forEach((button) => {
    button.addEventListener("click", async () => {
      const id = Number(button.dataset.id);
      if (!window.confirm("Eliminar este pago?")) {
        return;
      }

      try {
        await apiFetch(`/pagos/${id}`, { method: "DELETE" });
        await renderPagos();
      } catch (err) {
        window.alert(err.message);
      }
    });
  });
}

function openPagoModal() {
  ensurePagoModal();

  document.getElementById("pagoParticipanteInput").innerHTML = pagosState.participantes.map((participante) => {
    return `<option value="${participante.id}">${escapePagoHtml(participante.apellido)}, ${escapePagoHtml(participante.nombre)}</option>`;
  }).join("");
  document.getElementById("pagoFechaInput").value = new Date().toISOString().slice(0, 10);
  document.getElementById("pagoImporteInput").value = "";
  document.getElementById("pagoMedioInput").value = "transferencia";
  document.getElementById("pagoReferenciaInput").value = "";
  document.getElementById("pagoEstadoInput").value = "aprobado";
  document.getElementById("pagoObservacionesInput").value = "";

  bootstrap.Modal.getOrCreateInstance(document.getElementById("pagoModal")).show();
}

function openGenerarCargosModal() {
  ensureGenerarCargosModal();

  document.getElementById("cargoPeriodoInput").value = new Date().toISOString().slice(0, 7);
  document.getElementById("cargoGeneracionResultado").className = "alert d-none";
  document.getElementById("cargoGeneracionResultado").textContent = "";

  bootstrap.Modal.getOrCreateInstance(document.getElementById("generarCargosModal")).show();
}

function ensureGenerarCargosModal() {
  if (document.getElementById("generarCargosModal")) {
    return;
  }

  document.body.insertAdjacentHTML("beforeend", `
    <div class="modal fade" id="generarCargosModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Generar cargos mensuales</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Periodo</label>
              <input class="form-control" id="cargoPeriodoInput" type="month" required>
            </div>
            <div class="text-muted small mb-3">
              Se generara un cargo para cada participante con inscripcion activa en grupos activos y plan activo. Si ya existe un cargo para el mismo periodo, se omite.
            </div>
            <div class="alert d-none" id="cargoGeneracionResultado"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="button" class="btn btn-primary" id="confirmarGenerarCargosBtn">Generar</button>
          </div>
        </div>
      </div>
    </div>
  `);

  document.getElementById("confirmarGenerarCargosBtn")?.addEventListener("click", generarCargosMensuales);
}

async function generarCargosMensuales() {
  const resultBox = document.getElementById("cargoGeneracionResultado");
  const button = document.getElementById("confirmarGenerarCargosBtn");
  const periodo = document.getElementById("cargoPeriodoInput").value;

  try {
    if (!periodo) {
      throw new Error("Elegir un periodo.");
    }

    button.disabled = true;
    button.textContent = "Generando...";
    resultBox.className = "alert alert-info";
    resultBox.textContent = "Generando cargos...";

    const res = await apiFetch("/cargos/generar-mensuales", {
      method: "POST",
      body: JSON.stringify({ periodo })
    });

    const data = res.data || {};
    resultBox.className = "alert alert-success";
    resultBox.innerHTML = `
      Periodo ${escapePagoHtml(data.periodo)}: ${data.generados?.length || 0} generados, ${data.omitidos?.length || 0} omitidos.
    `;
    await renderPagos();
  } catch (err) {
    resultBox.className = "alert alert-danger";
    resultBox.textContent = err.message;
  } finally {
    button.disabled = false;
    button.textContent = "Generar";
  }
}

function ensurePagoModal() {
  if (document.getElementById("pagoModal")) {
    return;
  }

  document.body.insertAdjacentHTML("beforeend", `
    <div class="modal fade" id="pagoModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Nuevo pago</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <form id="pagoForm" class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Participante</label>
                <select class="form-select" id="pagoParticipanteInput" required></select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Fecha</label>
                <input class="form-control" id="pagoFechaInput" type="date" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Importe</label>
                <input class="form-control" id="pagoImporteInput" type="number" min="0" step="0.01" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Medio de pago</label>
                <select class="form-select" id="pagoMedioInput">
                  <option value="efectivo">efectivo</option>
                  <option value="transferencia">transferencia</option>
                  <option value="mercadopago">mercadopago</option>
                  <option value="tarjeta">tarjeta</option>
                  <option value="otro">otro</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Referencia</label>
                <input class="form-control" id="pagoReferenciaInput">
              </div>
              <div class="col-md-6">
                <label class="form-label">Estado</label>
                <select class="form-select" id="pagoEstadoInput">
                  <option value="aprobado">aprobado</option>
                  <option value="pendiente">pendiente</option>
                  <option value="rechazado">rechazado</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" id="pagoObservacionesInput" rows="3"></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="pagoGuardarBtn">Guardar</button>
          </div>
        </div>
      </div>
    </div>
  `);

  document.getElementById("pagoGuardarBtn")?.addEventListener("click", savePagoModal);
}

async function savePagoModal() {
  try {
    const payload = {
      participante_id: Number(document.getElementById("pagoParticipanteInput").value || 0),
      fecha_pago: document.getElementById("pagoFechaInput").value,
      importe: Number(document.getElementById("pagoImporteInput").value || 0),
      medio_pago: document.getElementById("pagoMedioInput").value,
      referencia: document.getElementById("pagoReferenciaInput").value.trim(),
      estado: document.getElementById("pagoEstadoInput").value,
      observaciones: document.getElementById("pagoObservacionesInput").value.trim() || null
    };

    if (!payload.participante_id || !payload.fecha_pago || payload.importe <= 0) {
      throw new Error("Participante, fecha e importe son obligatorios.");
    }

    await apiFetch("/pagos", {
      method: "POST",
      body: JSON.stringify(payload)
    });

    bootstrap.Modal.getInstance(document.getElementById("pagoModal"))?.hide();
    await renderPagos();
  } catch (err) {
    window.alert(err.message);
  }
}

function renderPagoEstadoBadge(estado) {
  const variants = {
    aprobado: "bg-success-subtle text-success-emphasis",
    pendiente: "bg-warning-subtle text-warning-emphasis",
    rechazado: "bg-danger-subtle text-danger-emphasis"
  };
  return `<span class="badge ${variants[estado] || "bg-secondary-subtle text-secondary-emphasis"}">${escapePagoHtml(estado || "")}</span>`;
}

function formatPagoDate(value) {
  return value ? String(value).slice(0, 10) : "";
}

function escapePagoHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
