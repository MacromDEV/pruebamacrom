<?php
session_name("loginUsuario");
session_start();
require_once "../../../../Clases/dbconectar.php";
require_once "../../../../Clases/ConexionMySQL.php";
require_once "../../../../Clases/Funciones.php";
date_default_timezone_set('America/Mexico_City');

class Pedidos {
    private $conn;
    private $jsonData = array("Bandera"=>0, "mensaje"=>"");
    private $formulario = array();
    private $archivos = array();
    private $fecha;
    private $estatus = array("Por Acreditar", "Acreditado", "En preparacion", "En transito", "En proceso de Entrega", "Entregado", "Cancelado");
    
    public function __construct($array) {
        $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
        $this->fecha = date("Y-m-d H:i:s");
    }

    public function __destruct() {
        unset($this->conn);
    }
    
    public function main(){
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) {
            $this->formulario = $json['pedidos'] ?? $json['pedido'] ?? $json;
        } else {
            $this->formulario = array_map("htmlspecialchars", $_POST);
        }
        $this->archivos = $_FILES ?? [];
         
        $opc = $this->formulario['opc'] ?? '';
        $rol_usuario = $_SESSION["Puesto"] ?? $_SESSION["perfil"] ?? $_SESSION["rol"] ?? $_SESSION["tipo_usuario"] ?? 'Admin';

