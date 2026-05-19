<?php
    session_name("loginUsuario");
    session_start();
    require_once "../../../../Clases/dbconectar.php";
    require_once "../../../../Clases/ConexionMySQL.php";
    require_once "../../../../Clases/Funciones.php";
    date_default_timezone_set('America/Mexico_City');
    
    class Marcas {
        private $conn;
        private $jsonData = array("Bandera"=>0,"mensaje"=>"");
        private $formulario = array();
        private $url;
        private $foto;
        
        public function __construct($array) {
            $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
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
                    
                    $this->jsonData["Data"]["noRegistros"] = $this->getNoMarcas($find);
                    $this->jsonData["Data"]["Registros"] = $this->getMarcas($find, $skip, $limit);
                    $this->jsonData["Bandera"] = 1;
                    break;
                case 'edit':
                case 'new':
                case 'disabled':
                case 'enabled':
                case 'delete':
                    if($this->setMarcas()){
                        $this->formulario["lastid"] = $this->formulario["opc"] == "edit" ? intval($this->formulario["_id"]) : $this->conn->last_id();
                        if($this->formulario["opc"] == 'edit' || $this->formulario["opc"] == 'new') $this->setProductos();
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
        
        private function getMensajeSuccess(){
            switch($this->formulario["opc"]){
                case 'new': return "La Agencia ha sido Creada";
                case 'edit': return "La Agencia ha sido Modificada";
                case 'disabled': return "La Agencia ha sido Desactivada";
                case 'enabled': return "La Agencia ha sido Activada";
                case 'delete': return "La Agencia ha sido Eliminada permanentemente";
            }
            return "";
        }
        
        private function getMensajeError(){
            switch($this->formulario["opc"]){
                case 'new': return "Error: La Agencia no ha sido Creada";
                case 'edit': return "Error: La Agencia no ha sido Modificada";
                case 'disabled': return "Error: La Agencia no ha sido Desactivada";
                case 'enabled': return "Error: La Agencia no ha sido Activada";
                case 'delete': return "Error: No se pudo eliminar la Agencia";
            }
            return "";
        }

        private function getNoMarcas($find=""){
            $find_seguro = addslashes(trim($find));
            $hist_seguro = intval($this->formulario["historico"]);
            $sql = "SELECT count(*) as total FROM Marcas WHERE Estatus = $hist_seguro AND Marca LIKE '%$find_seguro%'";
            $row = $this->conn->fetch($this->conn->query($sql));
            return $row["total"];
        }
        
        private function getMarcas($find="", $skip=0, $limit=10){
            $array = array();
            $find_seguro = addslashes(trim($find));
            $hist_seguro = intval($this->formulario["historico"]);
            $sql = "SELECT * FROM Marcas WHERE Estatus = $hist_seguro AND Marca LIKE '%$find_seguro%' LIMIT $skip, $limit";
            $id = $this->conn->query($sql);
            while($row = $this->conn->fetch($id)){
                $row["foto"] = file_exists("../../../../../../images/Marcas/".$row["_id"].".png");
                array_push($array, $row);
            }
            return $array;
        }
        
        private function setMarcas(){
            $sql = "";
            $usr_seguro = addslashes($_SESSION["nombre"]);
            $fecha_actual = date("Y-m-d H:i:s");
            $accionLog = ""; $detallesLog = "";

            if($this->formulario["opc"] == 'new' || $this->formulario["opc"] == 'edit') {
                $marca_segura = addslashes(trim($this->formulario["Marca"]));
                $id_condicion = ($this->formulario["opc"] == 'edit') ? " AND _id != " . intval($this->formulario["_id"]) : "";
                $checkSql = "SELECT _id FROM Marcas WHERE Marca = '$marca_segura'" . $id_condicion;
                $resCheck = $this->conn->query($checkSql);
                if ($this->conn->fetch($resCheck)) {
                    $this->jsonData["mensaje"] = "Ya existe una agencia registrada con ese nombre.";
                    return false;
                }
            }

            if($this->formulario["opc"] == 'new') {
                $marca_segura = addslashes(trim($this->formulario["Marca"]));
                $color_seguro = addslashes($this->formulario["Color"]);
                $sql = "INSERT INTO Marcas (Marca, Estatus, USRCreacion, USRModificacion, FechaCreacion, FechaModificacion, Color) 
                        VALUES ('$marca_segura', 1, '$usr_seguro', '$usr_seguro', '$fecha_actual', '$fecha_actual', '$color_seguro')";
                
                $accionLog = "CREAR_AGENCIA"; $detallesLog = "Se registró la agencia: $marca_segura";
            } else {
                $id_seguro = intval($this->formulario["_id"]);
                $nombre_agencia = isset($this->formulario["Marca"]) ? addslashes(trim($this->formulario["Marca"])) : "Agencia ID: $id_seguro";

                if($this->formulario["opc"] == 'edit'){
                    $color_seguro = addslashes($this->formulario["Color"]);
                    $sql = "UPDATE Marcas SET Marca = '$nombre_agencia', USRModificacion = '$usr_seguro', FechaModificacion = '$fecha_actual', Color = '$color_seguro' WHERE _id = $id_seguro";
                    $accionLog = "EDITAR_AGENCIA"; $detallesLog = "Se actualizaron los datos de la agencia: $nombre_agencia";
                } else if($this->formulario["opc"] == 'disabled'){
                    $sqlOld = "SELECT Marca FROM Marcas WHERE _id = $id_seguro";
                    $rowOld = $this->conn->fetch($this->conn->query($sqlOld));
                    $nombreV = $rowOld ? $rowOld['Marca'] : "Agencia ID: $id_seguro";

                    $sql = "UPDATE Marcas SET Estatus = 0, USRModificacion = '$usr_seguro', FechaModificacion = '$fecha_actual' WHERE _id = $id_seguro"; 
                    $accionLog = "DESACTIVAR_AGENCIA"; $detallesLog = "Se desactivó la agencia: $nombreV";
                } else if($this->formulario["opc"] == 'enabled'){
                    $sqlOld = "SELECT Marca FROM Marcas WHERE _id = $id_seguro";
                    $rowOld = $this->conn->fetch($this->conn->query($sqlOld));
                    $nombreV = $rowOld ? $rowOld['Marca'] : "Agencia ID: $id_seguro";

                    $sql = "UPDATE Marcas SET Estatus = 1, USRModificacion = '$usr_seguro', FechaModificacion = '$fecha_actual' WHERE _id = $id_seguro"; 
                    $accionLog = "ACTIVAR_AGENCIA"; $detallesLog = "Se reactivó la agencia: $nombreV";
                } else if($this->formulario["opc"] == 'delete'){ 
                    $sql = "DELETE FROM Marcas WHERE _id = $id_seguro"; 
                    $accionLog = "ELIMINAR_AGENCIA"; $detallesLog = "Se eliminó permanentemente la agencia ID: $id_seguro";
                }
            }

            if($this->conn->query($sql)){
                Funciones::guardarBitacora($this->conn, 'Agencias', $accionLog, $detallesLog);
                return true;
            } else {
                $this->jsonData["error"] = $this->conn->error;
                return false;
            }
        }
        
        private function setProductos(){
            $id_seguro = intval($this->formulario["lastid"]);
            $color_seguro = addslashes($this->formulario["Color"]);
            $sql = "SELECT _id FROM Producto WHERE _idMarca = $id_seguro";
            $id = $this->conn->query($sql);
            while($row = $this->conn->fetch($id)){
                $prod_id = intval($row["_id"]);
                $sqlUpdate = "UPDATE Producto SET Color = '$color_seguro' WHERE _id = $prod_id";
                $this->conn->query($sqlUpdate);
            }
        }
        
        private function subirImagen(){
            if(isset($this->foto["file"]) && $this->foto["file"]["name"] != "" && $this->foto["file"]["size"] != 0){
                $subdir = "../../../../../../"; $dir = "images/Marcas/";
                $archivo = $this->formulario["lastid"].".png";
                if(!is_dir($subdir.$dir)) mkdir($subdir.$dir, 0755, true);
                move_uploaded_file($this->foto["file"]["tmp_name"], $subdir.$dir.$archivo);
                return true;
            }
            return false;
        }
    }
    
    $app = new Marcas($array_principal);
    $app->principal();
?>