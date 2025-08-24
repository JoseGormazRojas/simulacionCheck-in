
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
         // Obtenemos los asientos ya enumerados desde la funcion enumerarAsientos
        $asientosEnumerados = $this->enumerarAsientos($flightId);
        
        $pasajerosConAsientoYaAsignado = [];
        // variables para guardar variables de la columna pasajeroId dentro del array $pasajerosConAsientoYaAsignado
        $idsAsignadosConAsiento = array_column($pasajerosConAsientoYaAsignado, 'pasajeroId');
        $idCompraPasajeroConAsiento = array_column($pasajerosConAsientoYaAsignado, 'compraId');
        // variable de prueba borrar posteriormente
        $conteo= 1;
        foreach($pasajeros as $pasajero){
            /* si el pasajero actual tiene un asiento asignado hacer, esto
             quiero decir que si la información del asiento del pasajero actual($pasajero["seat_id"]) es distinto a null hacer*/
            if(!is_null($pasajero["seat_id"])){
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
        }
                    /* LLAMAR A FUNCION CON LA ESTRUCTURA DEL AVION Y SU NUMERACION COMPLETA,
                    MARCAR COMO ASIENTO OCUPADO LOS ASIENTOS YA ASIGNADOS DESDE LA BASE DE DATOS Y 
                    AGRUPAR EN UNA VARIABLE LOS ASIENTOS DISPONIBLES */
        // Obtenemos los asientos ya enumerados desde la funcion enumerarAsientos
        $asientosEnumerados = $this->enumerarAsientos($flightId);
        

        // columnas
        $todasColumnas = array_unique(array_map(function ($asiento) {
            return preg_replace('/[0-9]/', '', $asiento);
        }, array_keys($asientosEnumerados)));
        sort($todasColumnas); // orden alfabético
        // filas
        $todasFilas = array_unique(array_map(function ($asiento) {
            return (int)preg_replace('/[^0-9]/', '', $asiento); // Devuelve la fila como número
        }, array_keys($asientosEnumerados)));
        sort($todasFilas); // Orden numerico
        // clases
        $todasClases = array_unique(array_column($asientosEnumerados, 'clase'));
        sort($todasClases); // Orden numerico

        // marcar asientos como ocupados
        // variable
        $asientosOcupados = [];
        // marcar asientos metiendo la butaca dentro de un array
        foreach ($pasajerosConAsientoYaAsignado as $pasajero) {
            if (!is_null($pasajero['asientoId'])) {
                $asientosOcupados[] = $pasajero['butaca']; // Ej: "12B"
            }
        }
        // eliminamos las butacas ya asignadas dejando para el uso solo las butacas disponibles y las guardamos en una variable
        $asientosDisponibles = array_diff_key($asientosEnumerados, array_flip($asientosOcupados));

        /*$pasajero recorrera toda la lista de pasajeros de la base de datos, 
        y representara al pasajero Actual en el que se encuentre en ese momento y su información*/
        foreach($pasajeros as $idx => $pasajero1){
            // si necesitas modificarlo, hazlo así
            $nuevoPasajero1 = $pasajero1;
            // variables para guardar variables de la columna pasajeroId dentro del array $pasajerosConAsientoYaAsignado
            $idsAsignadosConAsiento = array_column($pasajerosConAsientoYaAsignado, 'pasajeroId');
            
            if(in_array($nuevoPasajero1['passenger_id'], $idsAsignadosConAsiento)){
                continue;
            }
            // si el pasajero es menor de edad, hacer
            else if($nuevoPasajero1['age'] < 18){
                
                
                // buscaremos al grupo del menor
                foreach($pasajeros as $idx2 =>$pasajero2){
                    // si necesitas modificarlo, hazlo así
                    $nuevoPasajero2 = $pasajero2;
                    /* si el pasajero2 pertenece al grupo familiar del menor de edad,que tenga una id distinta 
                    al menor, si es mayor de edad y*/
                    if(
                        $nuevoPasajero2['purchase_id'] === $nuevoPasajero1['purchase_id'] && 
                        $nuevoPasajero2['passenger_id'] !== $nuevoPasajero1['passenger_id'] &&
                        $nuevoPasajero2['age'] > 18 && 
                        in_array($nuevoPasajero2['passenger_id'], $idsAsignadosConAsiento)
                        ){
                            // variable array para guardar posibles candidatos
                            $asientosCandidatos = [];
                            // butaca  y clase del pasajero2
                            $columna = $nuevoPasajero2['seat_column'];
                            $fila = $nuevoPasajero2['seat_row'];
                            
                            // index de array de columnas y de filas dentro de $enumerarAsientos
                            $indexColumna = array_search($columna, $todasColumnas);
                            $indexFila = array_search($fila, $todasFilas);
                           
                            /* si index -1 existe dentro de $todasColumnas hacer, esto quiere decir que 
                            si index es 2(representa a la columna 3 dentro de $todasColumnas) entonces 
                            index -1 = 1 (representando a la columna 2)*/
                            if (isset($todasColumnas[$indexColumna - 1])) {
                                /* variable para guardar el numero la fila proporcionado por $fila el cual no se a modificado,
                                y lo fucione con el valor de la columna index deseada dentro de $todasColumnas*/
                                $candidato = $fila . $todasColumnas[$indexColumna - 1];
                                // si el $posibleAsiento no existe dentro de $asientosOcupados, hacer
                                if(!in_array($candidato, $asientosOcupados)){
                                    // guardaremos ese asiento dentro del array $asientosCandidatos
                                    $asientosCandidatos[] = $candidato;
                                }
                            }
                            if (isset($todasColumnas[$indexColumna + 1])) {
                                /* variable para guardar el numero la fila proporcionado por $fila el cual no se a modificado,
                                y lo fucione con el valor de la columna index deseada dentro de $todasColumnas*/
                                $candidato = $fila . $todasColumnas[$indexColumna + 1];
                                // si el $posibleAsiento no existe dentro de $asientosOcupados, hacer
                                if(!in_array($candidato, $asientosOcupados)){
                                    // guardaremos ese asiento dentro del array $asientosCandidatos
                                    $asientosCandidatos[] = $candidato;
                                }
                            }
                            // if por filas
                            // si existe la posicion o indice antes  de la posicion del index del pasajero 2 , hacer
                            if (isset($todasFilas[$indexFila - 1])) {
                                // damos valor a esa fila
                                $filaAnterior = $fila - 1;
                                // guardamos esa butaca como un candidato momentaneo
                                $candidato = $filaAnterior . $columna;
                                // si no existe el candidato dentro del array de los asientos ocupados, hacer
                                if(!in_array($candidato, $asientosOcupados)){
                                    // guardamos la clase de esa butaca que se encuentra en la estructura del avion en una variable
                                    $candidatoClase = $asientosEnumerados[$candidato]['clase'];
                                    // si la clase de esa butaca es igual a la clase del pasajero menor de edad, hacer
                                    if($candidatoClase === $nuevoPasajero1["clase"]){
                                        // guardaremos ese asiento dentro del array $asientosCandidatos
                                        $asientosCandidatos[] = $candidato;
                                    }
                                }
                                
                            }
                            // si existe la posicion o indice siguiente  de la posicion del index del pasajero 2 , hacer
                            if (isset($todasFilas[$indexFila + 1])) {
                                // damos valor a esa fila
                                $filaSiguiente = $fila + 1;
                                // guardamos esa butaca como un candidato momentaneo
                                $candidato = $filaSiguiente . $columna;
                                // si no existe el candidato dentro del array de los asientos ocupados, hacer
                                if(in_array($candidato, $asientosDisponibles)){
                                    // guardamos la clase de esa butaca que se encuentra en la estructura del avion en una variable
                                    $candidatoClase = $asientosDisponibles[$candidato]['clase'];
                                    // si la clase de esa butaca es igual a la clase del pasajero menor de edad, hacer
                                    if($candidatoClase === $nuevoPasajero1["seat_type_id"]){
                                        // guardaremos ese asiento dentro del array $asientosCandidatos
                                        $asientosCandidatos[] = $candidato;
                                    }
                                }
                            }
                            // // si no hay asientos disponibles al lado del asiento del adulto, hacer
                            // if(!$asientosCandidatos){
                            //     // quitar asiento a pasajero 2 para asignar un nuevo asiento
                            //     foreach($pasajerosConAsientoYaAsignado as $idx3 =>$pasajero2){
                            //         $pasajero2_1 = $pasajero2['pasajeroId'];
                            //         if($pasajero2_1 === $nuevoPasajero2['passenger_id']){
                            //             // unset($pasajerosConAsientoYaAsignado[$idx3]);
                            //             $pasajero2['asientoId'] = null;
                            //             $pasajero2['butaca'] = null;
                            //             unset($asientosOcupados[$pasajero2['butaca']]);
                            //             $asientosDisponibles[] = $pasajero2['butaca'];
                            //             // $pasajero2['seat_column'] = null;
                            //             $nuevoCandidato1 = [];
                            //             $nuevoCandidato2 = [];

                            //             foreach($asientosDisponibles as $asiento => $infoButaca){
                            //                 if($infoButaca['clase'] === $pasajero2['clase']){
                            //                     // Obtener fila y columna del asiento candidato actual
                            //                     preg_match('/(\d+)([A-Z])/', $asiento, $matches);
                            //                     // fila
                            //                     $filaAsiento = $matches[1];
                            //                     // columna
                            //                     $columnaAsiento = $matches[2];
                            //                     /* guardamos la posicion de  la columna dentro de todasColumnas y la 
                            //                     guardamos en index. [A,B,D,E,F] = posicion[0,1,2,3,4] */
                            //                     $indexFila = array_search($filaAsiento, $todasFilas);
                            //                     $indexColumna = array_search($columnaAsiento, $todasColumnas);
                            //                     // buscar posibles asientos a candidato
                            //                     // por columna
                            //                     if(isset($todasColumnas[$indexColumna - 1])){
                            //                         $candidato = $filaAsiento . $todasColumnas[$indexColumna - 1];
                            //                         // si la butaca no existe dentro de los asientos ya ocupados
                            //                         if(!in_array($candidato, $asientosOcupados)){
                            //                             $nuevoCandidato1[] = $candidato;
                            //                             $nuevoCandidato2[] = $asiento;
                            //                         }
                            //                     }
                            //                     if(isset($todasColumnas[$indexColumna + 1])){
                            //                         $candidato = $filaAsiento . $todasColumnas[$indexColumna + 1];
                            //                         // si la butaca no existe dentro de los asientos ya ocupados
                            //                         if(!in_array($candidato, $asientosOcupados)){
                            //                             $nuevoCandidato1[] = $candidato;
                            //                             $nuevoCandidato2[] = $asiento;
                            //                         }
                            //                     }
                            //                     // por filas
                            //                     if(isset($todasFilas[$indexFila - 1])){
                            //                         $candidato = $filaAsiento - 1 . $columnaAsiento;
                            //                         // $claseCandidato = $asientosDisponibles[$candidato]['clase'];
                            //                         // si la butaca no existe dentro de los asientos ya ocupados
                            //                         if(!in_array($candidato, $asientosOcupados) && in_array($candidato, $asientosEnumerados)){
                            //                             $claseCandidato = $asientosEnumerados[$candidato]['clase'];
                            //                             if($claseCandidato === $infoButaca['clase']){
                            //                                 $nuevoCandidato1[] = $candidato;
                            //                                 $nuevoCandidato2[] = $asiento;
                            //                             }
                                                        
                            //                         }
                            //                     }
                            //                     if(isset($todasFilas[$indexFila + 1])){
                            //                         $candidato = $filaAsiento + 1 . $columnaAsiento;
                            //                         // $claseCandidato = $asientosDisponibles[$candidato]['clase'];
                            //                         // si la butaca no existe dentro de los asientos ya ocupados
                            //                         if(!in_array($candidato, $asientosOcupados) && in_array($candidato, $asientosEnumerados)){
                            //                             $claseCandidato = $asientosEnumerados[$candidato]['clase'];
                            //                             if($claseCandidato === $infoButaca['clase']){
                            //                                 $nuevoCandidato1[] = $candidato;
                            //                                 $nuevoCandidato2[] = $asiento;
                            //                             }
                                                        
                            //                         }
                            //                     }  
                            //                 }
                            //             }
                            //             if($nuevoCandidato1 && $nuevoCandidato2){
                            //                 for($i = 0; $i < count($nuevoCandidato1); $i++){
                            //                     $posibleAsiento1 = $nuevoCandidato1[$i];
                            //                     $posibleAsiento2 = $nuevoCandidato2[$i];
                            //                     if(isset($asientosDisponibles[$posibleAsiento1]) && isset($asientosDisponibles[$posibleAsiento2])){
                            //                         // Obtener fila y columna del asiento candidato actual
                            //                         preg_match('/(\d+)([A-Z])/', $posibleAsiento1, $matches);
                            //                         // Obtener fila y columna del asiento candidato actual
                            //                         preg_match('/(\d+)([A-Z])/', $posibleAsiento2, $matches2);
                            //                         // fila del asiento del menor
                            //                         $filaAsientoMenor = $matches[1];
                            //                         // columna del asiento del menor
                            //                         $columnaAsientoMenor = $matches[2];
                            //                         // fila del asiento del menor
                            //                         $filaAsientoAdulto = $matches2[1];
                            //                         // columna del asiento del menor
                            //                         $columnaAsientoAdulto = $matches2[2];
                            //                         // damos valor a las columnas sin valores del pasajero menor de edad
                            //                         $nuevoPasajero1['seat_id'] = $asientosDisponibles[$posibleAsiento1]['numero']; 
                            //                         $nuevoPasajero1['seat_column'] = $columnaAsientoMenor;
                            //                         $nuevoPasajero1['seat_row'] = $filaAsientoMenor;  
                            //                         // actualizar variables con información de asientos 
                            //                         $asientosOcupados[] = $posibleAsiento1;
                            //                         unset($asientosDisponibles[$posibleAsiento1]);
                            //                         unset($asientosEnumerados[$posibleAsiento1]); 

                            //                         // damos valor a las columnas sin valores del pasajero mayor de edad
                            //                         $pasajero2['asientoId'] = $asientosDisponibles[$posibleAsiento2]['numero']; 
                            //                         $pasajero2['butaca'] = $filaAsientoAdulto . $columnaAsientoAdulto;
                            //                         // $pasajero2['seat_row'] = $filaAsientoAdulto; 
                            //                         // actualizar variables con información de asientos 
                            //                         $asientosOcupados[] = $pasajero2['butaca'];
                            //                         unset($asientosDisponibles[$pasajero2['butaca']]);
                            //                         unset($asientosEnumerados[$pasajero2['butaca']]); 
                            //                         // Si el pasajero no tiene asiento, pero está en la misma compra que otro, lo agregamos también
                            //                         $pasajerosConAsientoYaAsignado[] = [
                            //                             "conteo" => $conteo,
                            //                             "compraId" => (int)$nuevoPasajero1["purchase_id"],
                            //                             "clase" => (int)$nuevoPasajero1["seat_type_id"],
                            //                             "pasajeroId" => (int)$nuevoPasajero1["passenger_id"],
                            //                             "nombre" => $nuevoPasajero1["name"],
                            //                             "edad" => (int)$nuevoPasajero1["age"],
                            //                             "asientoId" => (int)$nuevoPasajero1["seat_id"],
                            //                             "butaca" => $nuevoPasajero1["seat_row"] . $nuevoPasajero1["seat_column"]
                            //                         ];
                            //                         $conteo++;
                            //                         // // Si el pasajero no tiene asiento, pero está en la misma compra que otro, lo agregamos también
                            //                         // $pasajerosConAsientoYaAsignado[] = [
                            //                         //     "conteo" => $conteo,
                            //                         //     "compraId" => (int)$nuevoPasajero2["purchase_id"],
                            //                         //     "clase" => (int)$nuevoPasajero2["seat_type_id"],
                            //                         //     "pasajeroId" => (int)$nuevoPasajero2["passenger_id"],
                            //                         //     "nombre" => $nuevoPasajero2["name"],
                            //                         //     "edad" => (int)$nuevoPasajero2["age"],
                            //                         //     "asientoId" => (int)$nuevoPasajero2["seat_id"],
                            //                         //     "butaca" => $nuevoPasajero2["seat_row"] . $nuevoPasajero2["seat_column"]
                            //                         // ];
                            //                         // $conteo++;
                            //                         break 3; 
                            //                     }
                            //                 }
                            //             }


                            //         }
                            //     }

                                
                                
                               
                                
                                
                                
                            // }
                            if($asientosCandidatos){
                                // Buscar el primer asiento disponible
                                foreach ($asientosCandidatos as $asiento) {
                                    if(isset($asientosDisponibles[$asiento]) && $asientosDisponibles[$asiento]['clase'] === $nuevoPasajero1['seat_type_id']){
                                        // Obtener fila y columna del asiento candidato actual
                                        preg_match('/(\d+)([A-Z])/', $asiento, $matches);
                                        // fila
                                        $filaAsiento = $matches[1];
                                        // columna
                                        $columnaAsiento = $matches[2];
                                        // damos valor a las columna sin valores del pasajero menor de edad
                                        $nuevoPasajero1['seat_id'] = $asientosEnumerados[$asiento]['numero']; 
                                        $nuevoPasajero1['seat_column'] = $columnaAsiento;
                                        $nuevoPasajero1['seat_row'] = $filaAsiento; 
                                        // actualizar variables con información de asientos 
                                        $asientosOcupados[] = $asiento;
                                        unset($asientosDisponibles[$asiento]);   
                                        unset($asientosEnumerados[$asiento]); 
                                        // break; // salir de ambos foreach
                                        // Si el pasajero no tiene asiento, pero está en la misma compra que otro, lo agregamos también
                                        $pasajerosConAsientoYaAsignado[] = [
                                            "conteo" => $conteo,
                                            "compraId" => (int)$nuevoPasajero1["purchase_id"],
                                            "clase" => (int)$nuevoPasajero1["seat_type_id"],
                                            "pasajeroId" => (int)$nuevoPasajero1["passenger_id"],
                                            "nombre" => $nuevoPasajero1["name"],
                                            "edad" => (int)$nuevoPasajero1["age"],
                                            "asientoId" => (int)$nuevoPasajero1["seat_id"],
                                            "butaca" => $nuevoPasajero1["seat_row"] . $nuevoPasajero1["seat_column"]
                                        ];
                                        $conteo++;
                                        // cerrar foreach
                                        break 2;
                                    } 
                                }
                                
                            }
                            
                            
                    }
                    else if(
                        $nuevoPasajero2['purchase_id'] === $nuevoPasajero1['purchase_id'] && 
                        $nuevoPasajero2['passenger_id'] !== $nuevoPasajero1['passenger_id'] &&
                        $nuevoPasajero2['age'] > 18 && 
                        !in_array($nuevoPasajero2['passenger_id'], $idsAsignadosConAsiento)
                    ){
                        // variable array para guardar posibles candidatos
                        $asientosCandidatos = [];
                        $asientoPasajero2 = [];
                        foreach($asientosEnumerados as $butaca => $infoButaca){
                            if($infoButaca['clase'] === $nuevoPasajero2['seat_type_id']){
                                // Obtener fila y columna del asiento candidato actual
                                preg_match('/(\d+)([A-Z])/', $butaca, $matches);
                                // fila
                                $filaAsiento = $matches[1];
                                // columna
                                $columnaAsiento = $matches[2];
                                /* guardamos la posicion de  la columna dentro de todasColumnas y la 
                                guardamos en index. [A,B,D,E,F] = posicion[0,1,2,3,4] */
                                $indexFila = array_search($filaAsiento, $todasFilas);
                                $indexColumna = array_search($columnaAsiento, $todasColumnas);
                                // buscar posibles asientos a candidato
                                // por columna
                                if(isset($todasColumnas[$indexColumna - 1])){
                                    $candidato = $filaAsiento . $todasColumnas[$indexColumna - 1];
                                    // si la butaca no existe dentro de los asientos ya ocupados
                                    if(!in_array($candidato, $asientosOcupados)){
                                        $asientosCandidatos[] = $candidato;
                                        $asientoPasajero2[] = $butaca;
                                    }
                                }
                                if(isset($todasColumnas[$indexColumna + 1])){
                                    $candidato = $filaAsiento . $todasColumnas[$indexColumna + 1];
                                    // si la butaca no existe dentro de los asientos ya ocupados
                                    if(!in_array($candidato, $asientosOcupados)){
                                        $asientosCandidatos[] = $candidato;
                                        $asientoPasajero2[] = $butaca;
                                    }
                                }
                                // por filas
                                if(isset($todasFilas[$indexFila - 1])){
                                    $candidato = $filaAsiento - 1 . $columnaAsiento;
                                    // $claseCandidato = $asientosDisponibles[$candidato]['clase'];
                                    // si la butaca no existe dentro de los asientos ya ocupados
                                    if(!in_array($candidato, $asientosOcupados) && in_array($candidato, $asientosEnumerados)){
                                        $claseCandidato = $asientosEnumerados[$candidato]['clase'];
                                        if($claseCandidato === $infoButaca['clase']){
                                            $asientosCandidatos[] = $candidato;
                                            $asientoPasajero2[] = $butaca;
                                        }
                                        
                                    }
                                }
                                if(isset($todasFilas[$indexFila + 1])){
                                    $candidato = $filaAsiento + 1 . $columnaAsiento;
                                    // $claseCandidato = $asientosDisponibles[$candidato]['clase'];
                                    // si la butaca no existe dentro de los asientos ya ocupados
                                    if(!in_array($candidato, $asientosOcupados) && in_array($candidato, $asientosEnumerados)){
                                        $claseCandidato = $asientosEnumerados[$candidato]['clase'];
                                        if($claseCandidato === $infoButaca['clase']){
                                            $asientosCandidatos[] = $candidato;
                                            $asientoPasajero2[] = $butaca;
                                        }
                                        
                                    }
                                }  
                            }
                        }
                        if($asientosCandidatos && $asientoPasajero2){
                            // Buscar el primer asiento disponible
                            for($i = 0; $i < count($asientosCandidatos); $i++){
                                $posibleAsiento1 = $asientosCandidatos[$i];
                                $posibleAsiento2 = $asientoPasajero2[$i];
                                if(isset($asientosDisponibles[$posibleAsiento1]) && isset($asientosDisponibles[$posibleAsiento2])){
                                    // Obtener fila y columna del asiento candidato actual
                                    preg_match('/(\d+)([A-Z])/', $posibleAsiento1, $matches);
                                    // Obtener fila y columna del asiento candidato actual
                                    preg_match('/(\d+)([A-Z])/', $posibleAsiento2, $matches2);
                                    // fila del asiento del menor
                                    $filaAsientoMenor = $matches[1];
                                    // columna del asiento del menor
                                    $columnaAsientoMenor = $matches[2];
                                    // fila del asiento del menor
                                    $filaAsientoAdulto = $matches2[1];
                                    // columna del asiento del menor
                                    $columnaAsientoAdulto = $matches2[2];
                                    // damos valor a las columnas sin valores del pasajero menor de edad
                                    $nuevoPasajero1['seat_id'] = $asientosEnumerados[$posibleAsiento1]['numero']; 
                                    $nuevoPasajero1['seat_column'] = $columnaAsientoMenor;
                                    $nuevoPasajero1['seat_row'] = $filaAsientoMenor;  
                                    // actualizar variables con información de asientos 
                                    $asientosOcupados[] = $posibleAsiento1;
                                    unset($asientosDisponibles[$posibleAsiento1]);
                                    unset($asientosEnumerados[$posibleAsiento1]); 

                                    // damos valor a las columnas sin valores del pasajero mayor de edad
                                    $nuevoPasajero2['seat_id'] = $asientosEnumerados[$posibleAsiento2]['numero']; 
                                    $nuevoPasajero2['seat_column'] = $columnaAsientoAdulto;
                                    $nuevoPasajero2['seat_row'] = $filaAsientoAdulto; 
                                    // actualizar variables con información de asientos 
                                    $asientosOcupados[] = $posibleAsiento2;
                                    unset($asientosDisponibles[$posibleAsiento2]);
                                    unset($asientosEnumerados[$posibleAsiento2]); 
                                    // Si el pasajero no tiene asiento, pero está en la misma compra que otro, lo agregamos también
                                    $pasajerosConAsientoYaAsignado[] = [
                                        "conteo" => $conteo,
                                        "compraId" => (int)$nuevoPasajero1["purchase_id"],
                                        "clase" => (int)$nuevoPasajero1["seat_type_id"],
                                        "pasajeroId" => (int)$nuevoPasajero1["passenger_id"],
                                        "nombre" => $nuevoPasajero1["name"],
                                        "edad" => (int)$nuevoPasajero1["age"],
                                        "asientoId" => (int)$nuevoPasajero1["seat_id"],
                                        "butaca" => $nuevoPasajero1["seat_row"] . $nuevoPasajero1["seat_column"]
                                    ];
                                    $conteo++;
                                    // Si el pasajero no tiene asiento, pero está en la misma compra que otro, lo agregamos también
                                    $pasajerosConAsientoYaAsignado[] = [
                                        "conteo" => $conteo,
                                        "compraId" => (int)$nuevoPasajero2["purchase_id"],
                                        "clase" => (int)$nuevoPasajero2["seat_type_id"],
                                        "pasajeroId" => (int)$nuevoPasajero2["passenger_id"],
                                        "nombre" => $nuevoPasajero2["name"],
                                        "edad" => (int)$nuevoPasajero2["age"],
                                        "asientoId" => (int)$nuevoPasajero2["seat_id"],
                                        "butaca" => $nuevoPasajero2["seat_row"] . $nuevoPasajero2["seat_column"]
                                    ];
                                    $conteo++;
                                    break 2; 

                                }
                            }
                        }
                    }
                }
            }
            else{

                foreach($pasajeros as $idx2 => $infoPasajero2){
                    $nuevoPasajero2 = $infoPasajero2;
                    if(
                        $nuevoPasajero2['purchase_id'] === $nuevoPasajero1['purchase_id'] &&
                        $nuevoPasajero2['passenger_id'] !== $nuevoPasajero1['passenger_id'] &&
                        !in_array($nuevoPasajero2['passenger_id'], $idsAsignadosConAsiento) &&
                        $nuevoPasajero2['age'] < 18
                        ){
                        // variable array para guardar posibles candidatos
                        $asientosCandidatos = [];
                        $asientoPasajero2 = [];

                        foreach($asientosDisponibles as $butaca => $infoButaca){
                            if($infoButaca['clase'] === $nuevoPasajero2['seat_type_id']){

                                // Obtener fila y columna del asiento candidato actual
                                preg_match('/(\d+)([A-Z])/', $butaca, $matches);
                                // fila
                                $filaAsiento = $matches[1];
                                // columna
                                $columnaAsiento = $matches[2];
                                /* guardamos la posicion de  la columna dentro de todasColumnas y la 
                                guardamos en index. [A,B,D,E,F] = posicion[0,1,2,3,4] */
                                $indexFila = array_search($filaAsiento, $todasFilas);
                                $indexColumna = array_search($columnaAsiento, $todasColumnas);
                                // buscar posibles asientos a candidato
                                // por columna
                                if(isset($todasColumnas[$indexColumna - 1])){
                                    $candidato = $filaAsiento . $todasColumnas[$indexColumna - 1];
                                    // si la butaca no existe dentro de los asientos ya ocupados
                                    if(!in_array($candidato, $asientosOcupados)){
                                        $asientosCandidatos[] = $candidato;
                                        $asientoPasajero2[] = $butaca;
                                    }
                                }
                                if(isset($todasColumnas[$indexColumna + 1])){
                                    $candidato = $filaAsiento . $todasColumnas[$indexColumna + 1];
                                    // si la butaca no existe dentro de los asientos ya ocupados
                                    if(!in_array($candidato, $asientosOcupados)){
                                        $asientosCandidatos[] = $candidato;
                                        $asientoPasajero2[] = $butaca;
                                    }
                                }
                                // por filas
                                if(isset($todasFilas[$indexFila - 1])){
                                    $candidato = $filaAsiento - 1 . $columnaAsiento;
                                    // $claseCandidato = $asientosDisponibles[$candidato]['clase'];
                                    // si la butaca no existe dentro de los asientos ya ocupados
                                    if(!in_array($candidato, $asientosOcupados) && in_array($candidato, $asientosEnumerados)){
                                        $claseCandidato = $asientosEnumerados[$candidato]['clase'];
                                        if($claseCandidato === $infoButaca['clase']){
                                            $asientosCandidatos[] = $candidato;
                                            $asientoPasajero2[] = $butaca;
                                        }
                                        
                                    }
                                }
                                if(isset($todasFilas[$indexFila + 1])){
                                    $candidato = $filaAsiento + 1 . $columnaAsiento;
                                    // $claseCandidato = $asientosDisponibles[$candidato]['clase'];
                                    // si la butaca no existe dentro de los asientos ya ocupados
                                    if(!in_array($candidato, $asientosOcupados) && in_array($candidato, $asientosEnumerados)){
                                        $claseCandidato = $asientosEnumerados[$candidato]['clase'];
                                        if($claseCandidato === $infoButaca['clase']){
                                            $asientosCandidatos[] = $candidato;
                                            $asientoPasajero2[] = $butaca;
                                        }
                                        
                                    }
                                }  

                            }
                        }
                        if($asientosCandidatos && $asientoPasajero2){
                            // Buscar el primer asiento disponible
                            for($i = 0; $i < count($asientosCandidatos); $i++){
                                $posibleAsiento1 = $asientosCandidatos[$i];
                                $posibleAsiento2 = $asientoPasajero2[$i];
                                if(isset($asientosDisponibles[$posibleAsiento1]) && isset($asientosDisponibles[$posibleAsiento2])){
                                    // Obtener fila y columna del asiento candidato actual
                                    preg_match('/(\d+)([A-Z])/', $posibleAsiento1, $matches);
                                    // Obtener fila y columna del asiento candidato actual
                                    preg_match('/(\d+)([A-Z])/', $posibleAsiento2, $matches2);
                                    // fila del asiento del menor
                                    $filaAsientoMenor = $matches[1];
                                    // columna del asiento del menor
                                    $columnaAsientoMenor = $matches[2];
                                    // fila del asiento del menor
                                    $filaAsientoAdulto = $matches2[1];
                                    // columna del asiento del menor
                                    $columnaAsientoAdulto = $matches2[2];
                                    // damos valor a las columnas sin valores del pasajero menor de edad
                                    $nuevoPasajero1['seat_id'] = $asientosEnumerados[$posibleAsiento1]['numero']; 
                                    $nuevoPasajero1['seat_column'] = $columnaAsientoMenor;
                                    $nuevoPasajero1['seat_row'] = $filaAsientoMenor;  
                                    // actualizar variables con información de asientos 
                                    $asientosOcupados[] = $posibleAsiento1;
                                    unset($asientosDisponibles[$posibleAsiento1]);
                                    unset($asientosEnumerados[$posibleAsiento1]); 

                                    // damos valor a las columnas sin valores del pasajero mayor de edad
                                    $nuevoPasajero2['seat_id'] = $asientosEnumerados[$posibleAsiento2]['numero']; 
                                    $nuevoPasajero2['seat_column'] = $columnaAsientoAdulto;
                                    $nuevoPasajero2['seat_row'] = $filaAsientoAdulto; 
                                    // actualizar variables con información de asientos 
                                    $asientosOcupados[] = $posibleAsiento2;
                                    unset($asientosDisponibles[$posibleAsiento2]);
                                    unset($asientosEnumerados[$posibleAsiento2]); 
                                    // Si el pasajero no tiene asiento, pero está en la misma compra que otro, lo agregamos también
                                    $pasajerosConAsientoYaAsignado[] = [
                                        "conteo" => $conteo,
                                        "compraId" => (int)$nuevoPasajero1["purchase_id"],
                                        "clase" => (int)$nuevoPasajero1["seat_type_id"],
                                        "pasajeroId" => (int)$nuevoPasajero1["passenger_id"],
                                        "nombre" => $nuevoPasajero1["name"],
                                        "edad" => (int)$nuevoPasajero1["age"],
                                        "asientoId" => (int)$nuevoPasajero1["seat_id"],
                                        "butaca" => $nuevoPasajero1["seat_row"] . $nuevoPasajero1["seat_column"]
                                    ];
                                    $conteo++;
                                    // Si el pasajero no tiene asiento, pero está en la misma compra que otro, lo agregamos también
                                    $pasajerosConAsientoYaAsignado[] = [
                                        "conteo" => $conteo,
                                        "compraId" => (int)$nuevoPasajero2["purchase_id"],
                                        "clase" => (int)$nuevoPasajero2["seat_type_id"],
                                        "pasajeroId" => (int)$nuevoPasajero2["passenger_id"],
                                        "nombre" => $nuevoPasajero2["name"],
                                        "edad" => (int)$nuevoPasajero2["age"],
                                        "asientoId" => (int)$nuevoPasajero2["seat_id"],
                                        "butaca" => $nuevoPasajero2["seat_row"] . $nuevoPasajero2["seat_column"]
                                    ];
                                    $conteo++;
                                    break 2; 
                                }
                            }
                        }
                    }
                    else if(
                        $nuevoPasajero2['purchase_id'] === $nuevoPasajero1['purchase_id'] &&
                        $nuevoPasajero2['passenger_id'] !== $nuevoPasajero1['passenger_id'] &&
                        in_array($nuevoPasajero2['passenger_id'], $idsAsignadosConAsiento) 
                        // $nuevoPasajero2['age'] >= 18
                    ){
                        // variable array para guardar posibles candidatos
                        $asientosCandidatos = [];
                        // butaca  y clase del pasajero2
                        $fila = $nuevoPasajero2['seat_row'];
                        $columna = $nuevoPasajero2['seat_column'];
                        
                        
                        // index de array de columnas y de filas dentro de $enumerarAsientos
                        $indexColumna = array_search($columna, $todasColumnas);
                        $indexFila = array_search($fila, $todasFilas);
                        
                        /* si index -1 existe dentro de $todasColumnas hacer, esto quiere decir que 
                        si index es 2(representa a la columna 3 dentro de $todasColumnas) entonces 
                        index -1 = 1 (representando a la columna 2)*/
                        if (isset($todasColumnas[$indexColumna - 1])) {
                            /* variable para guardar el numero la fila proporcionado por $fila el cual no se a modificado,
                            y lo fucione con el valor de la columna index deseada dentro de $todasColumnas*/
                            $candidato = $fila . $todasColumnas[$indexColumna - 1];
                            // si el $posibleAsiento no existe dentro de $asientosOcupados, hacer
                            if(!in_array($candidato, $asientosOcupados)){
                                // guardaremos ese asiento dentro del array $asientosCandidatos
                                $asientosCandidatos[] = $candidato;
                            }
                        }
                        if (isset($todasColumnas[$indexColumna + 1])) {
                            /* variable para guardar el numero la fila proporcionado por $fila el cual no se a modificado,
                            y lo fucione con el valor de la columna index deseada dentro de $todasColumnas*/
                            $candidato = $fila . $todasColumnas[$indexColumna + 1];
                            // si el $posibleAsiento no existe dentro de $asientosOcupados, hacer
                            if(!in_array($candidato, $asientosOcupados)){
                                // guardaremos ese asiento dentro del array $asientosCandidatos
                                $asientosCandidatos[] = $candidato;
                            }
                        }
                        // if por filas
                        // si existe la posicion o indice antes  de la posicion del index del pasajero 2 , hacer
                        if (isset($todasFilas[$indexFila - 1])) {
                            // damos valor a esa fila
                            $filaAnterior = $fila - 1;
                            // guardamos esa butaca como un candidato momentaneo
                            $candidato = $filaAnterior . $columna;
                            // si no existe el candidato dentro del array de los asientos ocupados, hacer
                            if(in_array($candidato, $asientosDisponibles)){
                                // guardamos la clase de esa butaca que se encuentra en la estructura del avion en una variable
                                $candidatoClase = $asientosDisponibles[$candidato]['clase'];
                                // si la clase de esa butaca es igual a la clase del pasajero menor de edad, hacer
                                if($candidatoClase === $nuevoPasajero1["seat_type_id"]){
                                    // guardaremos ese asiento dentro del array $asientosCandidatos
                                    $asientosCandidatos[] = $candidato;
                                }
                            }
                            
                        }
                        // si existe la posicion o indice siguiente  de la posicion del index del pasajero 2 , hacer
                        if (isset($todasFilas[$indexFila + 1])) {
                            // damos valor a esa fila
                            $filaSiguiente = $fila + 1;
                            // guardamos esa butaca como un candidato momentaneo
                            $candidato = $filaSiguiente . $columna;
                            // si no existe el candidato dentro del array de los asientos ocupados, hacer
                            if(in_array($candidato, $asientosDisponibles)){
                                // guardamos la clase de esa butaca que se encuentra en la estructura del avion en una variable
                                $candidatoClase = $asientosDisponibles[$candidato]['clase'];
                                // si la clase de esa butaca es igual a la clase del pasajero menor de edad, hacer
                                if($candidatoClase === $nuevoPasajero1["seat_type_id"]){
                                    // guardaremos ese asiento dentro del array $asientosCandidatos
                                    $asientosCandidatos[] = $candidato;
                                }
                            }
                        }
                        // en diagonal
                        // si existe la posicion o indice siguiente  de la posicion del index del pasajero 2 , hacer
                        if (isset($todasFilas[$indexFila - 1]) && isset($todasColumnas[$indexColumna - 1])) {
                            // damos valor a esa fila
                            $filaAnterior = $fila - 1;
                            $columnaAnterior = $todasColumnas[$indexColumna - 1];
                            // guardamos esa butaca como un candidato momentaneo
                            $candidato = $filaAnterior . $columnaAnterior;
                            // si no existe el candidato dentro del array de los asientos ocupados, hacer
                            if(in_array($candidato, $asientosDisponibles)){
                                // guardamos la clase de esa butaca que se encuentra en la estructura del avion en una variable
                                $candidatoClase = $asientosDisponibles[$candidato]['clase'];
                                // si la clase de esa butaca es igual a la clase del pasajero menor de edad, hacer
                                if($candidatoClase === $nuevoPasajero1["seat_type_id"]){
                                    // guardaremos ese asiento dentro del array $asientosCandidatos
                                    $asientosCandidatos[] = $candidato;
                                }
                            }
                        }

                        // si existe la posicion o indice siguiente  de la posicion del index del pasajero 2 , hacer
                        if (isset($todasFilas[$indexFila + 1]) && isset($todasColumnas[$indexColumna + 1])) {
                            // damos valor a esa fila
                            $filaSiguiente = $fila + 1;
                            $columnaSiguiente = $todasColumnas[$indexColumna + 1];
                            // guardamos esa butaca como un candidato momentaneo
                            $candidato = $filaSiguiente . $columnaSiguiente;
                            // si no existe el candidato dentro del array de los asientos ocupados, hacer
                            if(in_array($candidato, $asientosDisponibles)){
                                // guardamos la clase de esa butaca que se encuentra en la estructura del avion en una variable
                                $candidatoClase = $asientosDisponibles[$candidato]['clase'];
                                // si la clase de esa butaca es igual a la clase del pasajero menor de edad, hacer
                                if($candidatoClase === $nuevoPasajero1["seat_type_id"]){
                                    // guardaremos ese asiento dentro del array $asientosCandidatos
                                    $asientosCandidatos[] = $candidato;
                                }
                            }
                        }
                        // si existe la posicion o indice siguiente  de la posicion del index del pasajero 2 , hacer
                        if (isset($todasFilas[$indexFila + 1]) && isset($todasColumnas[$indexColumna - 1])) {
                            // damos valor a esa fila
                            $filaSiguiente = $fila + 1;
                            $columnaAnterior = $todasColumnas[$indexColumna - 1];
                            // guardamos esa butaca como un candidato momentaneo
                            $candidato = $filaSiguiente . $columnaAnterior;
                            // si no existe el candidato dentro del array de los asientos ocupados, hacer
                            if(in_array($candidato, $asientosDisponibles)){
                                // guardamos la clase de esa butaca que se encuentra en la estructura del avion en una variable
                                $candidatoClase = $asientosDisponibles[$candidato]['clase'];
                                // si la clase de esa butaca es igual a la clase del pasajero menor de edad, hacer
                                if($candidatoClase === $nuevoPasajero1["seat_type_id"]){
                                    // guardaremos ese asiento dentro del array $asientosCandidatos
                                    $asientosCandidatos[] = $candidato;
                                }
                            }
                        }
                        // si existe la posicion o indice siguiente  de la posicion del index del pasajero 2 , hacer
                        if (isset($todasFilas[$indexFila - 1]) && isset($todasColumnas[$indexColumna + 1])) {
                            // damos valor a esa fila
                            $filaAnterior = $fila - 1;
                            $columnaSiguiente = $todasColumnas[$indexColumna + 1];
                            // guardamos esa butaca como un candidato momentaneo
                            $candidato = $filaAnterior . $columnaSiguiente;
                            // si no existe el candidato dentro del array de los asientos ocupados, hacer
                            if(in_array($candidato, $asientosDisponibles)){
                                // guardamos la clase de esa butaca que se encuentra en la estructura del avion en una variable
                                $candidatoClase = $asientosDisponibles[$candidato]['clase'];
                                // si la clase de esa butaca es igual a la clase del pasajero menor de edad, hacer
                                if($candidatoClase === $nuevoPasajero1["seat_type_id"]){
                                    // guardaremos ese asiento dentro del array $asientosCandidatos
                                    $asientosCandidatos[] = $candidato;
                                }
                            }
                        }
                        if($asientosCandidatos){
                            // Buscar el primer asiento disponible
                            for($i = 0; $i < count($asientosCandidatos); $i++){
                                $posibleAsiento1 = $asientosCandidatos[$i];
                                
                                if(isset($asientosDisponibles[$posibleAsiento1]) && $asientosDisponibles[$posibleAsiento1]['clase'] === $nuevoPasajero1['seat_type_id']){
                                    // Obtener fila y columna del asiento candidato actual
                                    preg_match('/(\d+)([A-Z])/', $posibleAsiento1, $matches);
                                    // Obtener fila y columna del asiento candidato actual
                                    
                                    // fila del asiento del menor
                                    $filaAsientoMenor = $matches[1];
                                    // columna del asiento del menor
                                    $columnaAsientoMenor = $matches[2];
                                    // fila del asiento del menor
                                    
                                    // damos valor a las columnas sin valores del pasajero menor de edad
                                    $nuevoPasajero1['seat_id'] = $asientosEnumerados[$posibleAsiento1]['numero']; 
                                    $nuevoPasajero1['seat_column'] = $columnaAsientoMenor;
                                    $nuevoPasajero1['seat_row'] = $filaAsientoMenor;  
                                    // actualizar variables con información de asientos 
                                    $asientosOcupados[] = $posibleAsiento1;
                                    unset($asientosDisponibles[$posibleAsiento1]);
                                    unset($asientosEnumerados[$posibleAsiento1]); 

                                    
                                    // Si el pasajero no tiene asiento, pero está en la misma compra que otro, lo agregamos también
                                    $pasajerosConAsientoYaAsignado[] = [
                                        "conteo" => $conteo,
                                        "compraId" => (int)$nuevoPasajero1["purchase_id"],
                                        "clase" => (int)$nuevoPasajero1["seat_type_id"],
                                        "pasajeroId" => (int)$nuevoPasajero1["passenger_id"],
                                        "nombre" => $nuevoPasajero1["name"],
                                        "edad" => (int)$nuevoPasajero1["age"],
                                        "asientoId" => (int)$nuevoPasajero1["seat_id"],
                                        "butaca" => $nuevoPasajero1["seat_row"] . $nuevoPasajero1["seat_column"]
                                    ];
                                    $conteo++;
                                    
                                    break 2; 
                                }
                            }
                            
                        }

                    }else{
                        if($asientosDisponibles){
                            // Buscar el primer asiento disponible
                            foreach($asientosDisponibles as $asiento => $infoButaca){
                                    $claseAsiento = $infoButaca['clase'];
                                    if($claseAsiento === $nuevoPasajero1['seat_type_id']){
                                        // Obtener fila y columna del asiento candidato actual
                                        preg_match('/(\d+)([A-Z])/', $asiento, $matches);
                                        // Obtener fila y columna del asiento candidato actual
                                        
                                        // fila del asiento del menor
                                        $filaAsientoMenor = $matches[1];
                                        // columna del asiento del menor
                                        $columnaAsientoMenor = $matches[2];
                                        // fila del asiento del menor
                                        
                                        // damos valor a las columnas sin valores del pasajero menor de edad
                                        $nuevoPasajero1['seat_id'] = $asientosEnumerados[$asiento]['numero']; 
                                        $nuevoPasajero1['seat_column'] = $columnaAsientoMenor;
                                        $nuevoPasajero1['seat_row'] = $filaAsientoMenor;  
                                        // actualizar variables con información de asientos 
                                        $asientosOcupados[] = $asiento;
                                        unset($asientosDisponibles[$asiento]);
                                        unset($asientosEnumerados[$asiento]); 

                                        
                                        // Si el pasajero no tiene asiento, pero está en la misma compra que otro, lo agregamos también
                                        $pasajerosConAsientoYaAsignado[] = [
                                            "conteo" => $conteo,
                                            "compraId" => (int)$nuevoPasajero1["purchase_id"],
                                            "clase" => (int)$nuevoPasajero1["seat_type_id"],
                                            "pasajeroId" => (int)$nuevoPasajero1["passenger_id"],
                                            "nombre" => $nuevoPasajero1["name"],
                                            "edad" => (int)$nuevoPasajero1["age"],
                                            "asientoId" => (int)$nuevoPasajero1["seat_id"],
                                            "butaca" => $nuevoPasajero1["seat_row"] . $nuevoPasajero1["seat_column"]
                                        ];
                                        $conteo++;
                                        
                                        break 2; 
                                    }
                                    
                                
                            }
                            
                        }



                    }




                }

              
            }
        }
        
         
        // ordenar arrays por compra, aunque los datos hayan sido agregados mas tarde
        usort($pasajerosConAsientoYaAsignado, function ($a, $b) {
            return $a['edad'] <=> $b['edad'];
        });



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
                "passengers" => $pasajerosConAsientoYaAsignado,
                "asientosDisponibles" => $asientosDisponibles
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
                if (array_filter($clase['columns'], fn($grupo) => in_array($columna, $grupo))) {
                    foreach ($clase['rows'] as $fila) {
                        $asiento = $fila . $columna;
                        $numerosAsientos[$asiento] = [
                            'numero' => $asientoActual++,
                            'clase' => $claseNum
                        ];
                    }
                }






                // // $filas recibe todas las filas de la clase actual
                // $filas = $clase['rows'];
                // // bandera para saber si la clase actual contiene a la columna actual
                // $existeColumna = false;
                // // $grupo representa a los grupos de columnas de la clase actual
                // foreach ($clase['columns'] as $grupo) {
                //     // si la columna actual existe dentro del grupo de columnas de la clase actual hacer
                //     if (in_array($columna, $grupo)) {
                //         // decir que si existe la columna actual dentro del grupo de columnas de esa clase
                //         $existeColumna = true;
                //         break;
                //     }
                // }
                // // si la columna existe en la clase actual hacer
                // if ($existeColumna) {
                //     // Recorrer todas las filas para esa clase y columna
                //     /*$filas representa a los grupos de filas dentro de la clase actual 
                //     y $fila sirve para recorrer cada fila dentro de $filas, representando una fila a la vez*/
                //     foreach ($filas as $fila) {
                //         // $asiento sera igual a la fila actual($fila) convinada con columna actual($columna)
                //         $asiento = $fila . $columna;
                //         /* dentro de $numerosAsientos[] guardaremos el valor de asiento(1A,2A,1B,...) y le daremos 
                //         el valor de la variable $asientoActual y procederemos a incrementar por un numero esta variable*/
                //         $numerosAsientos[$asiento] = [
                //             'numero' => $asientoActual++,
                //             'clase' => $claseNum
                //         ];
                //     }
                // }
            }
        }
        return $numerosAsientos; // Devuelve un array con clave asiento y valor número asignado
        
    }

}
?>