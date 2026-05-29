<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/core/bootstrap.php";
require_once "../../../tv-admin/asset/Clases/dbconectar.php";
require_once "../../../tv-admin/asset/Clases/ConexionMySQL.php";
require_once '../../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

class Registro{
    private $conn;
    private $formulario = array();
    private $jsonData = array("mensaje"=>"", "Bandera" => 0);
    private $datausercupones;
    
    public function __construct($array) {
        $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
    }

    public function __destruct() {
        unset($this->conn);
    } 
    
    public function main(){
        $this->formulario = json_decode(file_get_contents('php://input'));
        $this->jsonData["cupongeneral"] = $this->getCuponGeneral();
        if($this->FindCliente()){
            $id = $this->setCliente();
            if($id){
                    if($this->setSession($this->setCSeguridad($id), $id)){
                        $this->jsonData["Bandera"] = 1;
                        $this->jsonData["mensaje"] = "Bienvenido ". $this->formulario->Registro->Nombre;
                        $this->jsonData["Session"] = $_SESSION;
                        //Envio de registro satisfactorio al Correo del usuario.
                        try {

                            $destinatario = $this->formulario->Registro->username;
                            $nombre = $this->formulario->Registro->Nombre;

                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->SMTPDebug = 0;
                            $mail->Host = 'smtp.hostinger.com';
                            $mail->Port = 587;
                            $mail->SMTPAuth = true;
                            $mail->SMTPSecure = 'tls';
                            $mail->Username = 'soporte@macromautopartes.com';
                            $mail->Password = SMTP_PASS;

                            $mail->setFrom('soporte@macromautopartes.com', 'Soporte Macrom');
                            $mail->addAddress($destinatario, $nombre);

                            $mail->Subject = 'Registro en Macromautopartes';
                            $mail->isHTML(true);
                            $mail->CharSet = 'UTF-8';
                            $mail->AltBody = "Tu cuenta ha sido creada exitosamente en Macromautopartes. Visita https://macromautopartes.com";

                            $mail->Body = '
                                <!DOCTYPE html>
                                <html lang="es">
                                    <head>
                                        <meta charset="UTF-8">
                                        <title>Registro exitoso</title>
                                    </head>
                                    <body style="margin:0;padding:0;background-color:#f4f4f4;">

                                        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
                                            <tr>
                                                <td align="center">
                                                    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;font-family:Arial, sans-serif;">

                                                        <tr>
                                                            <td align="center">
                                                                <img src="https://macromautopartes.com/images/icons/CRcabecera.png" width="600" style="display:block;">
                                                            </td>
                                                        </tr>

                                                        <tr>
                                                            <td align="center" style="padding:30px 20px;">
                                                                <h2 style="color:#de0007;margin:0;">¡Bienvenido a Macromautopartes!</h2>
                                                                <p style="color:#555;font-size:16px;margin-top:15px;">Tu cuenta ha sido creada exitosamente.</p>
                                                                <p style="color:#777;font-size:14px;">Ya puedes comenzar a comprar desde la comodidad de tu casa.</p>
                                                            </td>
                                                        </tr>

                                                        <tr>
                                                            <td align="center" style="padding-bottom:30px;">
                                                                <a href="https://macromautopartes.com" style="background:#de0007;color:#ffffff;text-decoration:none; padding:12px 25px;border-radius:4px;font-size:14px;display:inline-block;"> Ir a la tienda </a>
                                                            </td>
                                                        </tr>

                                                        <tr>
                                                            <td align="center" style="background:#fafafa;padding:15px;font-size:12px;color:#999;">Este correo fue enviado automáticamente por Macromautopartes.<br>Si no realizaste este registro, ignora este mensaje.</td>
                                                        </tr>

                                                    </table>

                                                </td>
                                            </tr>
                                        </table>

                                    </body>
                                </html>';

                            $mail->send();

                        } catch (Exception $e) {
                            // No rompemos el registro si falla el correo
                            // Puedes guardar el error en log si quieres:
                            // error_log("Error al enviar correo: " . $e->getMessage());
                        }
                    }else{
                        $this->jsonData["Bandera"] = 0;
                        $this->jsonData["mensaje"] = "Error: al iniciar session";
                    }
                    
            }else{
                $this->jsonData["Bandera"] = 0;
                $this->jsonData["mensaje"] = "Error: No se creo el usuario";
            }
        }else{
            
            $this->jsonData["Bandera"] = 0;
            $this->jsonData["mensaje"] = "El usuario que se quiere registrar ya existe";
        }
        print json_encode($this->jsonData);
    }
    
