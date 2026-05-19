<?php
session_name("loginUsuario");
session_start();
require_once "../../../../Clases/dbconectar.php";
require_once "../../../../Clases/ConexionMySQL.php";
require_once "../../../../Clases/Funciones.php";
date_default_timezone_set('America/Mexico_City');

class Contacto {
    private $conn;
    private $jsonData = array("Bandera"=>0, "mensaje"=>"", "Data"=>array(), "msgnuevos"=>0);
    private $formulario = array();
    private $fecha;

    public function __construct($array) {
        $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
        $this->fecha = date("Y-m-d H:i:s");
    }

    public function __destruct() {
        unset($this->conn);
    }

    public function main() {
        $json = json_decode(file_get_contents('php://input'), true);
        $this->formulario = $json['contacto'] ?? $json;
        
        $opc = $this->formulario['opc'] ?? '';
        
        switch($opc) {
            case "get":
                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Data"] = $this->getContactos();
                $this->jsonData["msgnuevos"] = $this->getmsgnuevos();
                break;
                
            case 'set':
                $id = (int)($this->formulario['id'] ?? 0);
                $nombreRemitente = $this->getNombreRemitente($id);
                
                if($this->set_leido()) {
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["Data"] = $this->getContacto();
                    $this->jsonData["msgnuevos"] = $this->getmsgnuevos();
                    
                    Funciones::guardarBitacora($this->conn, 'Contacto', 'LECTURA_MENSAJE', "Abrió para lectura el mensaje de: $nombreRemitente");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al intentar consultar la base de datos.";
                }
                break;
            
            case 'delete':
                $id = (int)($this->formulario['id'] ?? 0);
                $nombreRemitente = $this->getNombreRemitente($id);
                
                if($this->conn->query("DELETE FROM Contacto WHERE _id = $id")) {
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "El mensaje fue eliminado.";
                    $this->jsonData["Data"] = $this->getContactos();
                    $this->jsonData["msgnuevos"] = $this->getmsgnuevos();
                    
                    Funciones::guardarBitacora($this->conn, 'Contacto', 'ELIMINAR_MENSAJE', "Eliminó permanentemente el mensaje de: $nombreRemitente");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al intentar eliminar el mensaje.";
                }
                break;

            case 'toggle_read':
                $id = (int)($this->formulario['id'] ?? 0);
                $estado = (int)($this->formulario['estado'] ?? 0);
                $nombreRemitente = $this->getNombreRemitente($id);
                
                $this->conn->query("UPDATE Contacto SET leido = $estado WHERE _id = $id");
                
                $accionTxt = $estado ? "leído" : "no leído";
                Funciones::guardarBitacora($this->conn, 'Contacto', 'CAMBIO_ESTADO_LECTURA', "Marcó como $accionTxt el mensaje de: $nombreRemitente");

                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Data"] = $this->getContactos();
                $this->jsonData["msgnuevos"] = $this->getmsgnuevos();
                break;

            case 'toggle_star':
                $id = (int)($this->formulario['id'] ?? 0);
                $nombreRemitente = $this->getNombreRemitente($id);
                
                $this->conn->query("UPDATE Contacto SET destacado = IF(destacado = 1, 0, 1) WHERE _id = $id");
                Funciones::guardarBitacora($this->conn, 'Contacto', 'TOGGLE_DESTACADO', "Cambió el estado de 'Destacado' para el mensaje de: $nombreRemitente");

                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Data"] = $this->getContactos();
                $this->jsonData["msgnuevos"] = $this->getmsgnuevos();
                break;
        }
        header('Content-Type: application/json');
        print json_encode($this->jsonData);
    }

    private function getNombreRemitente($id) {
        $sql = "SELECT nombre FROM Contacto WHERE _id = $id LIMIT 1";
        $res = $this->conn->query($sql);
        if ($res && $row = $this->conn->fetch($res)) {
            return html_entity_decode(stripslashes($row['nombre']), ENT_QUOTES, 'UTF-8');
        }
        return "ID: $id";
    }

    private function getContactos() {
        $array = array();
        $sql = "SELECT * FROM Contacto ORDER BY destacado DESC, fecha DESC";
        $res = $this->conn->query($sql);
        
        if($res) {
            while ($row = $this->conn->fetch($res)){
                array_push($array, $row);
            }
        }
        return $array;
    }

    private function set_leido() {
        $id = (int)($this->formulario['id'] ?? 0);
        $sql = "UPDATE Contacto SET leido = 1 WHERE _id = $id";
        return $this->conn->query($sql);
    }

    private function getContacto() {
        $id = (int)($this->formulario['id'] ?? 0);
        $sql = "SELECT * FROM Contacto WHERE _id = $id";
        return $this->conn->fetch($this->conn->query($sql));
    }

    private function getmsgnuevos() {
        $sql = "SELECT count(_id) as noleidos FROM Contacto WHERE leido = 0";
        $row = $this->conn->fetch($this->conn->query($sql));
        return $row["noleidos"] ?? 0;
    }
}

$app = new Contacto($array_principal);
$app->main();
?>