<?php
// conexion a base de datos con db.php
require_once __DIR__ . '/../src/db.php';
$pdo = getPDOConnection(); // Esto debe estar antes de pasar $pdo a cualquier clase o método
// conextar con assign_seats.php para traer una clase y sus funciones
require_once __DIR__ . '/../src/prueba.php';
// traemos json de assign_seats.php
header('Content-Type: application/json');
// obtenemos ruta y parametros de consultas
$uri = $_SERVER['REQUEST_URI'];
// obtenemos los metodos, en este caso GET
$method = $_SERVER['REQUEST_METHOD'];

// definimos la ruta principal
$basePath = '/airline_pi/public/indexNuevo.php';
// Eliminamos la ruta base de la uri, y solo obtenemos la parque se usara para el enrutamiento
$path = str_replace($basePath, '', $uri);
// si el metodo es igual a GET y la ruta coincide con el patron del enlace
if ($method === 'GET' && preg_match('#^/flights/(\d+)/passengers$#', $path, $matches)) {
    // extraemos la id del vuelo desde el enlace y lo convertimos a numero entero
    $flightId = (int)$matches[1];
    // creamos una instancia para el controlador de vuelos, pasando la conexion a la base de datos $pdo
    $controller = new Vuelo($pdo);
    // $seats = $controller->enumerarAsientos(2);
    // $controller->assignSeats($flightId);
    // $controler tendra el valor de la funcion publica getPassengersByFlight() de assign_seats.php
    $controller->buscarVuelo($flightId);


    // echo "<pre>";
    // print_r($seats);                                 // Mostramos todos los asientos generados
    // echo "</pre>";
    
} else {
    http_response_code(404);
    echo json_encode([
        "code" => 404,
        "data" => new stdClass()
    ]);
}


?>