<?php
    require_once "../../../../Clases/dbconectar.php";
    require_once "../../../../Clases/ConexionMySQL.php";
    require_once "../../../../Clases/Funciones.php";
    session_name("loginUsuario");
    session_start();
    date_default_timezone_set('America/Mexico_City');

    class PermisosAdmin {
        private $conn;
        private $jsonData = array("Bandera"=>0, "mensaje"=>"");
        private $formulario;

        public function __construct($array) {
            $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
        }

        public function main(){
            if(!isset($_SESSION["rol"]) || ($_SESSION["rol"] != 'root' && $_SESSION["rol"] != 'Admin')) {
                $this->jsonData["mensaje"] = "Acceso denegado.";
                print json_encode($this->jsonData);
                return;
            }

            $this->formulario = json_decode(file_get_contents('php://input'));
            
            if(isset($this->formulario->permisos)){
                switch($this->formulario->permisos->opc){
                    case 'get_modulos':
                        $this->getModulos($this->formulario->permisos->rol_seleccionado);
                        break;
                    case 'toggle':
                        $this->togglePermiso(
                            $this->formulario->permisos->rol_seleccionado, 
                            $this->formulario->permisos->id_modulo, 
                            $this->formulario->permisos->estado
                        );
                        break;
                    case 'get_roles':
                        $sql = "SELECT DISTINCT rol_nombre FROM Permisos_Roles ORDER BY rol_nombre ASC";
                        $roles_db = $this->conn->fetch_all($this->conn->query($sql));
                        $roles_array = [];
                        foreach($roles_db as $r) {
                            $roles_array[] = $r['rol_nombre'];
                        }
                        $this->jsonData["Roles"] = $roles_array;
                        $this->jsonData["Bandera"] = 1;
                        break;
                    case 'delete_rol':
                        $this->eliminarRol($this->formulario->permisos->rol_seleccionado);
                        break;
                }
            }
            print json_encode($this->jsonData);
        }

        private function getModulos($rol){
            $rol_seguro = addslashes($rol);
            $sql = "SELECT m.*, 
                    IF(EXISTS(SELECT 1 FROM Permisos_Roles p WHERE p.id_modulo = m.id_modulo AND p.rol_nombre = '$rol_seguro'), 1, 0) as tiene_permiso 
                    FROM Modulos_Admin m 
                    ORDER BY m.grupo ASC, m.titulo ASC";
            
            $this->jsonData["Modulos"] = $this->conn->fetch_all($this->conn->query($sql));
            $this->jsonData["Bandera"] = 1;
        }

        private function togglePermiso($rol, $id_modulo, $estado){
            $rol_seguro = addslashes($rol);
            $id_seguro = intval($id_modulo);

            $nombre_modulo = "ID $id_seguro";
            $sqlMod = "SELECT titulo FROM Modulos_Admin WHERE id_modulo = $id_seguro LIMIT 1";
            $resMod = $this->conn->query($sqlMod);
            if ($rowMod = $this->conn->fetch($resMod)) {
                $nombre_modulo = $rowMod['titulo'];
            }

            if($estado) {
                $sql = "INSERT INTO Permisos_Roles (rol_nombre, id_modulo) VALUES ('$rol_seguro', $id_seguro)";
                $accionLog = 'ASIGNAR_PERMISO';
                $estadoTexto = 'asignado';
            } else {
                $sql = "DELETE FROM Permisos_Roles WHERE rol_nombre = '$rol_seguro' AND id_modulo = $id_seguro";
                $accionLog = 'REVOCAR_PERMISO';
                $estadoTexto = 'revocado';
            }
            
            if($this->conn->query($sql)){
                $det = "Permiso $estadoTexto - Rol afectado: '$rol_seguro' | Módulo: $nombre_modulo";
                Funciones::guardarBitacora($this->conn, 'Permisos del Sistema', $accionLog, $det);

                $this->jsonData["Bandera"] = 1;
                $this->jsonData["mensaje"] = "Permiso guardado correctamente.";
            } else {
                $this->jsonData["mensaje"] = "Error al actualizar la base de datos.";
            }
        }
        
        private function eliminarRol($rol){
            $rol_seguro = addslashes($rol);
                        
            if($rol_seguro === 'root' || $rol_seguro === 'Admin') {
                $this->jsonData["mensaje"] = "Acción denegada: Los roles del sistema están protegidos.";
                return;
            }
                        
            $sql = "DELETE FROM Permisos_Roles WHERE rol_nombre = '$rol_seguro'";
                        
            if($this->conn->query($sql)){
                $det = "Se eliminó permanentemente el rol del sistema: '$rol_seguro' y todos sus permisos asociados.";
                Funciones::guardarBitacora($this->conn, 'Permisos del Sistema', 'ELIMINAR_ROL', $det);

                $this->jsonData["Bandera"] = 1;
                $this->jsonData["mensaje"] = "El rol '$rol_seguro' ha sido eliminado correctamente.";
            } else {
                $this->jsonData["mensaje"] = "Error al intentar eliminar el rol de la base de datos.";
            }
        }
    }

    $app = new PermisosAdmin($array_principal);
    $app->main();
?>