'use strict'

var url_getusercampras = "./modulo/Compras/Ajax/Compras.php";
const urlProfile = "./modulo/Profile/Ajax/Profile.php";
var urlCostumer = "./modulo/ProcesoCompra/Ajax/ProcesoCompra.php";
var url_session = "./modulo/home/Ajax/session.php";
var urlhome = "./modulo/home/Ajax/home.php";
const url_seicom = "https://volks.dyndns.info:444/service.asmx/consulta_art";
//const urlSkydropx = "https://api-demo.skydropx.com/v1/quotations"
const urlSkydropx = "https://api.skydropx.com/v1/quotations";
//const token = "Token token=SuOZQz5IrqceQbJmBqQfAo4PMQvNKMCh2PtXOKMfKM0t";
const token = "Token token=fInE1ArT8CJfaR2wkznA5hXSNCMSXs7vitsCFeM98Pct";

tsuruVolks.controller('ComprasCtrl', ["$scope", "$http", "$sce", ComprasCtrl])
    .directive('convertToString', function () {
        return {
            require: 'ngModel',
            link: function ($scope, element, attrs, ngModel) {
                ngModel.$parsers.push(function (value) {
                    return parseFloat(value);
                });
                ngModel.$formatters.push(function (value) {
                    return '' + value;
                });
            }
        };
    });

