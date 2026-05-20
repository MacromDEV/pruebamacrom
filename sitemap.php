<?php
// 1. Encendemos los errores de PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "tv-admin/asset/Clases/dbconectar.php";
require_once "tv-admin/asset/Clases/ConexionMySQL.php";

// 2. COMENTAMOS la cabecera XML temporalmente para poder leer el error
// header("Content-Type: application/xml; charset=utf-8");

$conn = new HelperMySql($array_principal["server"], $array_principal["user"], $array_principal["pass"], $array_principal["db"]);

echo "CONEXION EXITOSA... <br>";

// Revisa si tu tabla realmente se llama 'Producto'
$sql = "SELECT _id, NewUrlName, FechaModificacion FROM Producto WHERE Publicar = 1 AND stock > 0";
$res = $conn->query($sql);

if ($res) {
    echo "CONSULTA EXITOSA... <br>";
    while ($row = $conn->fetch($res)) {
        echo $row['_id'] . "<br>";
    }
}
?>