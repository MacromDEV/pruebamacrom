<?php
    class Funciones{
        static function siAcceso($string=""){
            $arrayTemp = explode(",", $string);
            return in_array($_SESSION["rol"], $arrayTemp, true);
        }

        static function siAcceso2($array){
            return in_array($_SESSION["rol"], $array, true);
        }

        public static function guardarBitacora($conn, $modulo, $accion, $detalles) {

            $id_usuario = isset($_SESSION['_id']) ? $_SESSION['_id'] : (isset($_SESSION['iduser']) ? $_SESSION['iduser'] : 0);
            $username = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : (isset($_SESSION['usr']) ? $_SESSION['usr'] : 'Sistema');

            $ip = $_SERVER['REMOTE_ADDR'];
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }

            $modulo_seguro = addslashes($modulo);
            $accion_seguro = addslashes($accion);
            $detalles_seguro = addslashes($detalles);
            $fecha = date("Y-m-d H:i:s");

            $sql = "INSERT INTO Bitacora_Auditoria (id_usuario, username, modulo, accion, detalles, fecha, ip_usuario) 
                    VALUES ('$id_usuario', '$username', '$modulo_seguro', '$accion_seguro', '$detalles_seguro', '$fecha', '$ip')";

            $conn->query($sql);
        }
    }
?>