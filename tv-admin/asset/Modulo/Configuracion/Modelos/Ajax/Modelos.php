<?php
    session_name("loginUsuario");
    session_start();
    require_once "../../../../Clases/dbconectar.php";
    require_once "../../../../Clases/ConexionMySQL.php";
    require_once "../../../../Clases/Funciones.php";
    date_default_timezone_set('America/Mexico_City');
    
    class Modelos {
        private $conn;
        private $jsonData = array("Bandera"=>0,"mensaje"=>"");
        private $formulario = array();
        private $url;

        public function __construct($array) {
            $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
            $this->url = preg_replace("#(admin\.)?#i","", $_SERVER["HTTP_ORIGIN"]);
        }

        public function __destruct() {
            unset($this->conn);
        }
        
        public function principal() {
            $this->formulario = array_map("htmlspecialchars", $_POST);
            
            switch ($this->formulario["opc"]) {
                case 'buscar':
                    switch($this->formulario["tipo"]){
                        case 'Modelos':
                            $find = isset($this->formulario["find"]) ? $this->formulario["find"] : "";
                            $skip = isset($this->formulario["skip"]) ? intval($this->formulario["skip"]) : 0;
                            $limit = isset($this->formulario["limit"]) ? intval($this->formulario["limit"]) : 10;

                            $this->jsonData["Data"]["NoModelos"] = $this->getNoModelos($find);
                            $this->jsonData["Data"]["Modelos"] = $this->getModelos($find, $skip, $limit);
                            $this->jsonData["Bandera"] = 1;
                            break;
                        case 'Marcas':
                            $this->jsonData["data"] = $this->getMarcas();
                            $this->jsonData["Bandera"] = 1;
                            break;
                        case 'Anios':
                            $this->jsonData["data"] = $this->getAnios();
                            $this->jsonData["Bandera"] = 1;
                            break;
                    }          
                    break;
                case 'edit':
                case 'new':
                case 'disabled':
                case 'enabled':
                case 'delete':
                    if($this->setModelos()){
                        $this->formulario["lastid"] = ($this->formulario["opc"] == "edit" || $this->formulario["opc"] == "delete") ? intval($this->formulario["_id"]) : $this->conn->last_id();
                        $this->jsonData["Bandera"] = 1;
                        $this->jsonData["mensaje"] = $this->getMensajeSuccess();
                    } else {
                        if(empty($this->jsonData["mensaje"])) $this->jsonData["mensaje"] = $this->getMensajeError();
                    }
                    break;
                case 'newanios':
                case 'editanio':
                case "deleteanio":
                    if($this->setAnios()){
                        $this->jsonData["Bandera"] = 1;
                        $this->jsonData["mensaje"] = $this->getMensajeSuccessAnios();
                    } else {
                        if(empty($this->jsonData["mensaje"])) $this->jsonData["mensaje"] = $this->getMensajeErrorAnios();
                    }
                    break;
            }
            $this->jsonData["dominio"] = $this->url;
            print json_encode($this->jsonData);
        }
        
        private function getMensajeSuccess(){
            switch($this->formulario["opc"]){
                case 'new': return "El Vehículo ha sido Creado";
                case 'edit': return "El Vehículo ha sido Modificado";
                case 'disabled': return "El Vehículo ha sido Desactivado";
                case 'enabled': return "El Vehículo ha sido Activado";
                case 'delete': return "El Vehículo ha sido Eliminado permanentemente";
            }
            return "";
        }
        
        private function getMensajeSuccessAnios(){
            switch($this->formulario["opc"]){
                case 'newanios': return "La generación ha sido Creada";
                case 'editanio': return "La generación ha sido Modificada";
                case 'deleteanio': return "La generación ha sido Eliminada";
            }
            return "";
        }
        
        private function getMensajeError(){
            switch($this->formulario["opc"]){
                case 'new': return "Error: El Vehículo no ha sido Creado";
                case 'edit': return "Error: El Vehículo no ha sido Modificado";
                case 'disabled': return "Error: El Vehículo no ha sido Desactivado";
                case 'enabled': return "Error: El Vehículo no ha sido Activado";
                case 'delete': return "Error: No se pudo eliminar el Vehículo";
            }
            return "";
        }
        
        private function getMensajeErrorAnios(){
            switch($this->formulario["opc"]){
                case 'newanios': return "Error: La generación no ha sido Creada";
                case 'editanio': return "Error: La generación no ha sido Modificada";
                case 'deleteanio': return "Error: La generación no ha sido Eliminada";
            }
            return "";
        }
        
        private function getNoModelos($find=""){
            $find_seguro = addslashes(trim($find));
            $hist_seguro = intval($this->formulario["historico"]);
            $sql = "SELECT count(*) as total FROM Modelos as M INNER JOIN Marcas as MA ON (M._idMarca = MA._id) WHERE M.Estatus = $hist_seguro AND M.Modelo LIKE '%$find_seguro%'";
            $row = $this->conn->fetch($this->conn->query($sql));            
            return $row["total"];
        }
        
        private function getModelos($find="", $skip=0, $limit=10){
            $array = array();
            $find_seguro = addslashes(trim($find));
            $hist_seguro = intval($this->formulario["historico"]);
            $sql = "SELECT M.*, MA.Marca FROM Modelos as M INNER JOIN Marcas as MA ON (M._idMarca = MA._id) WHERE M.Estatus = $hist_seguro AND M.Modelo LIKE '%$find_seguro%' LIMIT $skip, $limit";
            $id = $this->conn->query($sql);
            while($row = $this->conn->fetch($id)){
                $row["foto"] = file_exists("../../../../../../images/Marcas/".$row["_idMarca"].".png");
                array_push($array, $row);
            }
            return $array;
        }
        
        private function getMarcas(){
            $array = array();
            $hist_seguro = intval($this->formulario["historico"]);
            $sql = "SELECT * FROM Marcas WHERE Estatus = $hist_seguro ORDER BY Marca ASC";
            $id = $this->conn->query($sql);
            while($row = $this->conn->fetch($id)){
                $row["foto"] = file_exists("../../../../../../images/Marcas/".$row["_id"].".png");
                array_push($array, $row);
            }
            return $array;
        }
        
        private function getAnios(){
            $array = array();
            $id_seguro = intval($this->formulario["_idModelo"]);
            $sql = "SELECT * FROM Anios WHERE _idModelo = $id_seguro ORDER BY Anio ASC";
            $id= $this->conn->query($sql);
            while($row = $this->conn->fetch($id)){
                array_push($array, $row);
            }
            return $array;
        }
        
        private function setModelos (){
            $sql = "";
            $usr_seguro = addslashes($_SESSION["nombre"]);
            $fecha_actual = date("Y-m-d H:i:s");
            $accionLog = ""; $detallesLog = "";

            if($this->formulario["opc"] == 'new' || $this->formulario["opc"] == 'edit'){
                $mod_seguro = addslashes(trim($this->formulario["Modelo"]));
                $marca_segura = intval($this->formulario["_idMarca"]);
                $id_cond = ($this->formulario["opc"] == 'edit') ? " AND _id != " . intval($this->formulario["_id"]) : "";
                $check = $this->conn->query("SELECT _id FROM Modelos WHERE Modelo = '$mod_seguro' AND _idMarca = $marca_segura" . $id_cond);
                if($this->conn->fetch($check)){
                    $this->jsonData["mensaje"] = "Este vehículo ya existe dentro de la marca seleccionada.";
                    return false;
                }
            }

            if($this->formulario["opc"] == 'new'){
                $mod_seguro = addslashes(trim($this->formulario["Modelo"]));
                $marca_segura = intval($this->formulario["_idMarca"]);
                $sql = "INSERT INTO Modelos (Modelo, Estatus, _idMarca, USRCreacion, USRModificacion, FechaCreacion, FechaModificacion) VALUES ('$mod_seguro', 1, $marca_segura, '$usr_seguro', '$usr_seguro', '$fecha_actual', '$fecha_actual')";
                $accionLog = "CREAR_VEHICULO"; $detallesLog = "Se registró el vehículo: $mod_seguro";
            } else {
                $id_seguro = intval($this->formulario["_id"]);
                
                $sqlOld = "SELECT Modelo FROM Modelos WHERE _id = $id_seguro";
                $rowOld = $this->conn->fetch($this->conn->query($sqlOld));
                $nombreV = $rowOld ? $rowOld['Modelo'] : "Vehículo ID: $id_seguro";

                if($this->formulario["opc"] == 'edit'){
                    $mod_seguro = addslashes(trim($this->formulario["Modelo"]));
                    $marca_segura = intval($this->formulario["_idMarca"]);
                    $sql = "UPDATE Modelos SET Modelo='$mod_seguro', _idMarca=$marca_segura, USRModificacion='$usr_seguro', FechaModificacion='$fecha_actual' WHERE _id= $id_seguro";
                    $accionLog = "EDITAR_VEHICULO"; $detallesLog = "Se actualizaron los datos del vehículo: $mod_seguro";
                } else if($this->formulario["opc"] == 'disabled'){
                    $sql = "UPDATE Modelos SET Estatus=0, USRModificacion='$usr_seguro', FechaModificacion='$fecha_actual' WHERE _id= $id_seguro";
                    $accionLog = "DESACTIVAR_VEHICULO"; $detallesLog = "Se desactivó el vehículo: $nombreV";
                } else if($this->formulario["opc"] == 'enabled'){
                    $sql = "UPDATE Modelos SET Estatus=1, USRModificacion='$usr_seguro', FechaModificacion='$fecha_actual' WHERE _id= $id_seguro";
                    $accionLog = "ACTIVAR_VEHICULO"; $detallesLog = "Se reactivó el vehículo: $nombreV";
                } else if($this->formulario["opc"] == 'delete'){
                    $sql = "DELETE FROM Modelos WHERE _id= $id_seguro";
                    $this->conn->query("DELETE FROM Anios WHERE _idModelo = $id_seguro");
                    $accionLog = "ELIMINAR_VEHICULO"; $detallesLog = "Se eliminó permanentemente el vehículo ID: $id_seguro";
                }
            }

            if($this->conn->query($sql)){
                Funciones::guardarBitacora($this->conn, 'Vehículos', $accionLog, $detallesLog);
                return true;
            } else {
                $this->jsonData["error"] = $this->conn->error;
                return false;
            }
        }
        
        private function setAnios(){
            $usr_seguro = addslashes($_SESSION["nombre"]);
            $fecha_actual = date("Y-m-d H:i:s");
            $accionLog = ""; $detallesLog = "";

            if($this->formulario["opc"] == 'newanios' || $this->formulario["opc"] == 'editanio'){
                $anio_seguro = addslashes(trim($this->formulario["Anio"]));
                $idmod_seguro = intval($this->formulario["_idModelo"]);
                $id_cond = ($this->formulario["opc"] == 'editanio') ? " AND _id != " . intval($this->formulario["_id"]) : "";
                $check = $this->conn->query("SELECT _id FROM Anios WHERE Anio = '$anio_seguro' AND _idModelo = $idmod_seguro" . $id_cond);
                if($this->conn->fetch($check)){
                    $this->jsonData["mensaje"] = "Esta generación/año ya existe para este vehículo.";
                    return false;
                }
            }

            if($this->formulario["opc"] == 'newanios'){
                $anio_seguro = addslashes(trim($this->formulario["Anio"]));
                $idmod_seguro = intval($this->formulario["_idModelo"]);
                $sql = "INSERT INTO Anios (Anio, _idModelo, USRCreacion, USREdicion, FechaCreacion, FechaModificacion) VALUES ('$anio_seguro', $idmod_seguro, '$usr_seguro', '$usr_seguro', '$fecha_actual', '$fecha_actual')";
                $accionLog = "CREAR_GENERACION"; $detallesLog = "Generación creada: $anio_seguro (Vehículo ID: $idmod_seguro)";
            } else {
                $id_seguro = intval($this->formulario["_id"]);
                $nombreModelo = isset($this->formulario["NombreModelo"]) ? addslashes($this->formulario["NombreModelo"]) : "Vehículo ID: " . intval($this->formulario["_idModelo"]);

                if($this->formulario["opc"] == 'editanio'){
                    $anio_seguro = addslashes(trim($this->formulario["Anio"]));
                    $sqlOld = "SELECT Anio FROM Anios WHERE _id = $id_seguro";
                    $rowOld = $this->conn->fetch($this->conn->query($sqlOld));
                    $anioViejo = $rowOld ? $rowOld['Anio'] : 'Desconocido';

                    $sql = "UPDATE Anios SET Anio = '$anio_seguro', USREdicion='$usr_seguro', FechaModificacion='$fecha_actual' WHERE _id= $id_seguro";
                    $accionLog = "EDITAR_GENERACION"; 
                    $detallesLog = "<b>$nombreModelo</b> <br> <small class='text-muted'>Cambió de: $anioViejo ➔ <b>$anio_seguro</b></small>";
                } else if($this->formulario["opc"] == 'deleteanio'){
                    $sqlOld = "SELECT Anio FROM Anios WHERE _id = $id_seguro";
                    $rowOld = $this->conn->fetch($this->conn->query($sqlOld));
                    $anioViejo = $rowOld ? $rowOld['Anio'] : 'Desconocido';

                    $sql = "DELETE FROM Anios WHERE _id= $id_seguro";
                    $accionLog = "ELIMINAR_GENERACION"; 
                    $detallesLog = "<b>$nombreModelo</b> <br> <small class='text-danger'>Se eliminó la generación: $anioViejo</small>";
                }
            }
            
            if($this->conn->query($sql)){
                Funciones::guardarBitacora($this->conn, 'Vehículos (Años)', $accionLog, $detallesLog);
                return true;
            } else {
                $this->jsonData["error"] = $this->conn->error;
                return false;
            }
        }
    }
    
    $app = new Modelos($array_principal);
    $app->principal();
?>