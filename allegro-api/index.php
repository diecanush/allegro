<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/middleware/auth_middleware.php';

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/ParticipantesController.php';
require_once __DIR__ . '/controllers/ActividadesController.php';
require_once __DIR__ . '/controllers/ProfesoresController.php';
require_once __DIR__ . '/controllers/GruposController.php';
require_once __DIR__ . '/controllers/ClasesController.php';
require_once __DIR__ . '/controllers/AsistenciasController.php';
require_once __DIR__ . '/controllers/InscripcionesController.php';

require_once __DIR__ . '/controllers/PlanesController.php';
require_once __DIR__ . '/controllers/ParticipantePlanesController.php';
require_once __DIR__ . '/controllers/CargosController.php';
require_once __DIR__ . '/controllers/PagosController.php';

require_once __DIR__ . '/controllers/ComprobantesPagoController.php';
require_once __DIR__ . '/controllers/EstadoCuentaController.php';
require_once __DIR__ . '/controllers/RecordatoriosController.php';

require_once __DIR__ . '/controllers/DashboardController.php';

require_once __DIR__ . '/controllers/MiCuentaController.php';

$router = new Router();

$router->add('GET', '/', function () {
    Response::success([
        'app' => 'Allegro Fit API',
        'version' => '1.0.0'
    ], 'API funcionando');
});

$router->add('POST', '/auth/login', [AuthController::class, 'login']);
$router->add('GET', '/auth/me', [AuthController::class, 'me']);
$router->add('POST', '/auth/logout', [AuthController::class, 'logout']);

$router->add('GET', '/participantes', [ParticipantesController::class, 'index']);
$router->add('GET', '/participantes/{id}', [ParticipantesController::class, 'show']);
$router->add('POST', '/participantes', [ParticipantesController::class, 'store']);

$router->add('GET', '/actividades', [ActividadesController::class, 'index']);
$router->add('POST', '/actividades', [ActividadesController::class, 'store']);
$router->add('GET', '/profesores', [ProfesoresController::class, 'index']);
$router->add('GET', '/profesores/{id}', [ProfesoresController::class, 'show']);
$router->add('POST', '/profesores', [ProfesoresController::class, 'store']);
$router->add('PUT', '/profesores/{id}', [ProfesoresController::class, 'update']);
$router->add('DELETE', '/profesores/{id}', [ProfesoresController::class, 'delete']);

$router->add('PUT', '/participantes/{id}', [ParticipantesController::class, 'update']);
$router->add('DELETE', '/participantes/{id}', [ParticipantesController::class, 'delete']);

$router->add('GET', '/actividades/{id}', [ActividadesController::class, 'show']);
$router->add('PUT', '/actividades/{id}', [ActividadesController::class, 'update']);
$router->add('DELETE', '/actividades/{id}', [ActividadesController::class, 'delete']);

$router->add('GET', '/grupos', [GruposController::class, 'index']);
$router->add('GET', '/grupos/{id}', [GruposController::class, 'show']);
$router->add('POST', '/grupos', [GruposController::class, 'store']);
$router->add('PUT', '/grupos/{id}', [GruposController::class, 'update']);
$router->add('DELETE', '/grupos/{id}', [GruposController::class, 'delete']);

$router->add('GET', '/clases', [ClasesController::class, 'index']);
$router->add('GET', '/clases/{id}', [ClasesController::class, 'show']);
$router->add('POST', '/clases', [ClasesController::class, 'store']);
$router->add('PUT', '/clases/{id}', [ClasesController::class, 'update']);
$router->add('DELETE', '/clases/{id}', [ClasesController::class, 'delete']);

$router->add('GET', '/asistencias', [AsistenciasController::class, 'index']);
$router->add('POST', '/asistencias', [AsistenciasController::class, 'store']);
$router->add('POST', '/asistencias/bulk', [AsistenciasController::class, 'storeBulk']);
$router->add('DELETE', '/asistencias/{id}', [AsistenciasController::class, 'delete']);