    private function FindCliente(){
        $sql = "Select * from clientes where correo = '". $this->formulario->Registro->username."'";
        
        $this->conn->query($sql);
        return $this->conn->count_rows()>0? false: true;
    }
    
    private function setCliente(){
        $fecha1 = date("Y-m-d",strtotime($this->formulario->Registro->FechaCreacion));
        $this->formulario->Registro->Aviso = $this->formulario->Registro->Aviso == "true"? 1:0;
        $sql = "INSERT INTO clientes(nombres, Apellidos, correo, Domicilio, Colonia, Codigo_postal, ciudad, estado, "
                . "telefono, FechaCreacion, FechaModificacion, ultimoacceso, inicioacceso, Estatus, avisoprivacidad) value "
                . "('{$this->formulario->Registro->Nombre}','{$this->formulario->Registro->Apellidos}','{$this->formulario->Registro->username}',"
                . "'{$this->formulario->Registro->Domicilio}','{$this->formulario->Registro->Colonia}','{$this->formulario->Registro->Codigopostal}',"
                . "'{$this->formulario->Registro->Ciudad}','{$this->formulario->Registro->Estado}','{$this->formulario->Registro->Telefono}',"
                . "'".date("Y-m-d", strtotime($this->formulario->Registro->FechaCreacion))."','".date("Y-m-d", strtotime($this->formulario->Registro->FechaModificacion))."',"
                . "'".date("Y-m-d", strtotime($this->formulario->Registro->ultimoaccesso))."','".date("Y-m-d", strtotime($this->formulario->Registro->inicioacceso))."',1,"
                . "'{$this->formulario->Registro->Aviso}')";
        return $this->conn->query($sql)? $this->conn->last_id():false;
    }
    
private function setCSeguridad ($id){

    $passwordPlano = $this->formulario->Registro->pass;

    // 🔐 Hash nuevo
    $passwordHash = password_hash($passwordPlano, PASSWORD_DEFAULT);

    $fechaActual = date("Y-m-d H:i:s");

    if(isset($this->jsonData["cupongeneral"])){
        $sql = "INSERT INTO Cseguridad(username, password, FechaCreacion, FechaModificacion, Estatus, _id_cliente, cuponacre, cupon_nombre, password_changed_at)
                VALUES('{$this->formulario->Registro->username}', '$passwordHash', '$fechaActual', '$fechaActual',
                1, '$id', 0, '{$this->jsonData["cupongeneral"]}', '$fechaActual')";
    }else{
        $sql = "INSERT INTO Cseguridad(username, password, FechaCreacion, FechaModificacion, Estatus, _id_cliente, cuponacre, cupon_nombre, password_changed_at)
            VALUES('{$this->formulario->Registro->username}', '$passwordHash', '$fechaActual', '$fechaActual',
            1, '$id', 0, NULL, '$fechaActual')";
    }

    return $this->conn->query($sql) ? true : false;
}

    private function getCuponGeneral(){
        $sql = "SELECT cupon_nombre FROM Cseguridad where username = 'webmaster@macromautopartes.com'";
        $datausercupones = $this->conn->fetch($this->conn->query($sql));
        
        return $datausercupones["cupon_nombre"];
    }

    private function setSession($flag = false, $id = null){
        if($flag){
            session_name("loginCliente");
            session_start();
            session_regenerate_id(true);
            $_SESSION["autentificacion"]=1;
            $_SESSION["ultimoAcceso"]= date("Y-n-j H:i:s");
            $_SESSION["password_changed_at"] = date("Y-m-d H:i:s");
            $_SESSION["nombrecorto"] = $this->formulario->Registro->Nombre;
            $_SESSION["nombre"] = $this->formulario->Registro->Nombre.' '.$this->formulario->Registro->Apellidos;
            $_SESSION["iduser"] = $id;
            $_SESSION["usr"] = $this->formulario->Registro->username;
            return true;
        }else{
            return false;
        }
    }
    
}

$app = new Registro($array_principal);
$app->main();