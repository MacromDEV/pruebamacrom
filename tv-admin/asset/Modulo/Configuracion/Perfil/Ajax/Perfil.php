<?php
session_name("loginUsuario");
session_start();
require_once "../../../../Clases/dbconectar.php";
require_once "../../../../Clases/ConexionMySQL.php";
require_once "../../../../Clases/Funciones.php";

date_default_timezone_set('America/Mexico_City');

class Perfil {
    private $conn;
    private $jsonData = array("Bandera"=>0, "mensaje"=>"");
    private $formulario = array();
    private $foto = array();

    public function __construct($array) {
        $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
    }

    public function __destruct() {
        unset($this->conn);
    }

    public function principal() {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) {
            $this->formulario = $json['usuarios'] ?? $json;
        } else {
            $this->formulario = array_map("htmlspecialchars", $_POST);
        }
        $this->foto = $_FILES ?? [];

        $opc = $this->formulario['opc'] ?? '';

        switch ($opc) {
            case 'get':
                $this->jsonData["Data"] = $this->getUsuario();
                $usr = $this->jsonData["Data"]["Username"] ?? '';
                $this->jsonData["img"] = file_exists("../../../../Images/usuarios/".$usr.".png");
                $this->jsonData["Bandera"] = 1;
                break;

            case 'pass':
                if ($this->setPassword()) {
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "La contraseña se actualizó correctamente.";
                    
                    Funciones::guardarBitacora($this->conn, 'Mi Perfil', 'CAMBIO_PASSWORD_PROPIO', "El usuario actualizó su propia contraseña de acceso.");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al intentar cambiar la contraseña.";
                }
                break;

            case 'save':
                if ($this->setUsuarios()) {
                    if (isset($this->foto["file"]) && $this->foto["file"]["size"] > 0) {
                        $this->subirImagen();
                    }
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "El perfil se ha actualizado de manera satisfactoria."; 
                    
                    Funciones::guardarBitacora($this->conn, 'Mi Perfil', 'EDITAR_PERFIL_PROPIO', "El usuario actualizó su información personal o de contacto.");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al guardar los datos del perfil.";
                }
                break;
                
            case 'delete_foto':
                $username = $this->formulario['Username'] ?? '';
                if ($username != '') {
                    $ruta = "../../../../Images/usuarios/" . $username . ".png";
                    if (file_exists($ruta)) {
                        unlink($ruta);
                    }
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Foto de perfil eliminada correctamente.";
                    
                    Funciones::guardarBitacora($this->conn, 'Mi Perfil', 'ELIMINAR_FOTO_PERFIL', "El usuario eliminó su avatar/foto de perfil.");
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al identificar al usuario.";
                }
                break;
        }
        
        header('Content-Type: application/json');
        print json_encode($this->jsonData);
    }

    private function getUsuario() {
        $usr = $_SESSION["usr"] ?? '';
        $sql = "SELECT US.*, S._id as id_seguridad, S.Tipo_usuario 
                FROM Usuarios as US 
                INNER JOIN Seguridad as S ON (US._id = S._idUsuarios) 
                WHERE US.username='$usr'";
        return $this->conn->fetch($this->conn->query($sql));
    }

    private function setPassword() {
        $pass = $this->formulario['pass'] ?? '';
        $id = intval($this->formulario['id'] ?? 0);
        $sql = "UPDATE Seguridad SET password=SHA('$pass') WHERE _id=$id";
        return $this->conn->query($sql);
    }

    private function setUsuarios() {
        $id = intval($this->formulario['_id'] ?? 0);
        $usr_mod = $_SESSION["usr"] ?? 'Sistema';
        $fecha = date("Y-m-d H:i:s");

        $sql = "UPDATE Usuarios SET 
                Nombre = '{$this->formulario["Nombre"]}', 
                ApPaterno = '{$this->formulario["ApPaterno"]}', 
                ApMaterno = '{$this->formulario["ApMaterno"]}', 
                Domicilio = '{$this->formulario["Domicilio"]}',
                Colonia = '{$this->formulario["Colonia"]}', 
                Ciudad = '{$this->formulario["Ciudad"]}', 
                Estado = '{$this->formulario["Estado"]}', 
                Telefono = '{$this->formulario["Telefono"]}', 
                email = '{$this->formulario["email"]}', 
                FechaModificacion = '$fecha', 
                USRModificacion = '$usr_mod' 
                WHERE _id = $id";
        return $this->conn->query($sql);
    }

    private function subirImagen() {
        $username = $this->formulario["Username"] ?? 'default';
        $subdir = "../../../../Images/usuarios/";
        $archivo = $username . ".png";
        if (!is_dir($subdir)) { mkdir($subdir, 0755, true); }
        return move_uploaded_file($this->foto["file"]["tmp_name"], $subdir.$archivo);
    }
}

$app = new Perfil($array_principal);
$app->principal();
?>