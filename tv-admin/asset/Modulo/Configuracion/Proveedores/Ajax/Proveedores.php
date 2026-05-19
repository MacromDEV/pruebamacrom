<?php
session_name("loginUsuario");
session_start();
require_once "../../../../Clases/dbconectar.php";
require_once "../../../../Clases/ConexionMySQL.php";
require_once "../../../../Clases/Funciones.php"; 
date_default_timezone_set('America/Mexico_City');

Class Proveedores{
    private $conn;
    private $jsonData = array("Bandera"=>0,"mensaje"=>"");
    private $formulario = array();
    private $fecha;
    private $url;
    private $foto;
    
    public function __construct($array) {
        $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
        $this->fecha = date("Y-m-d H:i:s");
        $this->url = preg_replace("#(admin\.)?#i","", $_SERVER["HTTP_ORIGIN"]);
    }

    public function __destruct() {
        unset($this->conn);
    }

    public function principal(){
        $this->formulario = array_map("htmlspecialchars", $_POST);
        $this->foto = isset($_FILES) ? $_FILES : array();
        
        switch ($this->formulario["opc"]) {
            case 'buscar':
                $find = isset($this->formulario["find"]) ? $this->formulario["find"] : "";
                $skip = isset($this->formulario["skip"]) ? intval($this->formulario["skip"]) : 0;
                $limit = isset($this->formulario["limit"]) ? intval($this->formulario["limit"]) : 10;
                
                $this->jsonData["Data"]["noRegistros"] = $this->getNoProveedores($find);
                $this->jsonData["Data"]["Registros"] = $this->getProveedores($find, $skip, $limit);
                $this->jsonData["Bandera"] = 1;
            break;
            case 'edit':
            case 'new':
            case 'enabled':
            case 'disabled':
            case 'delete':
                if($this->setProveedores()){
                    $this->formulario["lastid"] = ($this->formulario["opc"] == "edit" || $this->formulario["opc"] == "delete") ? intval($this->formulario["_id"]) : $this->conn->last_id();
                    if(count($this->foto) != 0) $this->subirImagen();
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = $this->getMensajeSuccess();
                } else {
                    if(empty($this->jsonData["mensaje"])) $this->jsonData["mensaje"] = $this->getMensajeError();
                }
            break;
        }
        $this->jsonData["dominio"] = $this->url;
        print json_encode($this->jsonData);
    }

    private function setProveedores(){
        $sql = "";
        $usr_seguro = addslashes($_SESSION["nombre"]);
        $accionLog = ""; $detallesLog = "";

        if($this->formulario["opc"] == 'new' || $this->formulario["opc"] == 'edit') {
            $prov_seguro = addslashes(trim($this->formulario["Proveedor"]));
            $id_condicion = ($this->formulario["opc"] == 'edit') ? " AND _id != " . intval($this->formulario["_id"]) : "";
            
            $checkSql = "SELECT _id FROM Proveedor WHERE Proveedor = '$prov_seguro'" . $id_condicion;
            $resCheck = $this->conn->query($checkSql);
            if ($this->conn->fetch($resCheck)) {
                $this->jsonData["mensaje"] = "Ya existe un proveedor registrado con ese nombre.";
                return false;
            }
        }
        
        if($this->formulario["opc"] == 'new'){
            $prov_seguro = addslashes(trim($this->formulario["Proveedor"]));
            $title_seguro = addslashes(trim($this->formulario["tag_title"]));
            $alt_seguro = addslashes(trim($this->formulario["tag_alt"]));
            $sql = "INSERT INTO Proveedor (Proveedor, Estatus, tag_title, tag_alt, USRCreacion, USRModificacion, FechaCreacion, fechaModificacion) 
                    VALUES ('$prov_seguro', 1, '$title_seguro', '$alt_seguro', '$usr_seguro', '$usr_seguro', '{$this->fecha}', '{$this->fecha}')";
            $accionLog = "CREAR_PROVEEDOR"; $detallesLog = "Se registró el proveedor de marca: $prov_seguro";
        } else {
            $id_seguro = intval($this->formulario["_id"]);
            $nombre_prov = isset($this->formulario["Proveedor"]) ? addslashes(trim($this->formulario["Proveedor"])) : "Proveedor ID: $id_seguro";

            if($this->formulario["opc"] == 'edit'){
                $prov_seguro = addslashes(trim($this->formulario["Proveedor"]));
                $title_seguro = addslashes(trim($this->formulario["tag_title"]));
                $alt_seguro = addslashes(trim($this->formulario["tag_alt"]));
                $sql = "UPDATE Proveedor SET Proveedor = '$prov_seguro', USRModificacion='$usr_seguro', fechaModificacion='{$this->fecha}', tag_title = '$title_seguro', tag_alt = '$alt_seguro' WHERE _id=$id_seguro";
                $accionLog = "EDITAR_PROVEEDOR"; $detallesLog = "Se actualizaron los datos del proveedor: $nombre_prov";
            } else if($this->formulario["opc"] == 'enabled'){
                $sqlOld = "SELECT Proveedor FROM Proveedor WHERE _id = $id_seguro";
                $rowOld = $this->conn->fetch($this->conn->query($sqlOld));
                $nombreV = $rowOld ? $rowOld['Proveedor'] : "Proveedor ID: $id_seguro";

                $sql = "UPDATE Proveedor SET Estatus = 1, USRModificacion='$usr_seguro', fechaModificacion='{$this->fecha}' WHERE _id = $id_seguro";
                $accionLog = "ACTIVAR_PROVEEDOR"; $detallesLog = "Se reactivó el proveedor: $nombreV";
            } else if($this->formulario["opc"] == 'disabled'){
                $sqlOld = "SELECT Proveedor FROM Proveedor WHERE _id = $id_seguro";
                $rowOld = $this->conn->fetch($this->conn->query($sqlOld));
                $nombreV = $rowOld ? $rowOld['Proveedor'] : "Proveedor ID: $id_seguro";

                $sql = "UPDATE Proveedor SET Estatus = 0, USRModificacion='$usr_seguro', fechaModificacion='{$this->fecha}' WHERE _id = $id_seguro";
                $accionLog = "DESACTIVAR_PROVEEDOR"; $detallesLog = "Se desactivó el proveedor: $nombreV";
            } else if($this->formulario["opc"] == 'delete'){
                $sql = "DELETE FROM Proveedor WHERE _id = $id_seguro";
                $accionLog = "ELIMINAR_PROVEEDOR"; $detallesLog = "Se eliminó permanentemente el proveedor ID: $id_seguro";
            }
        }
        
        if($this->conn->query($sql)){
            Funciones::guardarBitacora($this->conn, 'Proveedores', $accionLog, $detallesLog);
            return true;
        } else { return false; }
    }

    private function getNoProveedores($find=""){
        $find_seguro = addslashes(trim($find));
        $hist_seguro = intval($this->formulario["historico"]);
        $sql = "SELECT count(*) as total FROM Proveedor WHERE Estatus = $hist_seguro AND Proveedor LIKE '%$find_seguro%'";
        $row = $this->conn->fetch($this->conn->query($sql));
        return $row["total"];
    }

    private function getProveedores($find="", $skip=0, $limit=10){
        $array = array();
        $find_seguro = addslashes(trim($find));
        $hist_seguro = intval($this->formulario["historico"]);
        $sql = "SELECT * FROM Proveedor WHERE Estatus = $hist_seguro AND Proveedor LIKE '%$find_seguro%' ORDER BY Proveedor ASC LIMIT $skip, $limit";
        $id = $this->conn->query($sql);
        while($row = $this->conn->fetch($id)){
            $row["foto"] = file_exists("../../../../../../images/Marcasrefacciones/".$row["_id"].".png");
            array_push($array, $row);
        }
        return $array;
    }

    private function getMensajeSuccess(){
        switch($this->formulario["opc"]){
            case 'new': return "El proveedor ha sido Creado";
            case 'edit': return "El proveedor ha sido Modificado";
            case 'disabled': return "El proveedor ha sido Desactivado";
            case 'enabled': return "El proveedor ha sido Activado";
            case 'delete': return "El proveedor ha sido Eliminado";
        }
        return "";
    }

    private function getMensajeError(){
        switch($this->formulario["opc"]){
            case 'new': return "Error: El proveedor no ha sido Creado";
            case 'edit': return "Error: El proveedor no ha sido Modificado";
            case 'disabled': return "Error: El proveedor no ha sido Desactivado";
            case 'enabled': return "Error: El proveedor no ha sido Activado";
            case 'delete': return "Error: No se pudo eliminar el proveedor";
        }
        return "";
    }

    private function subirImagen(){
        if(isset($this->foto["file"]) && $this->foto["file"]["name"] != "" && $this->foto["file"]["size"] != 0){
            $subdir ="../../../../../../"; $dir = "images/Marcasrefacciones/";
            $archivo = $this->formulario["lastid"].".png";
            if(!is_dir($subdir.$dir)) mkdir($subdir.$dir,0755, true);
            if(move_uploaded_file($this->foto["file"]["tmp_name"], $subdir.$dir.$archivo)) return true;
        }
        return false;
    }
}

$app = new Proveedores($array_principal);
$app->principal();
?>