$router->add('GET', '/inscripciones', [InscripcionesController::class, 'index']);
$router->add('GET', '/inscripciones/{id}', [InscripcionesController::class, 'show']);
$router->add('POST', '/inscripciones', [InscripcionesController::class, 'store']);
$router->add('PUT', '/inscripciones/{id}', [InscripcionesController::class, 'update']);
$router->add('DELETE', '/inscripciones/{id}', [InscripcionesController::class, 'delete']);

$router->add('GET', '/grupos/{id}/participantes', [InscripcionesController::class, 'participantesPorGrupo']);
$router->add('GET', '/participantes/{id}/grupos', [InscripcionesController::class, 'gruposPorParticipante']);

$router->add('GET', '/planes', [PlanesController::class, 'index']);
$router->add('GET', '/planes/{id}', [PlanesController::class, 'show']);
$router->add('POST', '/planes', [PlanesController::class, 'store']);
$router->add('PUT', '/planes/{id}', [PlanesController::class, 'update']);
$router->add('DELETE', '/planes/{id}', [PlanesController::class, 'delete']);

$router->add('GET', '/participante-planes', [ParticipantePlanesController::class, 'index']);
$router->add('POST', '/participante-planes', [ParticipantePlanesController::class, 'store']);
$router->add('PUT', '/participante-planes/{id}', [ParticipantePlanesController::class, 'update']);
$router->add('DELETE', '/participante-planes/{id}', [ParticipantePlanesController::class, 'delete']);

$router->add('GET', '/cargos', [CargosController::class, 'index']);
$router->add('POST', '/cargos', [CargosController::class, 'store']);
$router->add('PUT', '/cargos/{id}', [CargosController::class, 'update']);
$router->add('DELETE', '/cargos/{id}', [CargosController::class, 'delete']);

$router->add('GET', '/pagos', [PagosController::class, 'index']);
$router->add('GET', '/pagos/{id}', [PagosController::class, 'show']);
$router->add('POST', '/pagos', [PagosController::class, 'store']);
$router->add('DELETE', '/pagos/{id}', [PagosController::class, 'delete']);

$router->add('GET', '/comprobantes-pago', [ComprobantesPagoController::class, 'index']);
$router->add('GET', '/comprobantes-pago/{id}', [ComprobantesPagoController::class, 'show']);
$router->add('POST', '/comprobantes-pago', [ComprobantesPagoController::class, 'store']);
$router->add('PUT', '/comprobantes-pago/{id}/validar', [ComprobantesPagoController::class, 'validar']);
$router->add('DELETE', '/comprobantes-pago/{id}', [ComprobantesPagoController::class, 'delete']);

$router->add('GET', '/estado-cuenta/{participanteId}', [EstadoCuentaController::class, 'participante']);

$router->add('GET', '/recordatorios', [RecordatoriosController::class, 'index']);
$router->add('POST', '/recordatorios', [RecordatoriosController::class, 'store']);
$router->add('PUT', '/recordatorios/{id}', [RecordatoriosController::class, 'update']);
$router->add('DELETE', '/recordatorios/{id}', [RecordatoriosController::class, 'delete']);

$router->add('GET', '/dashboard', [DashboardController::class, 'resumen']);

$router->add('GET', '/mi-perfil', [MiCuentaController::class, 'perfil']);
$router->add('GET', '/mis-grupos', [MiCuentaController::class, 'misGrupos']);
$router->add('GET', '/mis-asistencias', [MiCuentaController::class, 'misAsistencias']);
$router->add('GET', '/mi-estado-cuenta', [MiCuentaController::class, 'miEstadoCuenta']);
$router->add('GET', '/mis-pagos', [MiCuentaController::class, 'misPagos']);
$router->add('GET', '/mis-comprobantes', [MiCuentaController::class, 'misComprobantes']);
$router->add('POST', '/mis-comprobantes', [MiCuentaController::class, 'subirComprobante']);

try {
    $router->dispatch(Request::method(), Request::uri());
} catch (Throwable $e) {
    Response::error('Error interno del servidor', 500, $e->getMessage());
}