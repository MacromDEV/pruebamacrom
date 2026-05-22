'use strict'

const urlProfile = "./modulo/Profile/Ajax/Profile.php";
const urlComprobante = "./modulo/Profile/Ajax/uploadfile.php";

tsuruVolks.controller("ProfileCtrl", ["$scope", "$http", ProfileCtrl]);
if (window.location.href.includes("/Profile")) {
    if (localStorage.getItem('iduser') == undefined) {
        location.href = "/";
    }
}
function ProfileCtrl($scope, $http) {
    var obj = $scope;
    obj.No_Pedidos = 0;
    obj.session = $_SESSION;
    obj.pag = "";
    obj.profile = {};
    obj.msjprofiledisplay = false;
    obj.Facturacion = { dataFacturacion: [], usocfdi: [], Actividad: [{ valor: "Persona Fisica" }, { valor: "Persona Moral" }] };
    obj.Mispedidos = [];
    obj.dataFactura = {}
    obj.wizard = { preparacion: false, transito: false, proceso: false, entregado: false }
    obj.comprobantefile = {};
    obj.paginador = { currentPage: 0, pages: [], pageSize: 5 }
    obj.paginador2 = { page: 0, limit: 15 }

    /* Paginacion */
    obj.configPages = () => {
        obj.paginador.pages.length = 0;
        var ini = obj.paginador.currentPage - 4;
        var fin = obj.paginador.currentPage + 5;
        if (ini < 1) {
            ini = 1;
            if (Math.ceil(obj.No_Pedidos / obj.paginador.pageSize) > 10)
                fin = 10;
            else
                fin = Math.ceil(obj.No_Pedidos / obj.paginador.pageSize);
        } else {
            if (ini >= Math.ceil(obj.No_Pedidos / obj.paginador.pageSize) - 10) {
                ini = Math.ceil(obj.No_Pedidos / obj.paginador.pageSize) - 10;
                fin = Math.ceil(obj.No_Pedidos / obj.paginador.pageSize);
            }
        }
        if (ini < 1) ini = 1;
        for (var i = ini; i <= fin; i++) {
            obj.paginador.pages.push({
                no: i, p: (obj.paginador.pageSize * i) - obj.paginador.pageSize
            });
        }
    }

    obj.nextPage = () => {
        obj.paginador.currentPage = obj.paginador.currentPage + 1;
        obj.configPages();
        obj.sendMispedidos("buscar", { _id: localStorage.getItem("iduser") }, obj.paginador.currentPage * obj.paginador.pageSize, obj.paginador.pageSize)
    }

    obj.lastPage = () => {
        obj.paginador.currentPage = obj.paginador.currentPage - 1;
        obj.configPages();
        obj.sendMispedidos("buscar", { _id: localStorage.getItem("iduser") }, obj.paginador.currentPage * obj.paginador.pageSize, obj.paginador.pageSize)
    }

    obj.setPage = function (a) {
        obj.paginador.currentPage = a.no - 1;
        obj.configPages();
        obj.sendMispedidos("buscar", { _id: localStorage.getItem("iduser") }, a.p, obj.paginador.pageSize)
    };

    /* Termina Paginacion */

    obj.btnMenulinks = (opc = '') => {
        if (opc != "") {
            location.href = "/Profile/" + opc;
        } else {
            location.href = "/Profile";
        }
    }

    /*Seccion para el modulo de mis pedidos */
    obj.setWizard = (estatus) => {
        switch (estatus) {
            case 1:
            case 2:
                obj.wizard.preparacion = true;
                break;
            case 3:
                obj.wizard.preparacion = true;
                obj.wizard.transito = true;
                break;
            case 4:
                obj.wizard.preparacion = true;
                obj.wizard.transito = true;
                obj.wizard.proceso = true;
                break;
            case 5:
                obj.wizard.preparacion = true;
                obj.wizard.transito = true;
                obj.wizard.proceso = true;
                obj.wizard.entregado = true;
                break;
        }
    }
    
    obj.sendMispedidos = (opc = "buscar", data = null, x = 0, y = obj.paginador.pageSize) => {
        $http({
            method: 'POST',
            url: urlProfile,
            data: { profile: { opc: opc, tipo: obj.pag, data: data, x: x, y: y } }
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                if (opc == "buscar") {
                    obj.No_Pedidos = res.data.Data.No_pedidos;
                    res.data.Data.Pedidos.forEach(e => {
                        e.Acreditado = parseInt(e.Acreditado);
                        e.class = obj.getcolorEstatus(e.Acreditado);
                    })
                    obj.configPages();
                    obj.Mispedidos = res.data.Data.Pedidos;

                }

                if (opc == "details") {
                    obj.Mispedidos = res.data.Data;
                    obj.Mispedidos.Acreditado = parseInt(obj.Mispedidos.Acreditado);
                    obj.setWizard(obj.Mispedidos.Acreditado);

                }
                if (opc == "DeleteComp") {
                    if (res.data.Bandera === 1) {
                        obj.Mispedidos.isFileComprobante = false;
                        angular.element('#comprobante').val(null)
                        toastr.success(res.data.mensaje);
                    } else {
                        toastr.error(res.data.mensaje);
                    }

                }
            } else {
                toastr.error(res.data.mensaje);
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizo la conexion con el servidor");
        });
    }

    obj.btnDeleteComp = (e) => {
        e.preventDefault();
        if (confirm("Estas seguro de eliminar el comprobante de pago")) {
            obj.sendMispedidos("DeleteComp", { _idpedido: localStorage.getItem("_idPedido") });
        }
    }

    obj.btnverMispedidos = (_idPedido) => {
        localStorage.setItem("_idPedido", _idPedido);
        obj.btnMenulinks("Mispedidos_view");
    }

    obj.abrirModalArchivo = (archivo) => {
        let ruta = "./Public/Comprobantes/" + archivo;
        
        let extension = archivo.split('.').pop().toLowerCase();
        let imgVisor = document.getElementById('imgVisor');
        let iframeVisor = document.getElementById('iframeVisor');

        if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(extension)) {
            iframeVisor.style.display = 'none';
            imgVisor.style.display = 'block';
            imgVisor.src = ruta;
        } else {
            imgVisor.style.display = 'none';
            iframeVisor.style.display = 'block';
            iframeVisor.src = ruta;
        }
        
        $("#ModalVerArchivo").modal('show');
    };

    obj.cerrarModalArchivo = () => {
        $("#ModalVerArchivo").modal('hide');
        document.getElementById('imgVisor').src = "";
        document.getElementById('iframeVisor').src = "";
    };

    obj.getcolorEstatus = (estatus) => {
        let classEstatus = "";
        switch (estatus) {
            case 0:
                classEstatus = "orange";
                break;
            case 1:
            case 5:
                classEstatus = "success";
                break;
            case 2:
            case 3:
            case 4:
                classEstatus = "yellow";
                break;
            case 6:
                classEstatus = "red";
                break;
        }
        return classEstatus;
    }

    obj.showModalFile = (data) => {
        obj.dataFactura = {
            Rfc: data.Rfc,
            Razon: data.Razonsocial,
            usoCFDI: data.Descripcion,
            xml: "Public/Facturas/" + data.archivoxml,
            pdf: "Public/Facturas/" + data.archivopdf
        }
        console.log(obj.dataFactura)
        $("#Mdlfiles").modal('show');
    }
    obj.mdlclose = () => {
        $("#Mdlfiles").modal('hide');
    }

    obj.btnDownloadZip = () => {
        let zip = new JSZip();
        let promise = new Blob([$.get(obj.dataFactura.xml)], { type: "text/plain;charset=utf-8" });
        zip.file(obj.dataFactura.xml, promise);
        promise = new Blob([$.get(obj.dataFactura.pdf)], { type: "text/plain;charset=utf-8" });
        console.log(promise);
        zip.file("obj.dataFactura.pdf", promise);
        console.log(zip);
    }

    obj.btnuploadComprobante = () => {
        $http({
            method: 'POST',
            url: urlComprobante,
            data: { profile: { opc: "upload", _idpedido: localStorage.getItem("_idPedido"), file: obj.comprobantefile } },
            headers: {
                'Content-Type': undefined
            },
            transformRequest: function (data) {
                var formData = new FormData();
                for (var m in data.profile) {
                    formData.append(m, data.profile[m]);
                }
                return formData;
            }
        }).then(function successCallback(res) {
            if (res.data.Bandera === 1) {
                obj.Mispedidos.isFileComprobante = true;
                obj.Mispedidos.comprobante = res.data.Data.comprobante;
                toastr.success(res.data.mensaje);
            } else {
                toastr.error(res.data.mensaje);
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizo la conexion con el servidor");
        });
    }

    obj.btnComprobanteCompra = (_idPedido) => {
        localStorage.setItem("_idPedido", _idPedido);
        window.open("./Reportes/ComprobantePago/Controller.php");
    }

    obj.btnRegresarviewPedidos = () => {
        window.location.href = "/Profile/Mispedidos";
    }

    obj.btnCancelarPedido = () => {
        if (confirm("¿Estas seguro de cancelar el pedido?")) {
            obj.sendMispedidos("CancelPedido", { _idpedido: localStorage.getItem("_idPedido") })
        }
    }
    /*Finaliza seccion mis pedidos */

    /* variables seccion direcciones */
    obj.dataDireccion = { Estatus: 1, Predeterminado: 0 }
    obj.dataFacturacion = { Estatus: 1, Predeterminado: 0 }

    /* seccion para modulo Session y seguridad */
    obj.btnGuardarSession = (press) => {
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: "btn btn-success",
                cancelButton: "btn btn-danger"
            },
            buttonsStyling: false
        });
        if (obj.profile.Nuevapass === obj.profile.Confirmarpass) {
            swalWithBootstrapButtons.fire({
                title: "¿Guardar Cambios?",
                text: "¿Deseas guardar los cambios?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Guardar",
                cancelButtonText: "Cancelar!",
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    switch (press) {
                        case 1:
                            obj.sendProfile("profile");
                            obj.ConfirmacionSucces();
                            break;
                        case 2:
                            if (obj.profile.Nuevapass === obj.profile.Confirmarpass) {
                                obj.msjprofiledisplay = false;
                                obj.sendProfile("password");
                            } else {
                                toastr.error("Las contraseñas no coinciden");
                                obj.ConfirmacionError();
                            }
                            break;
                    }
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    swalWithBootstrapButtons.fire({
                        title: "Cancelado",
                        text: "No sé realizo ningun cambio.",
                        icon: "error"
                    });
                }
            });
        } else {
            obj.msjprofiledisplay = true;
        }

    }

    var inprpass = document.querySelector("#inprpass");
    var inprcpass = document.querySelector("#inprcpass");
    var inprapass = document.querySelector("#inprapass");
    document.querySelectorAll(".pass_ver").forEach(el => {
        el.addEventListener("click", e => {
            if (el.classList.contains('pss')) {
                inprpass.toggleAttribute("type");
                el.classList.toggle("fa-eye-slash");
                if (inprpass.getAttribute("type") != null) {
                    inprpass.setAttribute("type", "password");
                }
            } else if (el.classList.contains('cpss')) {
                inprcpass.toggleAttribute("type");
                el.classList.toggle("fa-eye-slash");
                if (inprcpass.getAttribute("type") != null) {
                    inprcpass.setAttribute("type", "password");
                }
            } else if (el.classList.contains('apss')) {
                inprapass.toggleAttribute("type");
                el.classList.toggle("fa-eye-slash");
                if (inprapass.getAttribute("type") != null) {
                    inprapass.setAttribute("type", "password");
                }
            }

        });
    });
    obj.ConfirmacionSucces = () => {
        Swal.fire({
            title: "Exitoso",
            text: "Se han realizado los cambios.",
            icon: "success"
        });
        setTimeout(() => {
            location.reload()
        },1000);
    }
    obj.ConfirmacionError = () => {
        Swal.fire({
            title: "Cancelado",
            text: "No sé realizo ningun cambio.",
            icon: "error"
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
                Swal.fire({ title: "Guardado!", icon: "success" });
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
                Swal.fire({ title: "Eliminado!", icon: "success" });
                obj.SendDirecciones("delete", { id: id })
            }
        });
    }

    obj.sendProfile = (opc) => {
        $http({
            method: 'POST',
            url: urlProfile,
            data: { profile: { opc: opc, tipo: obj.pag, data: obj.profile } }
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                toastr.success(res.data.mensaje);
                obj.ConfirmacionSucces();
            } else {
                toastr.error(res.data.mensaje);
                obj.ConfirmacionError();
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
                if (!localStorage.getItem("iduser")) {
                    localStorage.setItem("iduser", obj.profile._id)
                }
            } else {
                toastr.error("Error: ");
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizo la conexion con el servidor");
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
                        }, 500);
                        break;
                    case 'delete':
                    case 'set':
                        toastr.success(res.data.mensaje);
                        obj.getDatosCliente();
                        break;
                    case 'edit':
                        obj.dataDireccion = res.data.Data;
                        break;
                }


            } else {
                toastr.error(res.data.mensaje);
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizo la conexion con el servidor");
        });
    }

    obj.btneditDomicilio = (id) => {
        obj.btnMenulinks("Direcciones_edit");
        localStorage.setItem("_id_domicilio", id);
    }

    obj.btnPredeterminado = (id) => {
        Swal.fire({
            title: "¿Deseas poner este domicilio como predeterminado?",
            showDenyButton: true,
            confirmButtonText: "Predeterminar",
            denyButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: "Guardado!", icon: "success" });
                obj.SendDirecciones("set", { id_domicilio: id, id: localStorage.getItem("id") })
            }
        });
    }

    /* Termina modulo de direcciones */

    /* Inicia modulo de Datos de Facturacion */
    obj.addFacturacion = () => {
        location.href = "/Profile/Facturacion_add";
        localStorage.setItem("pag", "Facturacion_add")
    }

    obj.btnEditadatosFacturacion = (id) => {
        localStorage.setItem("id_rfc", id)
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
                Swal.fire({ title: "Guardado!", icon: "success" });
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
                Swal.fire({ title: "Eliminado!", icon: "success" });
                obj.sendFacturacion(opc, { _id: id })
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
                Swal.fire({ title: "Cambios Guardados!", icon: "success" });
                obj.sendFacturacion(opc, obj.Facturacion.dataFacturacion);
            } else if (result.isDenied) {
                Swal.fire("Cambios No Guardados!", "", "error");
            }
        });
    }

    obj.btnFacpredetermiando = (id, opc) => {
        Swal.fire({
            title: "¿Deseas Predeterminar Estos Datos?",
            showDenyButton: true,
            confirmButtonText: "Predeterminar",
            denyButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: "Datos Predeterminados!", icon: "success" });
                obj.sendFacturacion(opc, { _id: id });
            }
        });
    }
    /* Finaliza modulo de Datos de Facturacion */

    obj.btnNext = () => {
        obj.paginador2.page += obj.paginador2.limit
        let params = { opc: "history", idCliente: localStorage.getItem("iduser"), page: obj.paginador2.page, limit: obj.paginador2.limit }

    }

    obj.bntPrevios = () => {
        obj.paginador2.page -= obj.paginador2.limit
        if (obj.paginador2.page < 0) {
            obj.paginador2.page = 0
        }
        let params = { opc: "history", idCliente: localStorage.getItem("iduser"), page: obj.paginador2.page, limit: obj.paginador2.limit }

    }
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
                obj.dataDireccion.id = localStorage.getItem("iduser");
                break;
            case 'Direcciones_edit':
                obj.dataDireccion.id_domicilio = localStorage.getItem('_id_domicilio');
                obj.SendDirecciones('edit', obj.dataDireccion)
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
                console.log("Entro", localStorage.getItem("id_rfc"));
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