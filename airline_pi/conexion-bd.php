
<?php
$host = 'mdb-test.c6vunyturrl6.us-west-1.rds.amazonaws.com';
$dbname = 'airline';
$user = 'postulaciones';
$pass = 'post123456';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT * FROM flight");
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($flights);
    echo "</pre>";

} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
}
?>