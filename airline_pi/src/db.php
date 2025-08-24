
<?php
function getPDOConnection() {
    static $pdo = null;

    $host = 'mdb-test.c6vunyturrl6.us-west-1.rds.amazonaws.com';
    $dbname = 'airline';
    $user = 'postulaciones';
    $pass = 'post123456';

    try {
        if ($pdo === null || !$pdo->query('SELECT 1')) {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode([
            "code" => 400,
            "errors" => "Could not connect to DB: " . $e->getMessage()
        ]);
        exit;
    }

    return $pdo;

}

// $host = 'mdb-test.c6vunyturrl6.us-west-1.rds.amazonaws.com';
// $dbname = 'airline';
// $user = 'postulaciones';
// $pass = 'post123456';

// try {
//     $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//     // echo "Conexión exitosa a la base de datos.";
// } catch (PDOException $e) {
//     http_response_code(400);
//     echo json_encode([
//         "code" => 400,
//         "errors" => "could not connect to db"
//     ]);
// }
?>