        switch ($opc) {
            case 'get':
                $this->autoCancelarPedidos();
                $find = $this->formulario['find'] ?? '';
                $historico = $this->formulario['Historico'] ?? false;
                $x = $this->formulario['x'] ?? 0;
                $y = $this->formulario['y'] ?? 10;

                $this->jsonData["Bandera"] = 1;
                $this->jsonData["Rol_Usuario"] = $rol_usuario;
                $this->jsonData["No_pedidos"] = $this->getNoPedidos($find, $historico);
                $this->jsonData["Pedidos"] = $this->getPedidos($x, $y, $find, $historico);
                break;
            
            case 'getOne':
                $id = $this->formulario['id'] ?? 0;
                $this->jsonData["Rol_Usuario"] = $rol_usuario;
                $this->jsonData["Pedido"] = $this->getOnePedido($id);
                $comp = $this->jsonData["Pedido"]["comprobante"] ?? '';
                $this->jsonData["Pedido"]["isFileComprobante"] = (strlen($comp) > 0) ? file_exists("../../../../../../Public/Comprobantes/{$comp}") : false;
                
                $this->jsonData["Detalles"] = $this->getPedidoDetalles($id);
                
                if(isset($this->jsonData["Pedido"]["FormaPago"]) && $this->jsonData["Pedido"]["FormaPago"] == "Tarjeta"){
                    $this->jsonData["Tarjeta"] = $this->getTarjeta($id);   
                }
                $this->jsonData["Bandera"] = 1;
                break;

            case 'save':
                $idPedido = (int)($this->formulario["_idPedidos"] ?? 0);
                $acreditado = (int)($this->formulario["Acreditado"] ?? 0);
                $guia = addslashes($this->formulario["GuiaEnvio"] ?? '');
                
                $costoEnvioAcordado = (float)($this->formulario["CostoEnvioAcordado"] ?? 0.00);
                $estatusPagoEnvio = (int)($this->formulario["EstatusPagoEnvio"] ?? 0);
                
                $pedidoViejo = $this->getOnePedido($idPedido);
                $estatusViejoTxt = $this->estatus[(int)($pedidoViejo['Acreditado'] ?? 0)] ?? 'Desconocido';
                $estatusNuevoTxt = $this->estatus[$acreditado] ?? 'Desconocido';

                if($this->setPedidosDetalles($acreditado, $guia, $costoEnvioAcordado, $estatusPagoEnvio, $idPedido)){
                    
                    $datosReales = $this->getDatosPedido($idPedido);
                    $noPedidoStr = $datosReales['noPedido'];
                    $clienteStr = $datosReales['cliente'];

                    $detallesAudit = "Actualizó el Pedido #$noPedidoStr ($clienteStr). ";
                    if($estatusViejoTxt != $estatusNuevoTxt){
                        $detallesAudit .= "Cambió estatus de '$estatusViejoTxt' a '$estatusNuevoTxt'. ";
                    }
                    if($guia != '' && $guia != ($pedidoViejo['GuiaEnvio'] ?? '')){
                        $detallesAudit .= "Asignó/Actualizó guía de envío: $guia. ";
                    }
                    if($costoEnvioAcordado != (float)($pedidoViejo['CostoEnvioAcordado'] ?? 0)){
                        $detallesAudit .= "Actualizó costo de envío acordado a $$costoEnvioAcordado. ";
                    }
                    if($estatusPagoEnvio != (int)($pedidoViejo['EstatusPagoEnvio'] ?? 0)){
                        $txtPago = $estatusPagoEnvio == 1 ? 'PAGADO' : 'SIN PAGAR';
                        $detallesAudit .= "Cambió estatus del envío a $txtPago. ";
                    }

                    // BITÁCORA
                    Funciones::guardarBitacora($this->conn, 'Pedidos', 'ACTUALIZAR_PEDIDO', trim($detallesAudit));

                    if(count($this->archivos) != 0){
                        $this->setPedidoDetallesfiles($this->uploadfiles($this->archivos), $idPedido);
                        Funciones::guardarBitacora($this->conn, 'Pedidos', 'SUBIR_FACTURA', "Subió archivos de facturación (XML/PDF) para el Pedido #$noPedidoStr ($clienteStr)");
                    }
                    if($acreditado == 5){
                        $arrayTemp = $this->getdetalsComprobanteMipedido($idPedido);
                        $ruta = "../../../../../../Public/Comprobantes/" . ($arrayTemp["comprobante"] ?? '');
                        if(file_exists($ruta) && $this->removeFile($ruta)){
                            $this->setdetalsComprobanteMipedido($idPedido);
                        }
                    }
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Los datos se han guardado satisfactoriamente";
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al guardar los datos";
                }
                break;

            case 'deletefile':
                $archivo = $this->formulario["file"] ?? '';
                $idPedido = (int)($this->formulario["_idPedido"] ?? 0);
                $tipo = addslashes($this->formulario["tipo"] ?? '');
                
                $ruta = "../../../../../../Public/Facturas/" . $archivo;
                if(file_exists($ruta) && $this->removeFile($ruta)){
                    $this->setDeletefilePedido($idPedido, $tipo);
                    
                    $datosReales = $this->getDatosPedido($idPedido);
                    $noPedidoStr = $datosReales['noPedido'];
                    $clienteStr = $datosReales['cliente'];

                    // BITÁCORA
                    Funciones::guardarBitacora($this->conn, 'Pedidos', 'ELIMINAR_FACTURA', "Eliminó el archivo de facturación ($tipo) del Pedido #$noPedidoStr ($clienteStr)");
                    
                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Archivo Eliminado";
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "El archivo no existe en el servidor";
                }
                break;

            case 'deleteArtic':
                $idDetalle = (int)($this->formulario["idDetalle"] ?? 0);
                $detalle = $this->getOneDetallePedido($idDetalle);
                
                if($detalle && $this->setOneDetallePedido($idDetalle)){
                    $idPedido = $detalle["_idPedidos"];
                    $this->setImportePedidosDetallesxArticulo($idPedido, $detalle["Importe"]);
                    
                    $datosReales = $this->getDatosPedido($idPedido);
                    $noPedidoStr = $datosReales['noPedido'];
                    $clienteStr = $datosReales['cliente'];
                    $importeFormat = number_format($detalle["Importe"], 2);

                    // BITÁCORA
                    Funciones::guardarBitacora($this->conn, 'Pedidos', 'CANCELAR_ARTICULO', "Canceló un artículo (Detalle ID: $idDetalle) del Pedido #$noPedidoStr ($clienteStr). Importe descontado: $$importeFormat");

                    $this->jsonData["Bandera"] = 1;
                    $this->jsonData["mensaje"] = "Artículo cancelado exitosamente";
                } else {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Error al cancelar el artículo";
                }
                break;

            case 'deleteOrder':
                if($rol_usuario != 'root' && $rol_usuario != 'Admin'){
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "Permisos insuficientes para esta acción.";
                    break;
                }

                $idPedido = (int)($this->formulario["_idPedidos"] ?? 0);
                $pedidoInfo = $this->getOnePedido($idPedido);
                
                if (!$pedidoInfo) {
                    $this->jsonData["Bandera"] = 0;
                    $this->jsonData["mensaje"] = "El pedido no existe o ya fue eliminado.";
                    break;
                }

                $noPedidoStr = $pedidoInfo['noPedido'];
                $clienteStr = trim($pedidoInfo['nombres'] . ' ' . $pedidoInfo['Apellidos']);

                if (!empty($pedidoInfo['comprobante'])) {
                    $rutaComp = "../../../../../../Public/Comprobantes/" . $pedidoInfo['comprobante'];
                    if (file_exists($rutaComp)) @unlink($rutaComp);
                }
                if (!empty($pedidoInfo['archivoxml'])) {
                    $rutaXml = "../../../../../../Public/Facturas/" . $pedidoInfo['archivoxml'];
                    if (file_exists($rutaXml)) @unlink($rutaXml);
                }
                if (!empty($pedidoInfo['archivopdf'])) {
                    $rutaPdf = "../../../../../../Public/Facturas/" . $pedidoInfo['archivopdf'];
                    if (file_exists($rutaPdf)) @unlink($rutaPdf);
                }

                $this->conn->query("DELETE FROM DetallesPedidos WHERE _idPedidos = $idPedido");
                $this->conn->query("DELETE FROM cupones_usados WHERE id_pedido = $idPedido");
                $this->conn->query("DELETE FROM Pedidos WHERE _idPedidos = $idPedido");

                // BITÁCORA
                Funciones::guardarBitacora($this->conn, 'Pedidos', 'ELIMINAR_PEDIDO', "ELIMINÓ PERMANENTEMENTE el Pedido #$noPedidoStr ($clienteStr) y sus detalles asociados.");

                $this->jsonData["Bandera"] = 1;
                $this->jsonData["mensaje"] = "Pedido eliminado permanentemente.";
                break;
        }
        
