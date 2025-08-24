
<?php
// conectamos a base de datos con db.php
require_once __DIR__ . '/db.php';

class Vuelo{
    private $pdo;

    public function __construct($pdo) {
    $this->pdo = $pdo;
    }
                            // funcion publica para obtener pasajeros de cada vuelo
    public function buscarVuelo($flightId){
        // // Obtener vuelo
        $stmt = $this->pdo->prepare("SELECT * FROM flight WHERE flight_id = ?");
        $stmt->execute([$flightId]);
        $flight = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$flight) {
            http_response_code(404);
            echo json_encode(["code" => 404, "data" => new stdClass()], JSON_PRETTY_PRINT);
            exit;
        }
        // Obtener pasajeros
        $stmt = $this->pdo->prepare("
            SELECT p.passenger_id, p.dni, p.name, p.age, p.country,
                bp.boarding_pass_id, bp.purchase_id, bp.seat_type_id, bp.seat_id,
                s.seat_column, s.seat_row
            FROM passenger p
            JOIN boarding_pass bp ON p.passenger_id = bp.passenger_id
            LEFT JOIN seat s ON s.seat_id = bp.seat_id
            WHERE bp.flight_id = ?
            ORDER BY bp.purchase_id ASC, p.passenger_id ASC
        ");
        // decir que la variable indefinida WHERE (flight_id = ?) tendra el valor de $flightId
        $stmt->execute([$flightId]);
        // guardamos todas las columnas solicitadas de la base de datos y las representaremos con una variable nueva
        $pasajeros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    // PASAJEROS CON LOS ASIENTOS YA ASIGNADOS DESDE LA BASE DE DATOS
        $pasajerosConAsientoYaAsignado = [];
        $conteo = 1;
        /*$pasajero recorrera toda la lista de pasajeros de la base de datos, 
        y representara al pasajero Actual en el que se encuentre en ese momento y su información*/
        foreach($pasajeros as $pasajero){
            /* si el pasajero actual tiene un asiento asignado hacer, esto
             quiero decir que si la información del asiento del pasajero actual($pasajero["seat_id"]) es distinto a null hacer*/
            
                // guardar la información deseada del pasajero Actual dentro de un array
                $pasajerosConAsientoYaAsignado[] = [
                    "conteo" => $conteo,
                    "compraId" => (int)$pasajero["purchase_id"],
                    "clase" => (int)$pasajero["seat_type_id"],
                    "pasajeroId" => (int)$pasajero["passenger_id"],
                    "nombre" => $pasajero["name"],
                    "edad" => (int)$pasajero["age"],
                    "asientoId" => (int)$pasajero["seat_id"],
                    "butaca" => $pasajero["seat_row"] . $pasajero["seat_column"]
                ];
                $conteo++;
            
        }
       
        




        // ordenar arrays por compra, aunque los datos hayan sido agregados mas tarde
        // usort($pasajerosConAsientoYaAsignado, function ($a, $b) {
        //     return $a['compraId'] <=> $b['compraId'];
        // });
        // JSON
        echo json_encode([
            "code" => 200,
            "data" => [
                "flightId" => (int)$flight["flight_id"],
                "takeoffDateTime" => $flight["takeoff_date_time"],
                "takeoffAirport" => $flight["takeoff_airport"],
                "landingDateTime" => $flight["landing_date_time"],
                "landingAirport" => $flight["landing_airport"],
                "airplaneId" => (int)$flight["airplane_id"],
                "passengers" => $pasajerosConAsientoYaAsignado
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);


       
    }









                                    // FUNCIÓN PARA ENUMERAR ASIENTOS
    public function enumerarAsientos($flightId) {
        $pdo = $this->pdo;
        // // Estructura de aviones por clase, columnas y asientos
        $estructuraAviones = [
            1 => [ // Avión 1
                1 => ['columns' => [['A', 'B'], ['F', 'G']], 'rows' => range(1, 4)],
                2 => ['columns' => [['A', 'B', 'C'], ['E', 'F', 'G']], 'rows' => range(8, 15)],
                3 => ['columns' => [['A', 'B', 'C'], ['E', 'F', 'G']], 'rows' => range(19, 34)],
            ],
            2 => [ // Avión 2
                1 => ['columns' => [['A'], ['E'], ['I']], 'rows' => range(1, 5)],
                2 => ['columns' => [['A', 'B'], ['D', 'E', 'F'], ['H', 'I']], 'rows' => range(9, 14)],
                3 => ['columns' => [['A', 'B'], ['D', 'E', 'F'], ['H', 'I']], 'rows' => range(18, 31)],
            ]
        ];
    
        // Obtener el avión del vuelo
        /*seleccionamos la columna aisplane_id dentro de la tabla flight(vuelos) y WHERE no permitira
        elegir un vuelo especifico dentro de la tabla, por el momento lo dejamos indefinido para posteriormente
        poder darle valor*/
        $stmt = $pdo->prepare("SELECT airplane_id FROM flight WHERE flight_id = ?");
        // decir que la variable indefinida(flight_id = ?) dentra el valor de $flightId
        $stmt->execute([$flightId]);
        // guardaremos la informacion recuperada de la base de datos flight, en este caso solo estamos guardando airplane_id
        $avion = $stmt->fetch(PDO::FETCH_ASSOC);
        // si deacuerdo al vuelo seleccionado no existe informacion de un avion otorgado a ese vuelo, retornar vacio
        if (!$avion) return [];
        // guardamos en una variable la id del avion de un vuelo especificado anteriormente.
        $avionId = (int)$avion['airplane_id'];
        // si no existe el avion dentro de $estructuraAviones returnar vacio
        if (!isset($estructuraAviones[$avionId])) return [];
        // variable para manipular la estructura de un avion especifico
         $avionDisponibleEst = $estructuraAviones[$avionId];
         // Paso 1: obtener todas las columnas únicas en orden, recorriendo las clases en orden
        //  variable para almacenar todas las columnas
        $todasColumnas = [];
        // $clase recibe las diferentes clases de un avion especifico, $clase = clase1, clase2, clase 3
        foreach ($avionDisponibleEst as $clase) {
            /*accedemos a las columnas de cada clase con $clase['columns'] y $grupo recibe las 
            distintas columnas(clase 1 -> columnas a-b, f-g. luego sigue con la siguiente clase y guarda todo)*/
            foreach ($clase['columns'] as $grupo) {
                /* $columna recorre cada columna dentro de cada clase dentro de $grupo 
                guardandolas de manera general sin distincion de clases*/
                foreach ($grupo as $columna) {
                    /* si la columna actual no esta dentro del array $todasColumnas[] entrar y hacer
                     (si la columna ya existe en el arreglo no entrara al if)*/
                    if (!in_array($columna, $todasColumnas)) {
                        /*guardar en el array $todasColumnas[], la columna actual y repetir proceso con todas las columnas,
                        dependiendo de la estructura estara en orden o desordenado las columnas.
                        $todasColumnas = [A,C,B,D,E,G,F]*/
                        $todasColumnas[] = $columna;
                    }
                }
            }
        }

        // con sort() ordenamos las columnas alfabéticamente para asegurar un orden natural (A, B, C, ...)
        sort($todasColumnas);
        // Paso 2: recorrer cada columna y dentro de ella todas las filas de todas las clases que tengan esa columna
        // variable para los asientos
        $numerosAsientos= [];
        // variable para comenzar conteo de asientos desde el numero uno
        $asientoActual = 1;
        // $columna representara a cada columna dentro del array, pero representa una columna a la vez por cada vuelta
        foreach ($todasColumnas as $columna) {
            // $clase representa a cada clase del avion, pero representa una clase a la vez
            foreach ($avionDisponibleEst as $claseNum => $clase) {
                // $filas recibe todas las filas de la clase actual
                $filas = $clase['rows'];
                // bandera para saber si la clase actual contiene a la columna actual
                $existeColumna = false;
                // $grupo representa a los grupos de columnas de la clase actual
                foreach ($clase['columns'] as $grupo) {
                    // si la columna actual existe dentro del grupo de columnas de la clase actual hacer
                    if (in_array($columna, $grupo)) {
                        // decir que si existe la columna actual dentro del grupo de columnas de esa clase
                        $existeColumna = true;
                        break;
                    }
                }
                // si la columna existe en la clase actual hacer
                if ($existeColumna) {
                    // Recorrer todas las filas para esa clase y columna
                    /*$filas representa a los grupos de filas dentro de la clase actual 
                    y $fila sirve para recorrer cada fila dentro de $filas, representando una fila a la vez*/
                    foreach ($filas as $fila) {
                        // $asiento sera igual a la fila actual($fila) convinada con columna actual($columna)
                        $asiento = $fila . $columna;
                        /* dentro de $numerosAsientos[] guardaremos el valor de asiento(1A,2A,1B,...) y le daremos 
                        el valor de la variable $asientoActual y procederemos a incrementar por un numero esta variable*/
                        $numerosAsientos[$asiento] = [
                            'numero' => $asientoActual++,
                            'clase' => $claseNum
                        ];
                    }
                }
            }
        }
        return $numerosAsientos; // Devuelve un array con clave asiento y valor número asignado
        
    }

}
?>
