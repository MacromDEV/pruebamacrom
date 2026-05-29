<?php
session_name("loginUsuario");
session_start();

require_once "../../../Clases/dbconectar.php";
require_once "../../../Clases/ConexionMySQL.php";
date_default_timezone_set('America/Mexico_City');

class Dashboard {
    private $conn;
    private $jsonData = array("Bandera" => 0, "mensaje" => "", "Data" => array());
    private $fechaHoy;

    public function __construct($array) {
        $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
        $this->fechaHoy = date("Y-m-d");
    }

    public function __destruct() {
        unset($this->conn);
    }

    public function main() {
        $json = json_decode(file_get_contents('php://input'), true);
        
        $opc = $json['opc'] ?? $json['home']['opc'] ?? $_POST['opc'] ?? '';

        switch ($opc) {
            case 'loginM':
                $sqlMantenimiento = "SELECT modo_mantenimiento FROM Configuracion_Sistema WHERE id = 1 LIMIT 1";
                $resMantenimiento = $this->conn->query($sqlMantenimiento);
                $estado = 0;
                
                if($resMantenimiento) {
                    $row = $this->conn->fetch($resMantenimiento);
                    $estado = intval($row['modo_mantenimiento']);
                }
                
                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Usuarios"] = $estado;
                break;
            case 'ping':
                $mi_username = $_SESSION["nombre_usuario"] ?? $_SESSION["usr"] ?? '';
                $rol = strtolower($_SESSION["rol"] ?? '');
                $ahora = date("Y-m-d H:i:s");
                $sqlMantenimiento = "SELECT modo_mantenimiento FROM Configuracion_Sistema WHERE id = 1 LIMIT 1";
                $resMantenimiento = $this->conn->query($sqlMantenimiento);
                $modo = 0;
                
                if($resMantenimiento) {
                    $rowM = $this->conn->fetch($resMantenimiento);
                    $modo = intval($rowM['modo_mantenimiento']);
                }

                if ($modo === 1 && $rol !== 'root') {
                    session_destroy();
                    $this->jsonData["Bandera"] = -1;
                    $this->jsonData["mensaje"] = "Sistema en mantenimiento";
                    break;
                }

                if($mi_username != '') {
                    $sqlPing = "UPDATE Usuarios SET ultimoAcceso = '$ahora', OnlineNow = 1 WHERE Username = '$mi_username'";
                    $this->conn->query($sqlPing);
                }
                
                $this->jsonData["Bandera"] = 1;
            break;

            case 'getKPIs':
                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Data"] = array(
                    "ventasHoy" => $this->getVentasHoy(),
                    "pedidosPendientes" => $this->getPedidosPendientes(),
                    "ticketPromedio" => $this->getTicketPromedio(),
                    "clientesNuevos" => $this->getClientesNuevosSemana(),
                    "contactosNuevos" => $this->getContactosNuevos()
                );
                break;

            case 'getGraficaMensual':
                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Data"] = $this->getVentasMensuales();
                break;
                
            case 'getUltimosPedidos':
                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Data"] = $this->getUltimosPedidos();
                break;

            case 'get':
                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Publicados"] = $this->getInventarioEstatus(1,1);
                $this->jsonData["NoPublicados"] = $this->getInventarioEstatus(0,0);
                break;

            case 'getPermisos':
                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Data"] = $this->getPermisosUsuario();
                break;
                
            case 'get_online_users':
                $minutos_tolerancia = 3; 
                $mi_username = $_SESSION["nombre_usuario"] ?? $_SESSION["usr"] ?? '';
                $limite_tiempo = date("Y-m-d H:i:s", strtotime("-$minutos_tolerancia minutes"));
    
                $sql = "SELECT Username, CONCAT_WS(' ', Nombre, ApPaterno, ApMaterno) as nombreCompleto FROM Usuarios WHERE ultimoAcceso >= '$limite_tiempo' AND Estatus = 1 AND OnlineNow = 1 AND Username != '$mi_username'";

                $res = $this->conn->query($sql);
                $online = array();

                if ($res) {
                    while($row = $this->conn->fetch($res)){
                        array_push($online, $row);
                    }
                }

                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Data"] = $online;
                break;
        }