function ComprasCtrl($scope, $http, $sce) {
    /* GUARD – evitar doble inicialización */
    if (window.__COMPRAS_CTRL_INIT__) {
        console.warn("ComprasCtrl duplicado evitado");
        return;
    }
    window.__COMPRAS_CTRL_INIT__ = true;
    
    /* ALIAS / BASE */
    var obj = $scope;
    const SESSION = $_SESSION;

    /* VARIABLES DE ESTADO */
    obj.session = $_SESSION;
    obj.avisoEnvioMultiple = false;

    obj.Costumer = { Cenvio: { costo: 0 }, aviso: false, facturacion: 0, profile: {}, metodoPago: "", cart: $_SESSION.CarritoPrueba, dataFacturacion: {}, dataDomicilio: {}, descuento: 0, DiaEstimado: null, dataCP:{},DiaExtraEnvio:0};
    obj.cenvio = 0;
    obj.Numproducts = obj.session.CarritoPrueba ? Object.keys(obj.session.CarritoPrueba).length : 0;
    obj.factflag = false;
    obj.cotizador = [];
    obj.flag = false;
    obj.dataflag = false;
    obj.requiredEnvio = false;
    obj.sucursales = { Colima: 0, Manzanillo: 0, Tecoman: 0, VillaDeAlvarez: 0, Bodega: 0, VentasInternet: 0};
    obj.SeiData = {};

    $_SESSION.DiasenvioEXT = {};
    $_SESSION.SinStock = {};

    obj.dataCotizador = {
        zip_from: "28000",
        zip_to: "60174",
        parcel: {
            weight: 0, //peso
            height: 0, //altura
            width: 0, //ancho
            length: 0 //largo
        }
    }
    /* CONSTANTES */
    const hoy = new Date();
    const diaDeLaSemana = hoy.getDay()+1;
    const horaDelDia = hoy.getHours();

    const MAPA_SUCURSALES = {
        "VILLA DE ALVAREZ": "VillaDeAlvarez",
        "VENTAS INTERNET": "VentasInternet",
        "TECOMAN": "Tecoman",
        "TECOMÁN": "Tecoman",
        "COLIMA": "Colima",
        "MANZANILLO": "Manzanillo",
        "BODEGA": "Bodega"
    };
    const ORDEN_SUCURSALES = [
        { key: "VentasInternet", dia: 0 },
        { key: "VillaDeAlvarez", dia: 0 },
        { key: "Colima", dia: 0 },
        { key: "Tecoman", dia: 3 },
        { key: "Manzanillo", dia: 5 },
        { key: "Bodega", dia: 2 },
    ];
    const DIAS_CAMION = {
        Tecoman: 3,      // Miercoles
        Manzanillo: 1,  // Lunes
    };

    /* DOM / EVENTOS PUROS */

    /* UI: Control del Loader Global */
    obj.ui = {
        loader: {
            mostrar: function(texto = "Procesando...") { 
                $('#loading-text-compras').text(texto);
                $('#loading-screen-compras').fadeIn(200); 
            },
            ocultar: function() { 
                $('#loading-screen-compras').fadeOut(200); 
            }
        }
    };

    /* UTILIDADES */
    $scope.tieneMultiplesSucursales = function(clave) {
        if (!$scope.ProductosPorSucursal) return false;
        if (!$scope.ProductosPorSucursal[clave]) return false;
        return $scope.ProductosPorSucursal[clave].sucursales.length > 1;
    };

    function actualizarCarrito(ref) {
        ref.upd = 1;
        ref.updCLV = ref.Clave;
        ref.n = $_SESSION["CarritoPrueba"]["length"];
        obj.actualizarSession(ref, true);
    }

    function recalcularCupon() {

        if (!obj.Costumer.valor_cpn) return;

        //Si el subtotal ya es 0, eliminar cupón automáticamente
        if (obj.Costumer.Subtotal <= 0) {

            obj.Costumer.descuento = 0;
            obj.Costumer.valor_cpn = null;
            obj.Costumer.usercpn = null;
            obj.Costumer.id_cupon = null;

            const inpCupon = document.getElementById("inpCupon");
            const btnCupon = document.getElementById("btncupon");

            if (inpCupon) inpCupon.disabled = false;
            if (btnCupon) btnCupon.disabled = false;

            return;
        }

        const porcentaje = obj.Costumer.valor_cpn;
        const nuevoMonto = obj.Costumer.Subtotal * (porcentaje / 100);

        obj.Costumer.descuento = nuevoMonto;
    }

    obj.eachRefacciones = (array) => {
        if (!array) return; 

        array.forEach(e => {
            let nombreProd = e["_producto"] || e["Producto"] || "";
            
            e.NewUrlName = nombreProd.replaceAll(" ","-");
            e.NewUrlName = e.NewUrlName.replaceAll(",","");
            e.NewUrlName = e.NewUrlName.normalize('NFD').replace(/[\u0300-\u036f]/g,"");
            e.NewAltName = nombreProd.replaceAll(",","");
            
            // Forzamos números para que no haya inputs en blanco ni errores de Angular
            e.Cantidad = parseInt(e.Cantidad, 10) || 1;
            e.Existencias = parseInt(e.Existencias, 10) || 1;
        });
    };
    if(obj.session && obj.session.CarritoPrueba) {obj.eachRefacciones(obj.session.CarritoPrueba);}
    
    obj.getImagen = (id) => {
        //var url = "https://macromautopartes.com/images/refacciones/";
        var url = "images/refacciones/";
        return url + id + ".webp";
    }

    /* CARRITO / STOCK */
    async function prodCarrito() {

        /*==LIMPIEZA PREVENTIVA==*/
        $_SESSION.ProductosPorSucursal = {};
        $_SESSION.DiasenvioEXT = {};
        $_SESSION.ProdNOstock = 0;
        $_SESSION.ProdInsufStock = 0;

        /*===HELPERS LOCALES===*/
        const normalizarStock = (table) => {
            const stockPorSucursal = {
                Colima: 0,
                VentasInternet: 0,
                Tecoman: 0,
                VillaDeAlvarez: 0,
                Manzanillo: 0,
                Bodega: 0,
            };

            table.forEach(registro => {
                const nombreLimpio = registro.em_nombre.replace("(MACROM AUTOPARTES)", "").trim().toUpperCase();
                const sucursalKey = MAPA_SUCURSALES[nombreLimpio];

                if (sucursalKey) {
                    stockPorSucursal[sucursalKey] += parseInt(registro.existencia, 10) || 0;
                } else {
                    console.warn("Sucursal no mapeada:", nombreLimpio);
                }
            });

            return stockPorSucursal;
        };

        const combinarSucursales = (stockPorSucursal, cantidadSolicitada) => {
            let restante = cantidadSolicitada;
            const usadas = [];

            for (const suc of ORDEN_SUCURSALES) {
                const disponible = stockPorSucursal[suc.key] || 0;
                if (disponible <= 0) continue;

                const tomar = Math.min(disponible, restante);

                usadas.push({
                    sucursal: suc.key,
                    cantidad: tomar
                });

                restante -= tomar;

                if (restante === 0) break;
            }

            return restante > 0 ? null : { usadas };
        };

        const calcularDiasPorSucursal = (diaCamion) => {
            const hoy = diaDeLaSemana;
            const pasoHoy = hoy === diaCamion && horaDelDia <= 16;

            if (hoy < diaCamion) return diaCamion - hoy;
            if (pasoHoy) return 0;

            return 7 - hoy + diaCamion;
        };

        const calcularDiasEnvioReal = (usadas) => {
            let maxDias = 0;

            for (const s of usadas) {
                const diaCamion = DIAS_CAMION[s.sucursal];
                if (!diaCamion) continue;

                const dias = calcularDiasPorSucursal(diaCamion);
                maxDias = Math.max(maxDias, dias);
            }

            return maxDias;
        };

        /*===PROCESAMIENTO DEL CARRITO===*/
        for (const item of $_SESSION.CarritoPrueba) {

            let stockPorSucursal = {};
            let totalStock = 0;

            if (item.Kit == 1 || item.Kit == '1') {
                
                const stockKit = parseInt(item.Existencias, 10) || 0;
                
                stockPorSucursal = {
                    Colima: 0,
                    VentasInternet: stockKit,
                    Tecoman: 0,
                    VillaDeAlvarez: 0,
                    Manzanillo: 0,
                    Bodega: 0 
                };
                
                totalStock = stockKit;

            } else {
                
                if (!item?.Clave) {
                    console.warn("Item sin clave:", item);
                    continue;
                }

                const seiData = await obj.getSeicom(item.Clave);
                if (!seiData || !seiData.Table) continue;

                stockPorSucursal = normalizarStock(seiData.Table);
                totalStock = Object.values(stockPorSucursal).reduce((a, b) => a + b, 0);
            }
            // ==========================================


            /* ----- SIN STOCK ----- */
            if (totalStock === 0) {
                $_SESSION.ProdNOstock = 1;
                obj.btnEliminarRefaccion(item);
                continue;
            }

            /* ----- STOCK INSUFICIENTE ----- */
            if (totalStock < item.Cantidad) {
                $_SESSION.ProdInsufStock = 1;
                
                // Actualizamos la existencia visible para que el usuario no pida más de lo que hay
                item.Existencias = totalStock; 
                obj.btnAgregar(item);
                continue;
            }

            /* ----- COMBINACIÓN DE SUCURSALES ----- */
            const regla = combinarSucursales(stockPorSucursal, item.Cantidad);
            if (!regla) continue;
            
            $_SESSION.ProductosPorSucursal[item.Clave] = {
                descripcion: item.Descripcion || '',
                cantidadTotal: item.Cantidad,
                sucursales: regla.usadas
            };
            $scope.ProductosPorSucursal = $_SESSION.ProductosPorSucursal;
            
            /* ----- CÁLCULO DE DÍAS EXTRA ----- */
            const diasExtra = calcularDiasEnvioReal(regla.usadas);
            const keyEnvio = "AUTO_" + item.Clave;

            if (diasExtra > 0) {
                $_SESSION.DiasenvioEXT[keyEnvio] = diasExtra;
            } else {
                obj.Costumer.DiaExtraEnvio = 0;
            }
        }

        /*===VALIDACIÓN DE ENVÍO MÚLTIPLE===*/
        const diasExtras = Object.values($_SESSION.DiasenvioEXT || {});
        const hayEnvioMultiple = diasExtras.some(d => d > 0);

        if (hayEnvioMultiple && !obj.alertaEnvioMostrada) {

            obj.alertaEnvioMostrada = true;

            setTimeout(() => {
                Swal.fire({
                    icon: 'info',
                    title: 'Tiempo de entrega',
                    html: `
                        <p>
                            Tu pedido se surtirá desde <strong>diferentes sucursales</strong>,
                            por lo que el tiempo de entrega podría
                            <strong>extenderse un poco más de lo habitual</strong>.
                        </p>
                    `,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#3085d6'
                });
            }, 1000);
        }
    }

    obj.btnAgregar = (Refaccion) => {
        if ($_SESSION.ProdInsufStock == 1) {
            Swal.fire({
                title: "Piezas Insuficientes",
                html: `¡Oops!, Parece que hay más piezas de las que tenemos en existencia,
                       vamos actualizar tu pieza al maximo en existencia,
                       las cuales son: <strong>${Refaccion.Existencias} Piezas en existencia</strong>.`,
                imageUrl: "https://macromautopartes.com/images/refacciones/" + Refaccion.imagenid + ".webp",
                imageWidth: 400,
                imageHeight: 200,
                imageAlt: Refaccion._producto,
                confirmButtonText: "Ok"
            }).then((result) => {
                if (result.isConfirmed || result.dismiss == 'backdrop' || result.dismiss == 'esc') {
                    Refaccion.Cantidad = Refaccion.Existencias;
                    obj.Ttotal();
                    actualizarCarrito(Refaccion);
                }
            });
            return;
        }

        //si puede aumentar, aumenta. Si no, avisa.
        if (parseInt(Refaccion.Cantidad) < parseInt(Refaccion.Existencias)) {
            Refaccion.Cantidad++;
            obj.Ttotal();
            actualizarCarrito(Refaccion);
        } else {
            toastr.warning("Solo tenemos " + Refaccion.Existencias + " piezas en existencia de este producto.");
        }
    };

    obj.btnQuitar = (Refaccion) => {

        Refaccion.Cantidad--;

        if (Refaccion.Cantidad < 1) {
            obj.btnEliminarRefaccion(Refaccion);
            Refaccion.Cantidad = 1;
            return;
        }

        obj.Ttotal();
        actualizarCarrito(Refaccion);
    };

    obj.validarCantidadCart = (Refaccion) => {
        if (Refaccion.Cantidad === undefined || Refaccion.Cantidad === null) return;

        let cantidadActual = parseInt(Refaccion.Cantidad);
        let stockMaximo = parseInt(Refaccion.Existencias);

        if (cantidadActual > stockMaximo) {
            Refaccion.Cantidad = stockMaximo; // Lo bajamos al máximo
            toastr.warning("Solo tenemos " + stockMaximo + " piezas en existencia.");
        }
        
        // Si el número es válido y mayor a 0, actualizamos el carrito en tiempo real
        if (Refaccion.Cantidad > 0) {
            obj.Ttotal();
            actualizarCarrito(Refaccion);
        }
    };

    //cuando el usuario da clic fuera del input (pierde el foco)
    obj.formatearCantidadCart = (Refaccion) => {
        // Si el usuario dejó el campo vacío o intentó poner 0 o menos
        if (!Refaccion.Cantidad || Refaccion.Cantidad < 1) {
            Refaccion.Cantidad = 1; // Lo regresamos a 1
            obj.Ttotal();
            actualizarCarrito(Refaccion);
        }
    };

    obj.btnEliminarRefaccion = (Refaccion) => {
        if($_SESSION.ProdNOstock == 1){
            Swal.fire({
                title: "Pieza sin Stock",
                text: "¡Oops!, La pieza se quedo sin stock",
                imageUrl: "https://macromautopartes.com/images/refacciones/"+Refaccion.imagenid+".webp",
                imageWidth: 400,
                imageHeight: 200,
                imageAlt: Refaccion._producto,
                confirmButtonText: "Ok"
            }).then((result) =>{
                if(result.isConfirmed || result.dismiss == 'backdrop' || result.dismiss == 'esc'){
                    Refaccion.erase = 1;
                    Refaccion.borrar = Refaccion.Clave;
                    Refaccion.n = $_SESSION["CarritoPrueba"]["length"];
                    obj.actualizarSession(Refaccion, true);
                }
            });
        }else{
            Swal.fire({
            title: "¿Deseas Eliminar la Refaccion del carrito?",
            showCancelButton: true,
            confirmButtonText: "Eliminar",
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    showConfirmButton: false,
                    title: "¡Eliminado!",
                    text: "El articulo fue eliminado",
                    icon: "success"
                });
                Refaccion.erase = 1;
                Refaccion.borrar = Refaccion.Clave;
                Refaccion.n = $_SESSION["CarritoPrueba"]["length"];
                obj.actualizarSession(Refaccion, true);
                // Eliminar visualmente del carrito (frontend inmediato)
                obj.session.CarritoPrueba = obj.session.CarritoPrueba
                    .filter(item => item.Clave !== Refaccion.Clave);

                obj.Numproducts = obj.session.CarritoPrueba.length;
                obj.subtotal();
                obj.Ttotal();
            }
        });
        }
    }

    obj.subtotal = () => {

        obj.Costumer.Subtotal = Object.values(obj.session.CarritoPrueba || [])
            .reduce((total, item) => {

                const precio = item.RefaccionOferta == '1'
                    ? item.Precio2
                    : item.Precio;

                return total + (item.Cantidad * precio);

            }, 0);
        
        recalcularCupon();
        return obj.Costumer.Subtotal;
    };

    obj.Ttotal = () => {
        if (obj.Costumer.Cenvio.Envio == 'L') {
            obj.Costumer.Cenvio.Costo = obj.dataCotizador.parcel.weight == 0 ? 0 : obj.cenvio;
            obj.requiredEnvio = true;
        }
        if(obj.dataCotizador.parcel.weight == 0 && obj.Costumer.Cenvio.Costo == 0 && obj.Numproducts != 0){
            obj.Costumer.Cenvio.Costo = 120.00;
        }
        obj.total = obj.Costumer.Subtotal + parseFloat(obj.Costumer.Cenvio.Costo) - obj.Costumer.descuento;
        return obj.total;
    }

    obj.actualizarSession = (Refaccion, opc) => {
        $http({
            method: 'POST',
            url: url_session,
            data: { modelo: Refaccion }
        }).then(function successCallback(res) {
            if (opc) {
                // Lanzamos el aviso global para que Cabecera y Compras se actualicen
                $scope.$root.$broadcast('carritoActualizado');
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizo la conexion con el servidor");
        });
    };
    
    /*=== TOOLTIP ===*/
    $scope.tooltipSucursal = {
        visible: false,
        data: [],
        style: {},
        claveActiva: null
    };

    $scope.toggleTooltipSucursal = function(event, clave) {
        if ($scope.tooltipSucursal.claveActiva === clave) {
            $scope.tooltipSucursal.visible = false;
            $scope.tooltipSucursal.claveActiva = null;
            return;
        }

        const info = $scope.ProductosPorSucursal[clave];
        if (!info) return;

        const rect = event.currentTarget.getBoundingClientRect();

        $scope.tooltipSucursal.data = info.sucursales;
        $scope.tooltipSucursal.visible = true;
        $scope.tooltipSucursal.claveActiva = clave;

        $scope.tooltipSucursal.style = {
            position: 'fixed',
            top: (rect.bottom + 8) + 'px',
            left: (rect.left + rect.width / 2) + 'px',
            transform: 'translateX(-50%)',
            zIndex: 9999
        };
    };

    document.addEventListener("click", function(e){
        if(!e.target.closest(".envio-label")){
            $scope.$applyAsync(() => {
                $scope.tooltipSucursal.visible = false;
                $scope.tooltipSucursal.claveActiva = null;
            });
        }
    });

    obj.getSeicom = async (clave) => {
        try {
            const response = await $http({
                method: 'GET',
                url: url_seicom,
                params: { articulo: clave },
                headers: { 'Content-Type': "application/x-www-form-urlencoded" },
                transformResponse: data => $.parseXML(data)
            });
            const xml = $(response.data).find("string");
            const json = JSON.parse(xml.text());
            obj.SeiData = json;
            return json;
        } catch (error) {
            console.error(error); 
            return null;
        }
    };

    /* EVÍO / COTIZADOR */
    obj.empaquetar = () => {
        const parcel = obj.dataCotizador.parcel;
        let pesoTotal = 0;
        let maxL = 0;
        let maxW = 0;
        let sumH = 0;

        const LADO_MAXIMO = 150;
        const PESO_MAXIMO = 60;
        const MARGEN_CAJA = 3;

        const items = Object.values(obj.session.CarritoPrueba || {});

        if (items.length === 0) {
            Object.assign(parcel, { weight: 0, length: 0, width: 0, height: 0 });
            obj.requiereCotizacionManual = false;
            return;
        }

        items.forEach(e => {
            const l = parseFloat(e.Largo) || 0;
            const w = parseFloat(e.Ancho) || 0;
            const h = parseFloat(e.Alto) || 0;
            const peso = parseFloat(e.Peso) || 0;
            const cantidad = parseInt(e.Cantidad) || 1;

            const dims = [l, w, h].sort((a, b) => b - a);
            const itemL = dims[0];
            const itemW = dims[1];
            const itemH = dims[2];

            maxL = Math.max(maxL, itemL);
            maxW = Math.max(maxW, itemW);

            let factorApilamiento = 1;
            if (e._producto && e._producto.toUpperCase().includes("FASCIA")) {
                factorApilamiento = 0.5;
            }

            if (cantidad > 1) {
                sumH += itemH + (itemH * (cantidad - 1) * factorApilamiento);
            } else {
                sumH += itemH;
            }

            if (!e.Enviogratis) {
                pesoTotal += (peso * cantidad);
            }
        });

        parcel.length = Math.ceil(maxL + MARGEN_CAJA);
        parcel.width  = Math.ceil(maxW + MARGEN_CAJA);
        parcel.height = Math.ceil(sumH + MARGEN_CAJA);
        parcel.weight = pesoTotal > 0 ? parseFloat(pesoTotal.toFixed(2)) : 1;

        if (parcel.length > LADO_MAXIMO || parcel.width > LADO_MAXIMO || parcel.height > LADO_MAXIMO || parcel.weight > PESO_MAXIMO) {
            obj.requiereCotizacionManual = true;
            console.warn("Dimensiones excedidas. Se requiere cotización manual por paqueteria.");
        } else {
            obj.requiereCotizacionManual = false;
        }

        obj.requiredEnvio = parcel.weight === 0;
    };

    obj.eliminarPaqueterias = (data) => {
        let arrayPaq = ["CARSSA", "SKYDROPX", "JTEXPRESS", "SANDEX", "QUIKEN", "SENDEX", "UPS", "TRACUSA", "TRESGUERRAS"];
        arrayPaq.forEach(e => {
            data = data.filter(paqueteria => paqueteria.provider != e)
        });
        return data
    }

    obj.btnCotizacion = () => {
        // Validar peso mínimo
        if(obj.dataCotizador.parcel.weight < 1){
            obj.dataCotizador.parcel.weight = 1;
        }
        
        obj.dataCotizador.zip_to = obj.Costumer.dataDomicilio.data.Codigo_postal;
        
        $http({
            method: 'POST',
            url: urlSkydropx,
            data: obj.dataCotizador,
            headers: {
                'Authorization': token,
                'Content-Type': "application/json"
            }
        }).then(function successCallback(res) {
            if (res.data) {
                obj.cotizador = obj.eliminarPaqueterias(res.data);
                obj.cotizador.forEach(e => {
                    e.newtotal = (parseFloat(e.total_pricing) + 4.64) + parseFloat(e.total_pricing * (3.2/100));
                });
            }
            obj.flag = false;

        }, function errorCallback(res) {
            console.error("Error en la cotización de Skydropx: ", res);
            toastr.error("Hubo un problema al cotizar el envío. Revisa tu código postal o intenta más tarde.");
            
            obj.flag = false; 
            obj.cotizador = [];
        });
    }

    obj.btncotizar = () => {
        if (obj.requiereCotizacionManual) {
            Swal.fire({
                icon: 'warning',
                title: 'Envío Voluminoso - Acción Requerida',
                width: '600px',
                html: `
                    <style>
                        .swal2-confirm, .swal2-cancel {
                            font-size: 16px !important;
                            padding: 14px 28px !important;
                            border-radius: 8px !important;
                            font-weight: 600 !important;
                        }
                        @media (max-width: 500px) {
                            .swal2-actions {
                                flex-direction: column-reverse !important;
                                gap: 12px !important;
                                width: 100% !important;
                            }
                            .swal2-confirm, .swal2-cancel {
                                width: 100% !important;
                                margin: 0 !important;
                            }
                        }
                    </style>

                    <div style="text-align: left; font-size: 15px;">
                        <p>Tu pedido incluye artículos de gran tamaño que exceden las dimensiones estándar de las paqueterías convencionales.</p>
                        
                        <!-- Caja de advertencia visual -->
                        <div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border-left: 5px solid #ffeeba; margin: 15px 0;">
                            <strong>⚠️ AVISO IMPORTANTE SOBRE TU PAGO:</strong><br><br>
                            El pago que estás a punto de realizar en la página <strong>CUBRE ÚNICAMENTE EL COSTO DE LAS REFACCIONES</strong>.<br><br>
                            El costo del envío <strong>NO ESTÁ INCLUIDO</strong> (por ello marca $0 temporalmente) y deberá ser cotizado y pagado por separado una vez que acordemos la paqueteria ideal para ti.
                        </div>

                        <p>Al continuar con la compra, confirmas que <strong>estás de acuerdo con estos términos</strong> y te comprometes a liquidar el costo del envio posteriormente.</p>
                        
                        <p>Para acordar el envío de inmediato, termina tu compra y contáctanos:</p>
                        
                        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px; margin-bottom: 5px;">
                            <a href="https://wa.me/523122682028?text=Hola,%20acabo%20de%20hacer%20un%20pedido%20voluminoso%20y%20estoy%20de%20acuerdo%20en%20pagar%20el%20envío%20por%20separado." 
                               target="_blank" 
                               style="background-color: #25d366; color: white; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.3s;">
                                <i class="fab fa-whatsapp"></i> Acordar envío por WhatsApp
                            </a>
                            
                            <a href="https://m.me/MacromAutopartes" 
                               target="_blank" 
                               style="background-color: #1877f2; color: white; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.3s;">
                                <i class="fab fa-facebook-messenger"></i> Acordar envío por Facebook
                            </a>

                            <a href="/contacto" 
                               target="_blank"
                               style="background-color: #6c757d; color: white; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: bold; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.3s;">
                                <i class="fas fa-envelope"></i> Formulario de Contacto
                            </a>
                        </div>
                    </div>
                `,
                confirmButtonText: '<i class="fas fa-check-circle" style="margin-right: 5px;"></i> Sí, acepto pagar el envío por separado',
                confirmButtonColor: '#de0007',
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-times-circle" style="margin-right: 5px;"></i> No, cancelar compra',
                cancelButtonColor: '#343a40', 
                reverseButtons: true, 
                padding: '2em'
            }).then((result) => {
                if (result.isConfirmed) {
                    $scope.$apply(() => {
                        obj.Costumer.Cenvio.paqueteria = "Acordar con el negocio";
                        obj.Costumer.Cenvio.Costo = 0; 
                        obj.Costumer.Cenvio.Servicio = "Envío por paqueteria(Por Cotizar)";
                        obj.Costumer.Cenvio.enviodias = "Variable";
                        obj.Costumer.DiaEstimado = "Por confirmar";
                        obj.requiredEnvio = true; 
                        
                        obj.Costumer.aviso = true; 
                        
                        obj.Ttotal(); 
                        
                        toastr.success("Envío por acordar seleccionado. Ya puedes dar clic en PAGAR AHORA.");
                    });
                }
            });
            return; 
        }

        // Flujo normal para paquetes estándar
        obj.flag = true;
        obj.btnCotizacion();
        $("#mdlcotizar").modal('show');
    };

    obj.selectenvio = (params) => {
        obj.Costumer.Cenvio.paqueteria = params.provider;
        obj.Costumer.Cenvio.Costo = params.newtotal;
        obj.Costumer.Cenvio.CostoSnIva = params.total_pricing;
        obj.Costumer.Cenvio.enviodias = params.days;
        obj.Costumer.DiaEstimado = obj.getFechaentrega(obj.Costumer.Cenvio.enviodias);
        obj.Costumer.Cenvio.Servicio = params.service_level_name;
        obj.requiredEnvio = true;
        $("#mdlcotizar").modal('hide');
    }

    obj.getFechaentrega = (dias) => {
        const diasExtra = Math.max(0, ...Object.values($_SESSION.DiasenvioEXT || {}));
        dias += diasExtra;
        const arrayDias = ["Domingo", "Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sabado", "Domingo"];
        const arrayMes = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"]
        const fecha = moment().add(dias, 'days')
        return `${arrayDias[fecha.day()]} ${fecha.format("DD")} ${arrayMes[fecha.month()]}`;
    }

    /* CLIENTE / USUARIO */
    obj.getDataUser = (data) => {
        $http({
            method: 'POST',
            url: url_getusercampras,
            data: { Compras: data }
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                obj.Costumer.profile = res.data.Data.datauser;
                obj.Costumer.dataDomicilio = res.data.Data.datadomicilio;
                obj.Costumer.dataCP = res.data.Data.dataCP;
                obj.Costumer.dataFacturacion = res.data.Data.datafacturacion;
                obj.Costumer.Cenvio = res.data.Data.Cenvio;
                obj.cenvio = res.data.Data.Cenvio.Costo;
                localStorage.setItem("id", obj.Costumer.profile.id)
                localStorage.setItem("id_rfc", obj.Costumer.dataFacturacion._id)
                obj.Ttotal();
                obj.dataflag = true;
                prodCarrito(); 
                $scope.ProductosPorSucursal = $_SESSION.ProductosPorSucursal;
            } else {
                toastr.error(res.data.Mensaje || res.data.mensaje || "Error al cargar datos del usuario");
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizo la conexion con el servidor");
        });
    };

    obj.btndireccionguardadas = (pag) => {
        localStorage.setItem("pag", pag);
        location.href = "/Profile/" + pag;
    }

    obj.comprobarDatosFacturacion = (value) => {
        obj.factflag = value == 1 ? true : false;
    }

    /* PAGO */
    obj.btnPagar = () => {
        if (obj.Costumer.dataDomicilio.Bandera) {
            if ((obj.Costumer.facturacion == "1" && obj.Costumer.dataFacturacion.Bandera) || obj.Costumer.facturacion == "0") {
                if (obj.requiredEnvio) {
                    
                    // Mostramos el Loader
                    obj.ui.loader.mostrar("Procesando pedido...");

                    obj.Costumer.opc = "buy2";
                    if (obj.Costumer.profile.cupon_nombre != null && obj.Costumer.profile.cupon_nombre != "") {
                        const cpn = obj.Costumer.profile.cupon_nombre.split(",");
                        obj.Costumer.usercpn = cpn.filter(Discpn => Discpn != obj.Costumer.usercpn);
                        obj.Costumer.usercpn = obj.Costumer.usercpn.toString();
                    }
                    obj.Costumer.Medidas = obj.dataCotizador.parcel;
                    obj.ProcesarCompra(obj.Costumer);
                } else {
                    toastr.error("Error: Debes de seleccionar el costo del envio");
                }
            } else {
                toastr.error("Error: no hay datos de facturacion predeterminados");
            }
        } else {
            toastr.error("Error no tienes una direccion de envio predeterminada");
        }
    }

    const activarEstiloPago = (idElemento) => {
        ["btntransfe", "btnefectivo", "btncredito"].forEach(id => {
            let btn = document.getElementById(id);
            if(btn) {
                btn.style.borderColor = "#e5e7eb";
                btn.style.backgroundColor = "#fff";
                btn.style.boxShadow = "none";
                btn.style.borderWidth = "1px";
                btn.style.transform = "scale(1)";
                btn.style.transition = "all 0.3s ease";
            }
        });
        
        let btnActivo = document.getElementById(idElemento);
        if(btnActivo) {
            btnActivo.style.borderColor = "#de0007";
            btnActivo.style.backgroundColor = "#e58b8bb8";
            btnActivo.style.boxShadow = "0 6px 15px rgba(222,0,7,0.15)";
            btnActivo.style.borderWidth = "3px";
            btnActivo.style.transform = "scale(1.03)";
        }
    }

    obj.metransfe = () => {
        obj.Costumer.metodoPago = "Transferencia";
        activarEstiloPago("btntransfe");
    }
    
    obj.medeposito = () => {
        obj.Costumer.metodoPago = "Deposito";
        activarEstiloPago("btnefectivo");
    }
    
    obj.metarjeta = () => {
        obj.Costumer.metodoPago = "Tarjeta";
        activarEstiloPago("btncredito");
    }

    obj.ProcesarCompra = (data) => {
        $http({
            method: 'POST',
            url: urlCostumer,
            data: { Costumer: data }
        }).then(function successCallback(res) {
            
            if (res.data.Bandera == 1) {
                if (obj.total === 0) {
                    location.href = "/ProcesoCompra/paso3";
                } else if (obj.Costumer.metodoPago === "Deposito" || obj.Costumer.metodoPago === "Transferencia") {
                    obj.openDeposito(res.data.Data);
                } else if (obj.Costumer.metodoPago === "Tarjeta") {
                    
                    obj.ui.loader.mostrar("Redirigiendo a pasarela segura..."); 
                    let urlBanco = res.data.data || res.data.Data || res.data.url;
                    obj.seturl(urlBanco);
                }
            } else {
                obj.ui.loader.ocultar();
                toastr.warning(res.data.mensaje || "Ocurrió un problema, revisa tu método de pago.");
            }
        }, function errorCallback(res) {
            obj.ui.loader.ocultar();
            toastr.error("Error: no se realizo la conexion con el servidor");
        });
    }

    obj.openDeposito = (id) => {
        localStorage.setItem("id_pedido", id)
        var popUp = window.open("./Reportes/fichaDeposito/Controller.php");
        if (popUp == null || typeof (popUp) == 'undefined') {
            toastr.info("Aviso: Tienes bloqueadas las ventanas emergentes. Puedes ver tu ficha desde 'Mis Pedidos'.");
        }
        
        location.href = "/ProcesoCompra/paso3";
    }
    
    obj.seturl = (url) => {
        if (typeof url === 'object' && url !== null) {
            url = url[0] || Object.values(url)[0]; 
        }

        if (!url || typeof url !== 'string') {
            obj.ui.loader.ocultar(); 
            toastr.error("Error crítico: El banco no devolvió la URL de pago.");
            console.error("URL de pago inválida/vacía recibida del backend:", url);
            return;
        }

        window.location.href = url;
    }

    obj.RefaccionDetalles = (_id, newurl) => {
        window.open("/catalogo/detalles/" + _id + "-" + newurl, "_self");
    }

    obj.aplicarCupon = () => {

        const inpCupon = document.getElementById("inpCupon");
        const btnCupon = document.getElementById("btncupon");
        const alertCupon = document.getElementById("alert--cupon");

        const codigo = inpCupon.value.trim();
        alertCupon.className = "";
        alertCupon.innerHTML = "";

        if (codigo === "") {
            alertCupon.classList.add("cupon--alert-active");
            alertCupon.innerHTML = "Ingresa un cupón";
            return;
        }

        $http({
            method: "POST",
            url: url_getusercampras,
            data: {
                Compras: {
                    opc: "validarCupon",
                    cupon: codigo,
                    id: obj.Costumer.profile.id
                }
            }
        }).then(res => {

            if (res.data.Bandera === 1) {

                const porcentaje = parseInt(res.data.descuento);
                const monto = obj.Costumer.Subtotal * (porcentaje / 100);

                obj.Costumer.descuento = monto;
                obj.Costumer.valor_cpn = porcentaje;
                obj.Costumer.usercpn = res.data.codigo;
                obj.Costumer.id_cupon = res.data.id_cupon;

                obj.Ttotal();

                btnCupon.disabled = true;
                inpCupon.disabled = true;

                alertCupon.innerHTML = res.data.mensaje;

            } else {
                alertCupon.classList.add("cupon--alert-active");
                alertCupon.innerHTML = res.data.mensaje;
            }

        }, () => {
            alertCupon.classList.add("cupon--alert-active");
            alertCupon.innerHTML = "Error al validar cupón";
        });
    };

    var closecotizar = document.getElementById("cotizarclose");
    closecotizar.addEventListener("click", function () {
        $("#mdlcotizar").modal('hide');
    });

    obj.changeCenvio = (Tarifa) => {
        id = obj.Costumer.Cenvio.Costos.find(e => e.Tarifa === Tarifa);
    }

    if ($_SESSION.facturacion == null) {
        obj.Costumer.facturacion = 0;
    } else if ($_SESSION.facturacion == 1) {
        obj.Costumer.facturacion = 1;
    }

    var butccompra = document.querySelector(".clip");
    var butcompra = document.querySelector(".confirmar--pago");
    $scope.$on('carritoActualizado', function() {
        $http({
            method: 'POST',
            url: url_session,
            data: { opc: "obtener_carrito_actualizado" }
        }).then(function(res) {
            if (res && res.data && res.data.Bandera == 1) {
                obj.session.CarritoPrueba = res.data.Data.Carrito;
                
                if(obj.session.CarritoPrueba) {
                    obj.eachRefacciones(obj.session.CarritoPrueba);
                }
                
                obj.Numproducts = obj.session.CarritoPrueba ? obj.session.CarritoPrueba.length : 0;
                obj.subtotal();
                obj.empaquetar(); 
                
                obj.Ttotal();
                prodCarrito(); 
            }
        });
    });
    angular.element(document).ready(function () {
        if (obj.session.autentificacion == undefined && obj.session.autentificacion != 1) {
            location.href = "/login";
        } else {
            obj.getDataUser({ opc: "get", username: obj.session.usr })
            obj.empaquetar();
        }
    });
}

