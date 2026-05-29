<?php
require_once "tv-admin/asset/Clases/dbconectar.php";
require_once "tv-admin/asset/Clases/ConexionMySQL.php";

function crearUrlAmigable($str) {
    $str = strtolower(trim($str));
    $str = strtr(utf8_decode($str), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    $str = preg_replace('/[^a-z0-9-]/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return rtrim($str, '-');
}

header("Content-Type: application/xml; charset=utf-8");

$conn = new HelperMySql($array_principal["server"], $array_principal["user"], $array_principal["pass"], $array_principal["db"]);

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

echo '<url>';
echo '<loc>https://macromautopartes.com/</loc>';
echo '<changefreq>daily</changefreq>';
echo '<priority>1.0</priority>';
echo '</url>';

$sql = "SELECT _id, Producto, dateModify FROM Producto WHERE Publicar = 1 AND stock > 0";
$res = $conn->query($sql);

if ($res) {
    while ($row = $conn->fetch($res)) {
        $slug = crearUrlAmigable($row['Producto']);
        
        $url = "https://macromautopartes.com/catalogo/detalles/" . $row['_id'] . "-" . $slug;
        
        $fechaDB = isset($row['dateModify']) ? $row['dateModify'] : date("Y-m-d");
        $fecha = date("Y-m-d", strtotime($fechaDB));
        
        echo '<url>';
        echo '<loc>' . $url . '</loc>';
        echo '<lastmod>' . $fecha . '</lastmod>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.8</priority>';
        echo '</url>';
    }
}

echo '</urlset>';
?>