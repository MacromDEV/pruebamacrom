<?php
    session_name("loginUsuario");
    session_start();
    require_once "../../../../Clases/dbconectar.php";
    require_once "../../../../Clases/ConexionMySQL.php";
    require_once "../../../../Clases/SendMail.php";
    require_once "../../../../Clases/Funciones.php";

    date_default_timezone_set('America/Mexico_City');
    
    class Usuarios{
        private $conn;
        private $jsonData = array("Bandera"=>0,"mensaje"=>"");
        private $formulario = array();
        private $correo;
        private $obj;
        
        public function __construct($array) {
            $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
            $this->correo = new SendMail();
        }

        public function __destruct() {
            unset($this->conn);
        }
        
        public function principal(){
            $this->formulario = json_decode(file_get_contents('php://input'));
            
            switch ($this->formulario->usuarios->opc) {
                case 'buscar':
                    $datos = $this->getUsuarios();
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Entro a la funcion";
                    $this->jsonData["Data"] = $datos;
                    $this->jsonData["Rol_Usuario"] = $_SESSION["rol"] ?? 'Usuario'; 
                    break;
                    
                case 'activar':
                case 'borrar':
                        $idUser = (int)$this->formulario->usuarios->id;
                        $sqlOld = "SELECT Username FROM Usuarios WHERE _id = $idUser";
                        $rowOld = $this->conn->fetch($this->conn->query($sqlOld));
                        $nombreU = $rowOld ? $rowOld['Username'] : "ID: $idUser";

                        if($this->setUsuarios($this->formulario->usuarios->opc=="borrar"? 0:1)){
                            if($this->setSeguridad($this->formulario->usuarios->opc=="borrar"? 0:1)){
                                $this->jsonData["Bandera"] = 1;
                                $this->jsonData["mensaje"] = "El usuario se ha actualizado";
                                
                                $accionBitacora = $this->formulario->usuarios->opc == "borrar" ? 'DESACTIVAR_USUARIO' : 'ACTIVAR_USUARIO';
                                $textoBitacora = $this->formulario->usuarios->opc == "borrar" ? 'desactivó' : 'reactivó';
                                Funciones::guardarBitacora($this->conn, 'Usuarios (Gestión)', $accionBitacora, "Se $textoBitacora la cuenta del empleado: $nombreU");
                                
                            }else{
                                $this->jsonData["Bandera"] = 0;
                                $this->jsonData["mensaje"] = "Error: al actualizar la seguridad";
                            }
                        }else{
                            $this->jsonData["Bandera"] = 0;
                            $this->jsonData["mensaje"] = "Error: al actualizar el usuario";
                        }
                    break;
                    
                case 'edit':
                    $dato = $this->getUsuario();
                    if(count($dato)){
                        $this->jsonData["Bandera"] = 1;
                        $this->jsonData["Data"] = $dato;
                        $this->jsonData["img"] = file_exists("../../../../Images/usuarios/".$this->formulario->usuarios->id.".png");
                    }
                    break;
                    
                case 'pass':
                    if($this->setPassword()){
                        $this->Sendusernamexemail($this->formulario->usuarios->nombre, $this->formulario->usuarios->username, 
                        $this->formulario->usuarios->pass, $this->formulario->usuarios->email);
                        $this->jsonData["Bandera"] = 1;
                        $this->jsonData["mensaje"] = "El password ha sido cambiado";
                        
                        $nombrePass = addslashes($this->formulario->usuarios->username);
                        Funciones::guardarBitacora($this->conn, 'Usuarios (Gestión)', 'CAMBIO_PASSWORD', "Se forzó la regeneración de contraseña para: $nombrePass");
                        
                    }else{
                        $this->jsonData["Bandera"] = 0;
                        $this->jsonData["mensaje"] = "Error al intentar cambiar el password";
                    }
                    break;
                    
                case 'get_roles':
                    $sql = "SELECT DISTINCT rol_nombre FROM Permisos_Roles ORDER BY rol_nombre ASC";
                    $roles = $this->conn->fetch_all($this->conn->query($sql));
                    $array_roles = array();

                    foreach($roles as $r){
                        $array_roles[] = array(
                            "value" => $r['rol_nombre'], 
                            "descripcion" => ucfirst($r['rol_nombre'])
                        );
                    }
                    $this->jsonData["Roles"] = $array_roles;
                    $this->jsonData["Bandera"] = 1;
                    break;

                case 'eliminar_permanente':
                    $rol_actual = $_SESSION["rol"] ?? '';
                    
                    if ($rol_actual != 'root' && $rol_actual != 'Admin') {
                        $this->jsonData["Bandera"] = 0;
                        $this->jsonData["mensaje"] = "Permisos insuficientes para esta acción.";
                        break;
                    }
                    
                    $idUser = (int)$this->formulario->usuarios->id;
                    
                    if ($idUser == ($_SESSION["_id"] ?? 0) || $idUser == ($_SESSION["iduser"] ?? 0)) {
                        $this->jsonData["Bandera"] = 0;
                        $this->jsonData["mensaje"] = "Seguridad: No puedes eliminar tu propia cuenta.";
                        break;
                    }

                    $sqlInfo = "SELECT Username FROM Usuarios WHERE _id = $idUser";
                    $uInfo = $this->conn->fetch($this->conn->query($sqlInfo));
                    if ($uInfo) {
                        $rutaImg = "../../../../Images/usuarios/" . $uInfo["Username"] . ".png";
                        if (file_exists($rutaImg)) {
                            @unlink($rutaImg); 
                        }
                    }

                    $this->conn->query("DELETE FROM Seguridad WHERE _idUsuarios = $idUser");
                    $del = $this->conn->query("DELETE FROM Usuarios WHERE _id = $idUser");
                    
                    if ($del) {
                        $this->jsonData["Bandera"] = 1;
                        $this->jsonData["mensaje"] = "Usuario eliminado permanentemente.";
                        
                        if ($uInfo) {
                            Funciones::guardarBitacora($this->conn, 'Usuarios (Gestión)', 'ELIMINAR_USUARIO', "Se eliminó de forma definitiva la cuenta de empleado: {$uInfo['Username']}");
                        }
                        
                    } else {
                        $this->jsonData["Bandera"] = 0;
                        $this->jsonData["mensaje"] = "Error al intentar eliminar el usuario en la BD.";
                    }
                    break;

                default:
                    break;
            }
            print json_encode($this->jsonData);
        }
        
        private function Sendusernamexemail($nombre, $username, $pass, $email){
            $subjet = "Recuperacion de password";
            $correos = array();
            $body = file_get_contents("../../../../View/Resetusuario.html");
            $body = str_replace('{nombre}',$nombre,$body);
            $body = str_replace('{username}',$username, $body);
            $body = str_replace('{password}',$pass, $body);
            array_push($correos, array("email"=>$email, "nombre"=>$nombre)); 
            $this->correo->Send($subjet,$correos,$body);
        }
        
        private function getUsuarios(){
            $array = array();
            if ($_SESSION["rol"]=="root"){
                $sql = "select US.*,SG.Tipo_usuario from Usuarios as US inner join Seguridad as SG on (SG._idUsuarios = US._id) where US.Username not in ('root') and US.estatus = " . $this->formulario->usuarios->historico;
            }else if($_SESSION["rol"]=="Admin"){
                $sql = "select US.*,SG.Tipo_usuario from Usuarios as US inner join Seguridad as SG on (SG._idUsuarios = US._id) where US.Username not in ('Admin','root') and US.estatus = ". $this->formulario->usuarios->historico;
            }else{
                $sql = "select US.*,SG.Tipo_usuario from Usuarios as US inner join Seguridad as SG on (SG._idUsuarios = US._id) where US.Username not in ('Admin','root') and US.estatus = ". $this->formulario->usuarios->historico;
            }
            $id = $this->conn->query($sql);
            if($id){
                while($row = $this->conn->fetch($id)){
                    array_push($array, $row);
                }
            }
            return $array;
        }
        
        private function getUsuario(){
            $sql = "Select *,SG._id as idseguridad from Usuarios as US inner join Seguridad as SG on (SG._idUsuarios = US._id) where US._id=".$this->formulario->usuarios->id;
            return $this->conn->fetch($this->conn->query($sql));
        }
        
        private function setUsuarios($estatus = 0){
            $sql = "UPDATE Usuarios SET estatus = $estatus where _id={$this->formulario->usuarios->id}";
            return $this->conn->query($sql);
        }
        
        private function setSeguridad($estatus = 0){
            $sql = "UPDATE Seguridad SET estatus = $estatus where _idUsuarios={$this->formulario->usuarios->id}";
            return $this->conn->query($sql);
        }
        
        private function setPassword(){
            $nuevoHash = password_hash($this->formulario->usuarios->pass, PASSWORD_DEFAULT);
            
            $sql = "UPDATE Seguridad SET password='{$nuevoHash}' where _id=".$this->formulario->usuarios->id;
            return $this->conn->query($sql);
        }
    }
    
    $app = new Usuarios($array_principal);
    $app->principal();
?>