<?php
session_name("loginUsuario");
session_start();
require_once "../../../../Clases/dbconectar.php";
require_once "../../../../Clases/ConexionMySQL.php";
date_default_timezone_set('America/Mexico_City');

class WebPrincipal{
    private $conn;
    private $jsonData = array("Bandera"=>0, "mensaje"=>"");
    private $formulario = array();
    private $foto;
    private $fecha;
    private $url;

    public function __construct($array) {
        $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
        $this->fecha = date("Y-m-d H:i:s");
        $this->url = preg_replace("#(admin\.)?#i","", $_SERVER["HTTP_ORIGIN"] ?? '');
    }

    public function __destruct() {
        unset($this->conn);
    }

    public function main(){
        $json = json_decode(file_get_contents('php://input'), true);
        if (!empty($json) && isset($json['imagen'])) {
            $this->formulario = $json['imagen'];
        } else {
            $this->formulario = $_POST;
        }
        
        if (is_array($this->formulario)) {
            $this->formulario = array_map("addslashes", $this->formulario);
            $this->formulario = array_map("htmlspecialchars", $this->formulario);
        }
        
        $this->foto = isset($_FILES) ? $_FILES : array();
        
        $opc = $this->formulario["opc"] ?? '';

        switch ($opc) {
            case 'get':
                $this->jsonData["Data"] = $this->getImagen();
                $this->jsonData["Disabled"] = $this->getDisabledImg();
                $this->jsonData["categoria"] = $this->formulario["Categoria"] ?? '';
                $this->jsonData["Bandera"] = 1;
            break;

            case 'set':
                if(count($this->foto) != 0){
                    if($this->subirImagen()){
                        if($this->setImagen()){
                            $this->jsonData["Bandera"] = 1;
                            $this->jsonData["mensaje"] = "La imagen se subió y guardó satisfactoriamente.";
                            $this->jsonData["categoria"] = $this->formulario["Categoria"] ?? '';
                            
                            // BITÁCORA
                            $categoria = $this->formulario["Categoria"] ?? 'General';
                            $disenio = $this->formulario["Disenio"] ?? 'Desconocido';
                            $nombreImg = $this->foto["file"]["name"] ?? 'imagen';
                            $this->setBitacora("SUBIR_BANNER", "Subió un nuevo banner ($nombreImg) para $categoria en formato $disenio");
                        } else {
                            $this->jsonData["Bandera"] = 0;
                            $this->jsonData["mensaje"] = "Error al intentar guardar la imagen en la base de datos."; 
                        }
                    } else {
                        $this->jsonData["Bandera"] = 0;
                        $this->jsonData["mensaje"] = "Error al intentar subir el archivo físico al servidor.";
                    }
                } 
            break;

            case 'off':
                if($this->setImagen()){
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Imagen eliminada permanentemente.";
                    $this->jsonData["categoria"] = $this->formulario["Categoria"] ?? '';
                    
                    // BITÁCORA
                    $id_banner = $this->formulario["_id"] ?? '';
                    $categoria = $this->formulario["Categoria"] ?? '';
                    $this->setBitacora("ELIMINAR_BANNER", "Eliminó definitivamente el banner ID $id_banner de la sección $categoria");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al intentar eliminar la Imagen.";
                }
            break;

            case 'offcarrousel':
                if($this->cambiarEstatus(0)){
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Imagen desactivada del carrousel.";
                    $this->jsonData["categoria"] = $this->formulario["Categoria"] ?? '';
                    
                    // BITÁCORA
                    $id_banner = $this->formulario["_id"] ?? '';
                    $this->setBitacora("QUITAR_CARROUSEL", "Desactivó el banner ID $id_banner del Carrousel principal");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al intentar desactivar la Imagen.";
                }
            break;

            case 'pause':
                if($this->cambiarEstatus(0)){
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Banner desactivado correctamente.";
                    $this->jsonData["categoria"] = $this->formulario["Categoria"] ?? '';
                    
                    // BITÁCORA
                    $id_banner = $this->formulario["_id"] ?? '';
                    $categoria = $this->formulario["Categoria"] ?? '';
                    $this->setBitacora("DESACTIVAR_BANNER", "Desactivó el banner ID $id_banner de la sección $categoria");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al intentar desactivar el Banner.";
                }
            break;

            case 'act':
                if($this->reemplazarImagenActiva()){
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Banner reemplazado y puesto en vivo.";
                    $this->jsonData["categoria"] = $this->formulario["Categoria"] ?? '';
                    
                    // BITÁCORA
                    $id_banner = $this->formulario["_id"] ?? '';
                    $categoria = $this->formulario["Categoria"] ?? '';
                    $this->setBitacora("ACTIVAR_BANNER", "Puso en vivo el banner ID $id_banner en la sección $categoria");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al intentar reemplazar el Banner.";
                }
            break;

            case 'carrouselPred':
                if($this->cambiarEstatus(1)){
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Imagen agregada al carrousel activo.";
                    $this->jsonData["categoria"] = $this->formulario["Categoria"] ?? '';
                    
                    // BITÁCORA
                    $id_banner = $this->formulario["_id"] ?? '';
                    $this->setBitacora("AGREGAR_CARROUSEL", "Agregó el banner ID $id_banner al Carrousel activo");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al intentar agregar la Imagen.";
                }
            break;
        }

        $this->jsonData["dominio"] = $this->url;
        header('Content-Type: application/json');
        print json_encode($this->jsonData);
    }

