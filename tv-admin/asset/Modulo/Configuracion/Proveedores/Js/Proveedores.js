'use strict'

var urlProveedores = "./Modulo/Configuracion/Proveedores/Ajax/Proveedores.php";

if (typeof window.Toast === 'undefined') {
    window.Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 3000, timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
}

var confirmarAccionProveedor = (titulo, texto, icono, btnColor, btnText, accion) => {
    Swal.fire({
        title: titulo, text: texto, icon: icono,
        showCancelButton: true, confirmButtonColor: btnColor, cancelButtonColor: '#6c757d',
        confirmButtonText: btnText, cancelButtonText: 'Cancelar', reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) accion();
    });
};

tsuruVolks.controller('ProveedoresCrtl', ["$scope", "$http", ProveedoresCrtl]);

function ProveedoresCrtl($scope, $http) {
    var obj = $scope;
    obj.noProveedores = 0;
    obj.Proveedor = {};
    obj.dataProveedor = [];
    obj.nuevo = true;
    obj.btnsave = false;
    obj.historico = false;
    obj.img = "Images/boxed-bg.jpg";
    obj.paginador = { page: 0, limit: "10" };
    obj.txtfind = "";

    obj.getImagen = (foto, id) => {
        return foto ? obj.dominio + "/images/Marcasrefacciones/" + id + ".png" : "Images/boxed-bg.jpg";
    };

    obj.btnAumentar = () => {
        let currentPage = parseInt(obj.paginador.page, 10);
        let currentLimit = parseInt(obj.paginador.limit, 10);
        if ((currentPage + currentLimit) < obj.noProveedores) {
            obj.paginador.page = currentPage + currentLimit;
            obj.getProvedores();
        }
    };

    obj.btnDisminuir = () => {
        let currentPage = parseInt(obj.paginador.page, 10);
        let currentLimit = parseInt(obj.paginador.limit, 10);
        obj.paginador.page = currentPage - currentLimit;
        if (obj.paginador.page < 0) obj.paginador.page = 0;
        obj.getProvedores();
    };

    obj.getProvedores = (skip = null, limit = null) => {
        let _skip = skip !== null ? skip : parseInt(obj.paginador.page, 10);
        let _limit = limit !== null ? limit : parseInt(obj.paginador.limit, 10);

        setTimeout(() => {
            $http({
                method: 'POST', url: urlProveedores,  
                data: { Proveedor: { 
                    opc: "buscar", 
                    find: obj.txtfind, 
                    historico: obj.historico ? 0 : 1,
                    skip: _skip, 
                    limit: _limit 
                } },
                headers: { 'Content-Type': undefined },
                transformRequest: function (data) {
                    var formData = new FormData();
                    for (var m in data.Proveedor) formData.append(m, data.Proveedor[m]);
                    return formData;
                }
            }).then(function (res) {
                if (res.data.Bandera == 1) {
                    obj.noProveedores = res.data.Data.noRegistros;
                    obj.dataProveedor = res.data.Data.Registros;
                    obj.dominio = res.data.dominio;
                } else {
                    Toast.fire({ icon: 'error', title: res.data.mensaje });
                }
            }, () => Toast.fire({ icon: 'error', title: 'Error de conexión con el servidor' }));
        }, 100);
    };

    obj.btnNuevo = () => {
        obj.nuevo = true;
        obj.Proveedor = { opc: "new", tag_title: "", tag_alt: "" };
        delete obj.Proveedor.file;
        obj.img = "Images/boxed-bg.jpg";
        document.getElementById("txtfile").value = "";
        $('.custom-file-label').html('Seleccionar imagen...');
        $("#mProveedor").modal("show");
    };

    obj.btnCrearProveedor = () => {
        if(!obj.Proveedor.Proveedor || obj.Proveedor.Proveedor.trim() === '') {
            Toast.fire({ icon: 'warning', title: 'El nombre del proveedor es obligatorio' });
            return;
        }
        obj.btnsave = true;
        $http({
            method: 'POST', url: urlProveedores,  
            data: { Proveedor: obj.Proveedor },
            headers: { 'Content-Type': undefined },
            transformRequest: function (data) {
                var formData = new FormData();
                for (var m in data.Proveedor) formData.append(m, data.Proveedor[m]);
                return formData;
            }
        }).then(function (res) {
            obj.btnsave = false;
            if (res.data.Bandera == 1) {
                Toast.fire({ icon: 'success', title: res.data.mensaje });
                obj.getProvedores();
                $("#mProveedor").modal("hide");
            } else { Toast.fire({ icon: 'error', title: res.data.mensaje }); }
        }, function () {
            obj.btnsave = false;
            Toast.fire({ icon: 'error', title: 'Error de conexión' });
        });
    };

    obj.btnEditar = (proveedor) => {
        obj.nuevo = false;
        obj.Proveedor = angular.copy(proveedor);
        obj.Proveedor.opc = "edit";
        delete obj.Proveedor.file;
        obj.img = obj.Proveedor.foto ? obj.dominio + "/images/Marcasrefacciones/" + obj.Proveedor._id + ".png" : "Images/boxed-bg.jpg";
        document.getElementById("txtfile").value = "";
        $('.custom-file-label').html('Cambiar imagen...');
        $("#mProveedor").modal("show");
    };

    obj.btnEditarProveedor = () => {
        if(!obj.Proveedor.Proveedor || obj.Proveedor.Proveedor.trim() === '') {
            Toast.fire({ icon: 'warning', title: 'El nombre no puede quedar vacío' });
            return;
        }
        obj.btnsave = true;
        $http({
            method: 'POST', url: urlProveedores,  
            data: { Proveedor: obj.Proveedor },
            headers: { 'Content-Type': undefined },
            transformRequest: function (data) {
                var formData = new FormData();
                for (var m in data.Proveedor) formData.append(m, data.Proveedor[m]);
                return formData;
            }
        }).then(function (res) {
            obj.btnsave = false;
            if (res.data.Bandera == 1) {
                $("#mProveedor").modal("hide");
                obj.getProvedores();
                Toast.fire({ icon: 'success', title: res.data.mensaje });
            } else { Toast.fire({ icon: 'error', title: res.data.mensaje }); }
        }, function () {
            obj.btnsave = false;
            Toast.fire({ icon: 'error', title: 'Error de conexión' });
        });
    };

    obj.toggleEstatus = (modelo) => {
        let opcNueva = modelo.Estatus == 1 ? "disabled" : "enabled";
        let textoToast = modelo.Estatus == 1 ? "Proveedor desactivado" : "Proveedor activado";
        $http({
            method: 'POST', url: urlProveedores,  
            data: { Proveedor: { opc: opcNueva, _id: modelo._id } },
            headers: { 'Content-Type': undefined },
            transformRequest: function (data) {
                var formData = new FormData();
                for (var m in data.Proveedor) formData.append(m, data.Proveedor[m]);
                return formData;
            }
        }).then(function (res) {
            if (res.data.Bandera == 1) {
                modelo.Estatus = modelo.Estatus == 1 ? 0 : 1;
                Toast.fire({ icon: 'success', title: textoToast });
            } else { Toast.fire({ icon: 'error', title: res.data.mensaje }); }
        }, () => Toast.fire({ icon: 'error', title: 'Error de conexión' }));
    };

    obj.btnEliminar = (_id) => {
        confirmarAccionProveedor(
            '¿Eliminar Proveedor?',
            'Esta acción no se puede deshacer. Se borrará permanentemente.',
            'error', '#dc3545', '<i class="fas fa-trash-alt"></i> Sí, eliminar',
            () => {
                $http({
                    method: 'POST', url: urlProveedores,  
                    data: { Proveedor: { opc: "delete", _id: _id } },
                    headers: { 'Content-Type': undefined },
                    transformRequest: function (data) {
                        var formData = new FormData();
                        for (var m in data.Proveedor) formData.append(m, data.Proveedor[m]);
                        return formData;
                    }
                }).then(function (res) {
                    if (res.data.Bandera == 1) {
                        obj.getProvedores();
                        Toast.fire({ icon: 'success', title: res.data.mensaje });
                    } else { Toast.fire({ icon: 'error', title: res.data.mensaje }); }
                }, () => Toast.fire({ icon: 'error', title: 'Error de conexión' }));
            }
        );
    };

    angular.element(document).ready(function () {
        var fileInput1 = document.getElementById('txtfile');
        if(fileInput1){
            fileInput1.addEventListener('change', function (e) {
                var file = fileInput1.files[0];
                if (file) {
                    if (file.size <= 512000) {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            obj.img = reader.result;
                            obj.Proveedor.file = file; 
                            obj.$apply();
                        }
                        reader.readAsDataURL(file);
                        $(this).next('.custom-file-label').html(file.name); 
                    }else {
                        Toast.fire({ icon: 'warning', title: 'La imagen supera los 512 KB' });
                        this.value = "";
                        $(this).next('.custom-file-label').html('Seleccionar imagen...');
                    }
                }
            });
        }
        obj.getProvedores();
    });
}