        header('Content-Type: application/json');
        print json_encode($this->jsonData);
    }


    private function getVentasHoy() {
        $sql = "SELECT SUM(Importe + cenvio) as total FROM Pedidos WHERE DATE(Fecha) = '{$this->fechaHoy}' AND Acreditado IN (1, 2, 3, 4, 5)";
        $res = $this->conn->fetch($this->conn->query($sql));
        return (float)($res['total'] ?? 0);
    }

    private function getPedidosPendientes() {
        $sql = "SELECT COUNT(_idPedidos) as cantidad FROM Pedidos WHERE Acreditado IN (0, 2, 3, 4)";
        $res = $this->conn->fetch($this->conn->query($sql));
        return (int)($res['cantidad'] ?? 0);
    }

    private function getTicketPromedio() {
        $sql = "SELECT AVG(Importe + cenvio) as promedio FROM Pedidos WHERE Acreditado IN (1, 2, 3, 4, 5) AND Fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $res = $this->conn->query($sql);
        if($res){
            $data = $this->conn->fetch($res);
            return (float)($data['promedio'] ?? 0);
        }
        return 0.00;
    }

    private function getClientesNuevosSemana() {
        $fechaLunes = (date("D") == "Mon") ? date("Y-m-d") : date("Y-m-d", strtotime("last Monday"));
        $sql = "SELECT COUNT(_id) as cantidad FROM clientes WHERE DATE(FechaCreacion) >= '$fechaLunes'";
        $res = $this->conn->fetch($this->conn->query($sql));
        return (int)($res['cantidad'] ?? 0);
    }

    private function getContactosNuevos() {
        $sql = "SELECT COUNT(_id) as cantidad FROM Contacto WHERE leido = 0";
        $res = $this->conn->fetch($this->conn->query($sql));
        return (int)($res['cantidad'] ?? 0);
    }

    private function getVentasMensuales() {
        $sql = "SELECT DATE_FORMAT(Fecha, '%d/%m') as dia_mes, COUNT(_idPedidos) as cantidad, SUM(Importe + cenvio) as total 
        FROM Pedidos 
        WHERE Acreditado IN (1, 2, 3, 4, 5) AND Fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
        GROUP BY DATE(Fecha) 
        ORDER BY DATE(Fecha) ASC";
        
        $res = $this->conn->query($sql);
        $ventasPorDia = array();
        
        if($res){
            while ($row = $this->conn->fetch($res)) {
                $ventasPorDia[] = array(
                    "dia" => $row['dia_mes'],
                    "cantidad" => (int)$row['cantidad'],
                    "total" => (float)$row['total']
                );
            }
        }
        return $ventasPorDia;
    }


    private function getUltimosPedidos() {
        $array = array();
        $sql = "SELECT P.noPedido, P._idPedidos, P.Fecha, (P.Importe + P.cenvio) as Total, P.Acreditado, 
                CONCAT(C.nombres, ' ', C.Apellidos) as Cliente 
                FROM Pedidos P 
                INNER JOIN clientes C ON P._idCliente = C._id 
                ORDER BY P._idPedidos DESC 
                LIMIT 5";
        $res = $this->conn->query($sql);
        
        $estatusTxt = array("0"=>"Por Acreditar", "1"=>"Acreditado", "2"=>"En preparacion", "3"=>"En transito", "4"=>"En proceso", "5"=>"Entregado", "6"=>"Cancelado");
        
        while ($row = $this->conn->fetch($res)) {
            $row['estatusTxt'] = $estatusTxt[(string)$row['Acreditado']] ?? 'Desconocido';
            array_push($array, $row);
        }
        return $array;
    }

    private function getInventarioEstatus($estatus,$publicado) {
        $sql = "SELECT COUNT(_id) as cantidad FROM Producto WHERE Estatus = $estatus AND Publicar = $publicado";
        $res = $this->conn->fetch($this->conn->query($sql));
        return (int)($res['cantidad'] ?? 0);
    }

    private function getPermisosUsuario() {
        $rol = addslashes($_SESSION["rol"] ?? '');
        $sql = "SELECT m.opc FROM Modulos_Admin m 
                INNER JOIN Permisos_Roles p ON m.id_modulo = p.id_modulo 
                WHERE p.rol_nombre = '$rol'";
        $res = $this->conn->query($sql);
        
        $permisos = array();
        if ($res) {
            while ($row = $this->conn->fetch($res)) {
                $mod_clean = str_replace("?mod=", "", $row['opc']);
                $permisos[$mod_clean] = true;
            }
        }
        return $permisos;
    }
}

$app = new Dashboard($array_principal);
$app->main();
?>