<?php
    session_name("loginUsuario");
    session_start();
    require_once "../../../../Clases/dbconectar.php";
    require_once "../../../../Clases/ConexionMySQL.php";
    require_once "../../../../Clases/Funciones.php"; 
    date_default_timezone_set('America/Mexico_City');
    
    class Categorias {
        private $conn;
        private $jsonData = array("Bandera"=>0,"mensaje"=>"");
        private $formulario = array();
        private $foto = array(); 

        public function __construct($array) {
            $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
        }

        public function __destruct() {
            unset($this->conn);
        }
        
        public function principal() {
            $this->formulario = array_map("htmlspecialchars", $_POST);
            $this->foto = isset($_FILES) ? $_FILES : array();
            
            switch ($this->formulario["opc"]) {
                case 'buscar':
                    $find = isset($this->formulario["find"]) ? $this->formulario["find"] : "";
                    $skip = isset($this->formulario["skip"]) ? intval($this->formulario["skip"]) : 0;
                    $limit = isset($this->formulario["limit"]) ? intval($this->formulario["limit"]) : 10;

                    $this->jsonData["Data"]["noRegistros"] = $this->getNoCategorias($find);
                    $this->jsonData["Data"]["Registros"] = $this->getCategorias($find, $skip, $limit);
                    $this->jsonData["Bandera"] = 1;
                    break;
                    
                case 'edit':
                case 'new':
                case 'disabled':
                case 'enabled':
                case 'delete':
                    if($this->setCategorias()){
                        $this->formulario["lastid"] = ($this->formulario["opc"]=="new") ? $this->conn->last_id() : $this->formulario["_id"];
                        
                        if($this->formulario["opc"] != 'delete' && count($this->foto)!=0){
                            $this->subirImagen();
                        }

                        // === BITÁCORA ===
                        $nombreCat = isset($this->formulario["Categoria"]) ? $this->formulario["Categoria"] : "Categoría ID: " . $this->formulario["lastid"];
                        $id_afectado = $this->formulario["lastid"];
                        
                        if ($this->formulario["opc"] == "new") {
                            $det = "Se creó una nueva categoría: $nombreCat";
                            Funciones::guardarBitacora($this->conn, 'Categorías', 'NUEVA_CATEGORIA', $det);
                        } else {
                            $accionLog = ""; $det = "";
                            if($this->formulario["opc"] == "edit") {
                                $accionLog = "EDITAR_CATEGORIA"; $det = "Se actualizaron los datos de la categoría: $nombreCat";
                            } else if($this->formulario["opc"] == "disabled") {
                                $accionLog = "DESACTIVAR_CATEGORIA"; $det = "Se desactivó la categoría: $nombreCat";
                            } else if($this->formulario["opc"] == "enabled") {
                                $accionLog = "ACTIVAR_CATEGORIA"; $det = "Se reactivó la categoría: $nombreCat";
                            } else if($this->formulario["opc"] == "delete") {
                                $accionLog = "ELIMINAR_CATEGORIA"; $det = "Se eliminó de forma permanente la categoría ID: $id_afectado";
                            }
                            Funciones::guardarBitacora($this->conn, 'Categorías', $accionLog, $det);
                        }

                        $this->jsonData["Bandera"] = 1;
                        $this->jsonData["mensaje"] = $this->getMensajeSuccess();
                    }else{
                        $this->jsonData["Bandera"] = 0;
                        if(empty($this->jsonData["mensaje"])){
                            $this->jsonData["mensaje"] = $this->getMensajeError();
                        }
                    }
                    break;
            }
            print json_encode($this->jsonData);
        }
        
        private function getMensajeSuccess(){
            switch($this->formulario["opc"]){
                case 'new': return "La Categoría ha sido Creada";
                case 'edit': return "La Categoría ha sido Modificada";
                case 'disabled': return "La Categoría ha sido Desactivada";
                case 'enabled': return "La Categoría ha sido Activada";
                case 'delete': return "La Categoría fue Eliminada Permanentemente";
            }
            return "";
        }
        
        private function getMensajeError(){
            switch($this->formulario["opc"]){
                case 'new': return "Error: La Categoría no ha sido Creada";
                case 'edit': return "Error: La Categoría no ha sido Modificada";
                case 'disabled': return "Error: La Categoría no ha sido Desactivada";
                case 'enabled': return "Error: La Categoría no ha sido Activada";
                case 'delete': return "Error al intentar eliminar la Categoría";
            }
            return "";
        }

        private function getNoCategorias($find=""){
            $find_seguro = addslashes(trim($find));
            $historico = isset($this->formulario["historico"]) ? intval($this->formulario["historico"]) : 1;
            $sql = "SELECT count(*) as total FROM Categorias WHERE Status = $historico AND Categoria LIKE '%$find_seguro%'";
            $row = $this->conn->fetch($this->conn->query($sql));
            return $row["total"];
        }
        
        private function getCategorias($find="", $skip=0, $limit=10){
            $array = array();
            $find_seguro = addslashes(trim($find));
            $historico = isset($this->formulario["historico"]) ? intval($this->formulario["historico"]) : 1;
            $sql = "SELECT * FROM Categorias WHERE Status = $historico AND Categoria LIKE '%$find_seguro%' ORDER BY Categoria ASC LIMIT $skip, $limit";
            $id = $this->conn->query($sql);
            if($id){
                while($row= $this->conn->fetch($id)){
                    $row["foto"] = file_exists("../../../../../../images/Categorias/".$row["_id"].".png");
                    array_push($array, $row);
                }
            }
            return $array;
        }
        
        private function setCategorias (){
            $fechaActual = date("Y-m-d H:i:s");
            $usuario = $_SESSION["nombre"] ?? 'Sistema';

            if($this->formulario["opc"] == 'new' || $this->formulario["opc"] == 'edit') {
                $cat_segura = addslashes(trim($this->formulario["Categoria"]));
                
                $id_condicion = ($this->formulario["opc"] == 'edit') ? " AND _id != " . intval($this->formulario["_id"]) : "";

                $checkSql = "SELECT _id FROM Categorias WHERE Categoria = '$cat_segura'" . $id_condicion;
                $resCheck = $this->conn->query($checkSql);
                
                if ($this->conn->fetch($resCheck)) {
                    $this->jsonData["mensaje"] = "Ya existe una categoría registrada con ese nombre.";
                    return false;
                }
            }

            if($this->formulario["opc"]=="new"){
                $cat_segura = addslashes(trim($this->formulario["Categoria"]));
                $sql = "INSERT INTO Categorias (Categoria, Status, USRCreacion,USREdicion, FechaCreacion, FechaModificacion ) values "
                        . "('$cat_segura','1','$usuario','$usuario','$fechaActual','$fechaActual')";
            }else if($this->formulario["opc"]=="edit"){
                $cat_segura = addslashes(trim($this->formulario["Categoria"]));
                $sql = "UPDATE Categorias SET Categoria='$cat_segura', USREdicion='$usuario', FechaModificacion='$fechaActual' where _id= ".$this->formulario["_id"];
            }else if($this->formulario["opc"]=="disabled"){
                $sql = "UPDATE Categorias SET Status=0, USREdicion='$usuario', FechaModificacion='$fechaActual' where _id= ".$this->formulario["_id"];
            }else if($this->formulario["opc"]=="enabled"){
                $sql = "UPDATE Categorias SET Status=1, USREdicion='$usuario', FechaModificacion='$fechaActual' where _id= ".$this->formulario["_id"];
            }else if($this->formulario["opc"]=="delete"){
                $archivo = "../../../../../../images/Categorias/".$this->formulario["_id"].".png";
                if(file_exists($archivo)){ unlink($archivo); }
                $sql = "DELETE FROM Categorias WHERE _id= ".$this->formulario["_id"];
            }
            
            return $this->conn->query($sql) or $this->jsonData["error"] = $this->conn->error;
        }
        
        private function subirImagen(){
            if(isset($this->foto["file"]) && $this->foto["file"]["name"]!="" && $this->foto["file"]["size"]!=0){
                $subdir ="../../../../../../"; 
                $dir = "images/Categorias/";
                $archivo = $this->formulario["lastid"].".png";
                if(!is_dir($subdir.$dir)){
                    mkdir($subdir.$dir,0755, true);
                }
                if(move_uploaded_file($this->foto["file"]["tmp_name"], $subdir.$dir.$archivo)){
                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }
    }
    
    $app = new Categorias($array_principal);
    $app->principal();
?>