tsuruVolks.controller("ProfileCtrl", ["$scope", "$http", ProfileCtrl]);

function ProfileCtrl($scope, $http) {
    var obj = $scope;
    obj.session = $_SESSION;
    obj.pag = "";
    obj.profile = {};
    obj.Facturacion = { dataFacturacion: [], usocfdi: [], estados: [], Actividad: [{ valor: "Persona Fisica" }, { valor: "Persona Moral" }] };
    obj.Mispedidos = [];

    obj.btnMenulinks = (opc = '') => {
        if (opc != "") {
            location.href = "/Compras";
        } else {
            location.href = "/Profile";
        }
    }

    obj.facturaNo = () => {
        obj.sendFacturacion('none', obj.dataFacturacion)
    }

    obj.inputvalidireccion = () => { 
        let agregar = []; 
        let agregar_div = []; 
        let agregar_lbl = []; 
        for (var j = 0; j <= 7; j++) {
            // Aseguramos que si el elemento no existe (ej. campos opcionales) no reviente el código
            let elemento = document.getElementById("agregar_" + (j + 1));
            agregar[j] = elemento ? elemento.value : "";
            agregar_div[j] = document.getElementById("agregar_div" + (j + 1));
            agregar_lbl[j] = document.getElementById("agregar_lbl" + (j + 1));
        }
        validateEmptyDire(agregar, agregar_div, agregar_lbl);
    }

    function validateEmptyDire(agregar, agregar_div, agregar_lbl) {
        var hasError = false;
        
        for (var i = 0; i <= 7; i++) {
            if (agregar[i].trim().length == 0) {
                if(agregar_div[i]) agregar_div[i].style.borderColor = "var(--primario)";
                if(agregar_lbl[i]) agregar_lbl[i].style.color = "var(--primario)";
                hasError = true;
            } else {
                if(agregar_lbl[i]) agregar_lbl[i].style.color = "var(--negro)";
                if(agregar_div[i]) agregar_div[i].style.borderColor = "#d1d5db"; // Gris moderno
            }
        }
        
        if (hasError) {
            toastr.warning("Por favor, completa todos los datos marcados en rojo para continuar.");
        } else {
            Swal.fire({
                title: "¿Deseas Guardar Domicilio?",
                showDenyButton: true,
                confirmButtonText: "Guardar",
                denyButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ showConfirmButton: false, title: "¡Domicilio Guardado!", icon: "success" });
                    obj.SendDirecciones('add', obj.dataDireccion);
                } else if (result.isDenied) {
                    Swal.fire("Domicilio no guardado", "", "error");
                }
            });
        }
    } 

    obj.inputvalidfactura = () => { 
        let agregar = []; 
        let agregar_div = []; 
        let agregar_lbl = []; 
        for (var j = 1; j <= 6; j++) {
            let elemento = document.getElementById("Fagregar_" + j);
            agregar[j] = elemento ? elemento.value : "";
            agregar_div[j] = document.getElementById("Fagregar_div" + j);
            agregar_lbl[j] = document.getElementById("Fagregar_lbl" + j);
        }
        validateEmptyFact(agregar, agregar_div, agregar_lbl);
    }

    function validateEmptyFact(agregar, agregar_div, agregar_lbl) {
        var hasError = false;
        for (var i = 1; i <= 6; i++) {
            if (agregar[i].trim().length == 0) {
                if(agregar_div[i]) agregar_div[i].style.borderColor = "var(--primario)";
                if(agregar_lbl[i]) agregar_lbl[i].style.color = "var(--primario)";
                hasError = true;
            } else {
                if(agregar_lbl[i]) agregar_lbl[i].style.color = "var(--negro)";
                if(agregar_div[i]) agregar_div[i].style.borderColor = "#d1d5db";
            }
        }
        
        if (hasError) {
            toastr.warning("Por favor, completa todos los datos marcados en rojo para continuar.");
        } else {
            Swal.fire({
                title: "¿Deseas Guardar Estos Datos?",
                showDenyButton: true,
                confirmButtonText: "Guardar",
                denyButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ showConfirmButton: false, title: "¡Datos Guardados!", icon: "success" });
                    obj.sendFacturacion('add', obj.dataFacturacion);
                } else if (result.isDenied) {
                    Swal.fire("Los Datos No Fueron Guardados!", "", "error");
                }
            });
        }
    }//Termina Verificador de Agregar nueva Factura.

    /* variables seccion direcciones */
    obj.dataDireccion = { Estatus: 1, Predeterminado: 0 }
    obj.dataFacturacion = { Estatus: 1, Predeterminado: 0 }

    obj.sendProfile = (opc) => {
        $http({
            method: 'POST',
            url: urlProfile,
            data: { profile: { opc: opc, tipo: obj.pag, data: obj.profile } }
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                toastr.success(res.data.mensaje);
            } else {
                toastr.error(res.data.mensaje);
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizo la conexion con el servidor");
        });
    }

    obj.getDatosCliente = () => {
        $http({
            method: 'POST',
            url: urlProfile,
            data: { profile: { opc: "buscar", tipo: obj.pag } }
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                obj.profile = res.data.Data;
                obj.profile.arrayDomicilios.forEach(function (el) {
                    if (el.Predeterminado == 1) {
                        localStorage.setItem("_id_domicilio", el._id);
                    }
                });
                if (!localStorage.getItem("iduser")) {
                    localStorage.setItem("iduser", obj.profile._id)
                }
            } else {
                toastr.error("Error: ");
            }
        }, function errorCallback(res) {
            toastr.info("Info: No se actualizarón elementos necesarios, Actualizar pagina si está no se actualiza sola en los proximos 5segundos.");
            setTimeout(() => {
                location.reload();
            }, 3000);
        });
    }

    /* Termina el modulo de Session y Seguridad */
    /* Inicia modulo de Direcciones */
    obj.addDirecciones = () => {
        obj.btnMenulinks('Direcciones_add');
    }

    obj.SendDirecciones = (opc, data) => {
        $http({
            method: 'POST',
            url: urlProfile,
            data: { profile: { opc: opc, tipo: obj.pag, data: data } }
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                switch (opc) {
                    case 'add':
                    case 'save':
                        toastr.success(res.data.mensaje);
                        setTimeout(() => {
                            obj.btnMenulinks("Direcciones")
                        }, 100);
                        break;
                    case 'delete':
                    case 'set':
                        toastr.success(res.data.mensaje);
                        obj.getDatosCliente();
                        break;
                    case 'edit':
                        obj.dataDireccion = res.data.Data;
                        obj.Facturacion.estados = res.data.Data2;
                        break;
                }

            } else {
                toastr.error(res.data.mensaje);
            }
        }, function errorCallback(res) {
            toastr.info("No se encontro ningun Domicilio registrado");
        });
    }

    obj.btnGuardarDireccion = (opc) => {
        Swal.fire({
            title: "¿Deseas Guardar Domicilio?",
            showDenyButton: true,
            confirmButtonText: "Guardar",
            denyButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ showConfirmButton: false, title: "¡Guardado!", icon: "success" });
                obj.SendDirecciones(opc, obj.dataDireccion)
            } else if (result.isDenied) {
                Swal.fire("Domicilio no guardado", "", "error");
            }
        });
    }

    obj.btndescartarDomicilio = (id) => {
        Swal.fire({
            title: "¿Deseas Eliminar Domicilio?",
            showDenyButton: true,
            confirmButtonText: "Eliminar",
            denyButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ showConfirmButton: false, title: "Eliminado!", icon: "success" });
                obj.SendDirecciones("delete", { id: id });
                location.reload();
            }
        });
    }

    obj.btneditDomicilio = (id) => {
        //obj.btnMenulinks("Direcciones_edit");
        localStorage.setItem("_id_domicilio", id);
    }

    obj.btnPredeterminado = (id) => {
        obj.SendDirecciones("set", { id_domicilio: id, id: localStorage.getItem("id") });
        location.reload();
        localStorage.setItem("_id_domicilio", id);
    }

    /* Termina modulo de direcciones */

    /* Inicia modulo de Datos de Facturacion */
    obj.addFacturacion = () => {
        location.href = "/Profile/Facturacion_add";
        localStorage.setItem("pag", "Facturacion_add")
    }

    obj.btnEditadatosFacturacion = (id) => {
        localStorage.setItem("id_rfc", id);
        obj.btnMenulinks('Facturacion_edit');
    }

    obj.sendFacturacion = (opc = "buscar", data = null) => {
        $http({
            method: 'POST',
            url: urlProfile,
            data: { profile: { opc: opc, tipo: obj.pag, data: data } }
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {

                switch (opc) {
                    case 'add':
                    case 'save':
                        toastr.success(res.data.mensaje);
                        setTimeout(() => {
                            obj.btnMenulinks("Facturacion")
                        }, 500);
                        break;
                    case 'pre':
                        obj.btnMenulinks("Facturacion");
                        break;
                    case 'none':
                        obj.btnMenulinks("Facturacion");
                        break;
                    case 'del':
                        toastr.success(res.data.mensaje);
                        obj.sendFacturacion();
                        break;
                    case 'edit':
                        obj.Facturacion.dataFacturacion = res.data.Data.RFC;
                        obj.Facturacion.usocfdi = res.data.Data.usoCFDI;

                        break;
                    case 'new':
                        obj.Facturacion.usocfdi = res.data.Data;
                        obj.Facturacion.estados = res.data.Data2;
                        break;
                    default:
                        obj.Facturacion.dataFacturacion = res.data.Data;
                        break;
                }


            } else {
                toastr.error(res.data.mensaje);
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizo la conexion con el servidor");
        });
    }


    obj.btnGuardarnuevosdatos = (opc) => {
        Swal.fire({
            title: "¿Deseas Guardar Estos Datos?",
            showDenyButton: true,
            confirmButtonText: "Guardar",
            denyButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ showConfirmButton: false, title: "Guardado!", icon: "success" });
                obj.sendFacturacion(opc, obj.dataFacturacion)
            } else if (result.isDenied) {
                Swal.fire("Datos Facturación no guardados", "", "error");
            }
        });
    }

    obj.btnEliminardatosfacturacion = (id, opc) => {
        Swal.fire({
            title: "¿Deseas Eliminar Estos Datos?",
            showDenyButton: true,
            confirmButtonText: "Eliminar",
            denyButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ showConfirmButton: false, title: "Eliminado!", icon: "success" });
                obj.sendFacturacion(opc, { _id: id })
                location.reload();
            }
        });
    }

    obj.btnEditardatosfacturacion = (opc) => {
        Swal.fire({
            title: "¿Deseas Guardar Los Cambios en los Datos?",
            showDenyButton: true,
            confirmButtonText: "Guardar",
            denyButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ showConfirmButton: false, title: "¡Cambios Guardados!", icon: "success" });
                obj.sendFacturacion(opc, obj.Facturacion.dataFacturacion);
            } else if (result.isDenied) {
                Swal.fire("¡Cambios No Guardados!", "", "error");
            }
        });
    }

    obj.btnFacpredetermiando = (id, opc) => {
        obj.sendFacturacion(opc, { _id: id });
    }
    /* Finaliza modulo de Datos de Facturacion */

    /* */

    angular.element(document).ready(function () {
        if ((obj.session.autentificacion == undefined && obj.session.autentificacion != 1)) {
            localStorage.clear();
            location.href = "/login";
        }

        if (!localStorage.getItem("iduser")) {
            obj.getDatosCliente();
        }

        switch (obj.pag) {
            case 'Session':
            case 'Direcciones':
                obj.getDatosCliente();
                break;
            case 'Direcciones_add':
                obj.dataDireccion.id = obj.session.iduser;
                break;
            case 'Direcciones_edit':
                obj.dataDireccion.id_domicilio = localStorage.getItem('_id_domicilio');
                setTimeout(() => {
                    obj.SendDirecciones('edit', obj.dataDireccion);
                }, 100);
                break;
            case 'Facturacion':
                obj.sendFacturacion("buscar", { _id_cliente: localStorage.getItem("iduser") });
                break
            case 'Facturacion_add':
                obj.sendFacturacion("new");
                obj.dataFacturacion._id_cliente = localStorage.getItem("iduser");
                break;
            case 'Facturacion_edit':
                obj.sendFacturacion('edit', { _id: localStorage.getItem("id_rfc") })
                break
            case 'Mispedidos':
                obj.sendMispedidos("buscar", { _id: localStorage.getItem("iduser") })
                break
            case 'Mispedidos_view':
                obj.sendMispedidos("details", { _idpedido: localStorage.getItem("_idPedido") });
                break;
        }
        $(".numero").numeric();
    });
}