        header('Content-Type: application/json');
        print json_encode($this->jsonData);
    }

    private function getDatosPedido($idPedido) {
        $sql = "SELECT P.noPedido, CONCAT(C.nombres, ' ', C.Apellidos) as nombreCompleto 
                FROM Pedidos P 
                INNER JOIN clientes C ON P._idCliente = C._id 
                WHERE P._idPedidos = $idPedido LIMIT 1";
        $res = $this->conn->query($sql);
        if ($res && $row = $this->conn->fetch($res)) {
            $nombre = trim($row['nombreCompleto']);
            return [
                'noPedido' => $row['noPedido'] ? stripslashes($row['noPedido']) : $idPedido,
                'cliente' => html_entity_decode(stripslashes($nombre), ENT_QUOTES, 'UTF-8')
            ];
        }
        return ['noPedido' => $idPedido, 'cliente' => 'Cliente Desconocido'];
    }

    private function getNoPedidos($find, $historico){
        $clausula = ($historico == true || $historico == "true" || $historico == 1) ? "" : "not";
        $sql = "SELECT P._idPedidos FROM Pedidos as P inner join clientes as C
                on (P._idCliente = C._id) where (C.nombres like '%$find%' or C.Apellidos like '%$find%' or P.noPedido like '%$find%')
                and Acreditado $clausula in ('5','6')";
        $this->conn->query($sql);
        return $this->conn->count_rows();
    }

    private function getPedidos($x=0, $y=10, $find, $historico){
        $clausula = ($historico == true || $historico == "true" || $historico == 1) ? "" : "not";
        $array = array();
        $sql = "SELECT P._idPedidos, P.noPedido, P.Fecha, (P.Importe + P.cenvio) as Importe, P.Acreditado, P.FormaPago, 
                C.nombres, C.Apellidos, P.Facturacion FROM Pedidos as P 
                inner join clientes as C on (P._idCliente = C._id) WHERE (C.nombres like '%$find%' or C.Apellidos like '%$find%' or P.noPedido like '%$find%' )
                and Acreditado $clausula in ('5', '6') order by P._idPedidos Desc LIMIT $x,$y";
        $id = $this->conn->query($sql);
        while($row = $this->conn->fetch($id)){
            $row["estatus"] = $this->estatus[$row["Acreditado"]] ?? 'Desconocido';
            array_push($array, $row);
        }
        return $array;
    }

    private function getOnePedido($id){
        $sql = "SELECT P._idPedidos, P.Fecha, P.cenvio, P.Servicio, P.Importe, P.Acreditado, if(P.Acreditado=1, 'Acreditado','Por Acreditar') as Acreditadotxt,
                P.Enviado, P.GuiaEnvio, P.FormaPago, P.paqueteria, P.Alto, P.Ancho, P.Peso, P.Largo, P.FechaEstimadaEnvio, 
                P.CostoEnvioAcordado, P.EstatusPagoEnvio, 
                C.nombres, C.Apellidos, C.correo, P.Facturacion, P.archivoxml, P.archivopdf, P.noPedido, P.comprobante, 
                CD.Domicilio, CD.Codigo_postal, CD.Telefono, CD.Colonia, CD.Ciudad, CD.Estado, CD.numExt, CD.numInt, CD.Referencia,
                CF.UsoCFDI, CF.Descripción as Descripcion, F.Rfc, F.Razonsocial, F.Domicilio as FDomicilio, P.descuento 
                from Pedidos as P 
                inner join clientes as C on (P._idCliente = C._id)
                inner join Cdirecciones as CD on (P._id_cdirecciones = CD._id)
                left join Facturacion as F on (P._id_facturacion = F._id)
                left join usocfdi as CF on (F.cfdi = CF._id)
                where P._idPedidos = $id";
        return $this->conn->fetch($this->conn->query($sql));
    }

    private function getTarjeta($idPedido){
        $sql = "Select cc_type, cc_number, auth from LogTerminal where _idPedidos = $idPedido";
        return $this->conn->fetch($this->conn->query($sql));
    }

    private function getPedidoDetalles($id){
        $array = array();
        $sql = "SELECT DP._id, DP.Importe, DP.cantidad , P._id as parte, P.Clave, P.Producto
                FROM DetallesPedidos as DP 
                inner join Producto as P on (DP._idProducto = P._id) 
                where DP._idPedidos = $id and DP.Estatus = 1";
        $res = $this->conn->query($sql);
        while($row = $this->conn->fetch($res)){
            $row["imagen"] = file_exists("../../../../../../images/refacciones/{$row["parte"]}.png");
            array_push($array, $row);
        }
        return $array;
    }

    private function getdetalsComprobanteMipedido($id){
        $sql = "SELECT comprobante from Pedidos where _idPedidos=$id";
        return $this->conn->fetch($this->conn->query($sql));
    }

    private function setdetalsComprobanteMipedido($id){
        $sql ="UPDATE Pedidos SET comprobante='' where _idPedidos = $id";
        return $this->conn->query($sql);
    }

    private function removeFile($file){
        return unlink($file);
    }

    private function uploadfiles($files){
        $array = array();
        foreach($files as $key => $value){
            $subdir = "../../../../../../"; 
            $dir = "Public/Facturas/";
            $archivo = basename($value["name"]);
            if(!is_dir($subdir.$dir)){ mkdir($subdir.$dir, 0755, true); }
            if($archivo && move_uploaded_file($value["tmp_name"], $subdir.$dir.$archivo)){
                array_push($array, $archivo);
            }
        }
        return $array;
    }

    private function setDeletefilePedido($id, $tipo){
       $sql = "UPDATE Pedidos SET $tipo = '' where _idPedidos = $id ";
       return $this->conn->query($sql);
    }

    private function setPedidosDetalles($acreditado, $guiaenvio, $costoEnvioAcordado, $estatusPagoEnvio, $_id){
        $sql = "UPDATE Pedidos SET 
                Acreditado='$acreditado', 
                GuiaEnvio='$guiaenvio',
                CostoEnvioAcordado='$costoEnvioAcordado',
                EstatusPagoEnvio='$estatusPagoEnvio'
                WHERE _idPedidos = $_id";
        return $this->conn->query($sql);
    }

    private function setImportePedidosDetallesxArticulo($idPedido, $importe){
        $sql = "UPDATE Pedidos SET Importe = (Importe - $importe) where _idPedidos = $idPedido";
        return $this->conn->query($sql);
    }

    private function getOneDetallePedido($idDetalle){
        $sql = "select DP.*, P._idCliente, P._idPedidos from DetallesPedidos as DP 
                inner join Pedidos as P on (DP._idPedidos = P._idPedidos)
                where DP._id = $idDetalle";
        return $this->conn->fetch($this->conn->query($sql));
    }

    private function setOneDetallePedido($idDetalle){
        $sql = "UPDATE DetallesPedidos SET Estatus = 0, FechaEditar='". date("Y-m-d H:i:s")."' where _id=$idDetalle";
        return $this->conn->query($sql);
    }

    private function setPedidoDetallesfiles($array, $_id){
        $campos = "";
        foreach($array as $key => $value){
            if($key == 0) $campos = "archivoxml='$value',";
            else if($key == 1) $campos .= "archivopdf='$value' ";
        }
        if(empty($campos)) return false;
        
        $sql = "UPDATE Pedidos SET $campos where _idPedidos = $_id";
        return $this->conn->query($sql);
    }

    private function autoCancelarPedidos() {
        $fecha_limite = date("Y-m-d H:i:s", strtotime("-5 days"));
        $sql = "UPDATE Pedidos SET Acreditado = '6' WHERE Acreditado = '0' AND Fecha <= '$fecha_limite'";  
        $this->conn->query($sql);
    }
}

$app = new Pedidos($array_principal);
$app->main();
?>