    private function setBitacora($accion, $detalles) {
        $id_usuario = $_SESSION["id_usuario"] ?? $_SESSION["id"] ?? 0; 
        $username = $_SESSION["nombre_usuario"] ?? $_SESSION["usr"] ?? 'Desarrollador'; 
        
        $modulo = 'Banners_Web';
        $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; 
        
        $detalles_limpios = addslashes($detalles);

        $sql = "INSERT INTO Bitacora_Auditoria 
                (id_usuario, username, modulo, accion, detalles, fecha, ip_usuario) 
                VALUES 
                ($id_usuario, '$username', '$modulo', '$accion', '$detalles_limpios', '{$this->fecha}', '$ip_usuario')";
        
        $this->conn->query($sql);
    }

    private function cambiarEstatus($estatus){
        $id = $this->formulario["_id"] ?? '';
        $sql = "UPDATE Imagenes SET Estatus = $estatus WHERE _id = '$id'";
        return $this->conn->query($sql);
    }

    private function reemplazarImagenActiva(){
        $categoria = $this->formulario["Categoria"] ?? '';
        $id = $this->formulario["_id"] ?? '';
        
        $sqlOff = "UPDATE Imagenes SET Estatus = 0 WHERE Estatus = 1 AND Categoria = '$categoria'";
        $this->conn->query($sqlOff);
        
        $sqlOn = "UPDATE Imagenes SET Estatus = 1 WHERE _id = '$id'";
        return $this->conn->query($sqlOn);
    }

    private function setImagen(){
        $categoria = $this->formulario["Categoria"] ?? '';
        $disenio = $this->formulario["Disenio"] ?? '';
        $id = $this->formulario["_id"] ?? '';
        $opc = $this->formulario["opc"] ?? '';

        if($opc == 'set'){
            $nombreFile = $this->foto["file"]["name"];
            $estatus = (int)($this->formulario["Estatus"] ?? 0);
            
            $sql = "INSERT INTO Imagenes(imagen, Categoria, Estatus, FechaCreacion, FechaModificacion, Diseño) 
                    VALUES('$nombreFile', '$categoria', $estatus, '{$this->fecha}', '{$this->fecha}', '$disenio')";
            return $this->conn->query($sql);

        } else if($opc == 'off'){
            $this->BorrarImagenFisica($id); 
            $sql = "DELETE FROM Imagenes WHERE _id = '$id'";
            return $this->conn->query($sql);
        }
        return false;
    }

    private function getDisabledImg(){
        $array = array("Escritorio"=>array(), "Movil"=>array());
        $categoria = $this->formulario["Categoria"] ?? '';
        $disenos = array("Escritorio", "Movil");

        foreach($disenos as $diseno){
            $sql = "SELECT * FROM Imagenes WHERE Categoria = '$categoria' AND Estatus = 0 AND Diseño = '$diseno' ORDER BY _id DESC";
            $res = $this->conn->query($sql);
            while($row = $this->conn->fetch($res)){
                array_push($array[$diseno], $row);
            }
        }
        return $array;
    }

    private function getImagen(){
        $array = array("Escritorio"=>array(), "Movil"=>array());
        $categoria = $this->formulario["Categoria"] ?? '';
        $estatus = (int)($this->formulario["Estatus"] ?? 0);
        $disenos = array("Escritorio", "Movil");
        
        $limit = ($categoria != "Carrousel") ? "LIMIT 1" : "";

        foreach($disenos as $diseno){
            $sql = "SELECT * FROM Imagenes WHERE Categoria = '$categoria' AND Estatus = $estatus AND Diseño = '$diseno' ORDER BY _id DESC $limit";
            $res = $this->conn->query($sql);
            while($row = $this->conn->fetch($res)){
                array_push($array[$diseno], $row);
            }
        }
        return $array;
    }

    private function subirImagen(){
        if(isset($this->foto["file"]["name"]) && $this->foto["file"]["size"] > 0){
            $subdir = "../../../../../../"; 
            $dir = "images/Banners/";
            $archivo = $this->foto["file"]["name"];
            
            if(!is_dir($subdir.$dir)){
                mkdir($subdir.$dir, 0755, true);
            }
            return move_uploaded_file($this->foto["file"]["tmp_name"], $subdir.$dir.$archivo);
        }
        return false;
    }

    private function BorrarImagenFisica($id){
        $sql = "SELECT imagen FROM Imagenes WHERE _id = '$id'";
        $result = $this->conn->query($sql);
        if($row = $this->conn->fetch($result)){
            $imagen = $row["imagen"];
            $ruta = "../../../../../../images/Banners/{$imagen}";
            if(file_exists($ruta)){
                unlink($ruta);
            }
        }
    }
}

$app = new WebPrincipal($array_principal);
$app->main();