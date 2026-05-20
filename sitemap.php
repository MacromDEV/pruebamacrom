<?php
require_once "tv-admin/asset/Clases/dbconectar.php";
require_once "tv-admin/asset/Clases/ConexionMySQL.php";

header("Content-Type: application/xml; charset=utf-8");

$conn = new HelperMySql($array_principal["server"], $array_principal["user"], $array_principal["pass"], $array_principal["db"]);

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

echo '<url>';
echo '<loc>https://macromautopartes.com/</loc>';
echo '<changefreq>daily</changefreq>';
echo '<priority>1.0</priority>';
echo '</url>';

$sql = "SELECT _id, NewUrlName, FechaModificacion FROM Producto WHERE Publicar = 1 AND stock > 0";
$res = $conn->query($sql);

if ($res) {
    while ($row = $conn->fetch($res)) {
        $url = "https://macromautopartes.com/?mod=catalogo&amp;opc=detalles&amp;_id=" . $row['_id'] . "-" . $row['NewUrlName'];
        
        $fecha = date("Y-m-d", strtotime($row['FechaModificacion']));
        
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