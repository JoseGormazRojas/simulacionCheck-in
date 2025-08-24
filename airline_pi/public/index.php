
<?php
require_once __DIR__ . '/../src/db.php';
$pdo = getPDOConnection(); // Esto debe estar antes de pasar $pdo a cualquier clase o método
require_once __DIR__ . '/../src/FlightController.php';

header('Content-Type: application/json');

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Ajusta esto si tu proyecto está en otro subdirectorio
$basePath = '/airline_pi/public';

$path = str_replace($basePath, '', $uri);

if ($method === 'GET' && preg_match('#^/flights/(\d+)/passengers$#', $path, $matches)) {
    $flightId = (int)$matches[1];
    $controller = new Vuelo($pdo);
    $controller->buscarVuelo($flightId);
} else {
    http_response_code(404);
    echo json_encode([
        "code" => 404,
        "data" => new stdClass()
    ]);
}










































// require_once __DIR__ . '/../src/db.php';
// require_once __DIR__ . '/../src/FlightController.php';

// header('Content-Type: application/json');

// $uri = $_SERVER['REQUEST_URI'];
// $method = $_SERVER['REQUEST_METHOD'];

// // Ruta base del proyecto (ajusta si es necesario)
// $basePath = '/airline_pi/public'; 

// // Eliminar la ruta base del path actual
// $path = str_replace($basePath, '', parse_url($uri, PHP_URL_PATH));

// // Rutas disponibles

// // 1. GET /flights/{id}/passengers
// if ($method === 'GET' && preg_match('#^/flights/(\d+)/passengers$#', $path, $matches)) {
//     $flightId = (int)$matches[1];
//     $controller = new FlightController($pdo);
//     $controller->getPassengersByFlightId($flightId);
//     exit;
// }

// // 2. POST /flights/{id}/assign-seats
// if ($method === 'POST' && preg_match('#^/flights/(\d+)/assign-seats$#', $path, $matches)) {
//     $flightId = (int)$matches[1];
//     $controller = new FlightController($pdo);
//     $controller->assignSeats($flightId);
//     exit;
// }

// // Ruta no encontrada
// http_response_code(404);
// echo json_encode([
//     "code" => 404,
//     "message" => "Ruta no encontrada"
// ]);








































// require_once __DIR__ . '/../src/db.php';
// require_once __DIR__ . '/../src/FlightController.php';

// header('Content-Type: application/json');

// $uri = $_SERVER['REQUEST_URI'];
// $method = $_SERVER['REQUEST_METHOD'];

// // Define la ruta base según el subdirectorio del proyecto
// $basePath = '/airline_pi/public'; // ⚠️ CAMBIA ESTO si tu carpeta se llama diferente

// $path = str_replace($basePath, '', $uri);

// var_dump($path);
// // exit;

// // Ruta esperada: /flights/:id/passengers
// if ($method === 'GET' && preg_match('#^/flights/(\d+)/passengers$#', $path, $matches)) {
//     $flightId = (int)$matches[1];
//     $controller = new FlightController($pdo);
//     $controller->getPassengersByFlightId($flightId);
// } else {
//     http_response_code(404);
//     echo json_encode([
//         "code" => 404,
//         "data" => new stdClass()
//     ]);
// }

?>