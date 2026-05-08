async function renderDashboard() {
  const app = document.getElementById("app");
  app.innerHTML = `<div class="text-center py-5">Cargando dashboard...</div>`;

  try {
    const res = await apiFetch("/dashboard");
    const d = res.data;
    const k = d.kpis;

    app.innerHTML = `
      <h2 class="mb-4">Dashboard</h2>

      <div class="row g-3 mb-4">
        ${cardKpi("Participantes activos", k.participantes_activos)}
        ${cardKpi("Profesores activos", k.profesores_activos)}
        ${cardKpi("Grupos activos", k.grupos_activos)}
        ${cardKpi("Clases hoy", k.clases_hoy)}
        ${cardKpi("Asistencias hoy", k.asistencias_hoy)}
        ${cardKpi("Cargos pendientes", k.cargos_pendientes)}
        ${cardKpi("Saldo pendiente", `$ ${Number(k.saldo_pendiente).toLocaleString("es-AR")}`)}
        ${cardKpi("Pagos del mes", `$ ${Number(k.pagos_mes).toLocaleString("es-AR")}`)}
      </div>

      <div class="row g-4">
        <div class="col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5>Próximos vencimientos</h5>
              ${renderVencimientos(d.proximos_vencimientos)}
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5>Últimos pagos</h5>
              ${renderUltimosPagos(d.ultimos_pagos)}
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5>Comprobantes pendientes</h5>
              ${renderComprobantesPendientes(d.comprobantes_pendientes)}
            </div>
          </div>
        </div>
      </div>
    `;
  } catch (err) {
    app.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
  }
}

function cardKpi(label, value) {
  return `
    <div class="col-6 col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted">${label}</div>
          <div class="fs-4 fw-bold">${value}</div>
        </div>
      </div>
    </div>
  `;
}

function renderVencimientos(items) {
  if (!items?.length) return `<p class="text-muted mb-0">Sin vencimientos.</p>`;
  return `<ul class="list-group list-group-flush">
    ${items.map(i => `<li class="list-group-item px-0">
      <strong>${i.apellido}, ${i.nombre}</strong><br>
      <span>${i.concepto}</span><br>
      <small class="text-muted">Vence: ${i.fecha_vencimiento} · Saldo: $ ${Number(i.saldo).toLocaleString("es-AR")}</small>
    </li>`).join("")}
  </ul>`;
}

function renderUltimosPagos(items) {
  if (!items?.length) return `<p class="text-muted mb-0">Sin pagos.</p>`;
  return `<ul class="list-group list-group-flush">
    ${items.map(i => `<li class="list-group-item px-0">
      <strong>${i.apellido}, ${i.nombre}</strong><br>
      <small>${i.fecha_pago} · $ ${Number(i.importe).toLocaleString("es-AR")} · ${i.medio_pago}</small>
    </li>`).join("")}
  </ul>`;
}

function renderComprobantesPendientes(items) {
  if (!items?.length) return `<p class="text-muted mb-0">Sin comprobantes pendientes.</p>`;
  return `<ul class="list-group list-group-flush">
    ${items.map(i => `<li class="list-group-item px-0">
      <strong>${i.apellido}, ${i.nombre}</strong><br>
      <small>${i.archivo_nombre}</small>
    </li>`).join("")}
  </ul>`;
}