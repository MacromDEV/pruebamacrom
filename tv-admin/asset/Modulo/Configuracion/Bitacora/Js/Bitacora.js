var urlBitacora = "./Modulo/Configuracion/Bitacora/Ajax/Bitacora.php";

tsuruVolks.controller('BitacoraCtrl', ["$scope", "$http", "$sce", BitacoraCtrl]);

function BitacoraCtrl($scope, $http, $sce) {
    var obj = $scope;
    obj.logs = [];
    obj.busqueda = '';
    
    // Diccionarios para Catálogos Básicos
    obj.mapMarcas = {};
    obj.mapCategorias = {};
    obj.mapModelos = {};
    obj.mapAnios = {};
    obj.mapProveedores = {};
    obj.mapEnvios = {}; 
    obj.mapRefacciones = {};

    obj.cargarCatalogos = function() {
        const urlRef = "./Modulo/Control/Refacciones/Ajax/Refacciones.php";
        const urlEnvios = "./Modulo/Configuracion/Cenvios/Ajax/Cenvios.php";
        
        const configRef = {
            headers: {'Content-Type': undefined},
            transformRequest: data => { 
                var fd = new FormData(); 
                for (var m in data.modelo) fd.append(m, data.modelo[m]); 
                return fd; 
            }
        };

        $http.post(urlRef, { modelo: { opc: "buscar", tipo: "Marcas" } }, configRef).then(res => { if(res.data.Bandera == 1) res.data.data.forEach(m => obj.mapMarcas[m._id] = m.Marca); });
        $http.post(urlRef, { modelo: { opc: "buscar", tipo: "Categorias" } }, configRef).then(res => { if(res.data.Bandera == 1) res.data.data.forEach(c => obj.mapCategorias[c._id] = c.Categoria); });
        $http.post(urlRef, { modelo: { opc: "buscar", tipo: "Vehiculos" } }, configRef).then(res => { if(res.data.Bandera == 1) res.data.data.forEach(v => obj.mapModelos[v._id] = v.Modelo); });
        $http.post(urlRef, { modelo: { opc: "buscar", tipo: "Modelos" } }, configRef).then(res => { if(res.data.Bandera == 1) res.data.data.forEach(a => obj.mapAnios[a._id] = a.Anio); });
        $http.post(urlRef, { modelo: { opc: "buscar", tipo: "proveedores" } }, configRef).then(res => { if(res.data.Bandera == 1) res.data.data.forEach(p => obj.mapProveedores[p._id] = p.Proveedor); });
        $http.post(urlRef, { modelo: { opc: "buscar", tipo: "Refacciones", buscar: "", skip: 0, limit: 1000, historico: "false", publicados: "true", orden: "P._id", ordentype: "DESC" } }, configRef).then(res => { if(res.data.Bandera == 1) res.data.data.refacciones.forEach(r => obj.mapRefacciones[r._id] = r.Clave + " - " + r.Producto); });

        $http.post(urlEnvios, { opc: "getEnvios" }).then(res => { 
            if(res.data.Bandera == 1) {
                res.data.Envios.forEach(e => {
                    let destino = e.Municipio ? `${e.Municipio}, ${e.Estado}` : e.Estado;
                    obj.mapEnvios[e.id] = destino;
                });
            }
        });
    };

    obj.formatearDetalles = function(textoFila, modulo, accionLog) {
        if (!textoFila) return "";
        
        let textoLimpio = textoFila.replace(/&quot;/g, '"');

        const etiquetasJSON = {
            "_idMarca": "Marca", "_idCategoria": "Categoría", "Modelo": "Vehículo",
            "Anios": "Año/Versión", "id_proveedor": "Proveedor", "Precio1": "Precio Público",
            "stock": "Existencia", "precio_manual": "Precio Manual"
        };

        try {
            let matchJSON = textoLimpio.match(/({.*})/);
            if (matchJSON) {
                let jsonStr = matchJSON[1];
                let restoTexto = textoLimpio.replace(jsonStr, '').trim(); 
                let limpio = jsonStr.replace(/"|{|}/g, ""); 
                
                let formateado = limpio.split(',').map(item => {
                    let partes = item.split(':');
                    if(partes.length === 2) {
                        let llave = partes[0].trim();
                        let valor = partes[1].trim();
                        
                        if(llave === "_idMarca") valor = obj.mapMarcas[valor] || valor;
                        else if(llave === "_idCategoria") valor = obj.mapCategorias[valor] || valor;
                        else if(llave === "Modelo") valor = obj.mapModelos[valor] || valor;
                        else if(llave === "Anios") valor = obj.mapAnios[valor] || valor;
                        else if(llave === "id_proveedor") valor = obj.mapProveedores[valor] || valor;

                        let nombreHumano = etiquetasJSON[llave] || llave;
                        
                        return `<span class="badge badge-light border mb-1 mr-1 text-sm text-left font-weight-normal">
                                    <b class="text-danger">${nombreHumano}:</b> <span class="text-dark">${valor}</span>
                                </span>`;
                    }
                    return item;
                }).join(' ');

                return $sce.trustAsHtml((restoTexto ? `<b>${restoTexto}</b><br>` : '') + formateado);
            }
        } catch(e) {}

        return $sce.trustAsHtml(textoLimpio);
    };

    obj.cargarBitacora = function () {
        $http.post(urlBitacora, { opc: "get_logs" }).then(res => {
            if (res.data.Bandera == 1) obj.logs = res.data.Data;
        });
    }

    // Inicialización
    obj.cargarCatalogos();
    setTimeout(() => { obj.cargarBitacora(); }, 500);
}