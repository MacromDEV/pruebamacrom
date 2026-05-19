'use strict';

var urlCategorias = "./Modulo/Configuracion/Categorias/Ajax/Categorias.php";

if (typeof window.Toast === 'undefined') {
    window.Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
}

tsuruVolks.controller("CategoriasCrtl", ["$scope", "$http", CategoriasCrtl]);

function CategoriasCrtl($scope, $http) {
    var obj = $scope;
    obj.noCategorias = 0;
    obj.categorias = [];
    obj.categoria = {};
    obj.nuevo = true;
    obj.img = "Images/boxed-bg.jpg";
    obj.paginador = { page: 0, limit: "10" };
    obj.txtfind = "";

    const construirFormData = function(data) {
        var formData = new FormData();
        for (var m in data.categoria) formData.append(m, data.categoria[m]);
        var fileInput = document.getElementById('txtfile');
        if (fileInput && fileInput.files.length > 0) formData.append("file", fileInput.files[0]);
        return formData;
    };

    obj.btnAumentar = () => {
        let currentPage = parseInt(obj.paginador.page, 10);
        let currentLimit = parseInt(obj.paginador.limit, 10);
        if ((currentPage + currentLimit) < obj.noCategorias) {
            obj.paginador.page = currentPage + currentLimit;
            obj.getCategorias();
        }
    };

    obj.btnDisminuir = () => {
        let currentPage = parseInt(obj.paginador.page, 10);
        let currentLimit = parseInt(obj.paginador.limit, 10);
        obj.paginador.page = currentPage - currentLimit;
        if (obj.paginador.page < 0) obj.paginador.page = 0;
        obj.getCategorias();
    };

    obj.getCategorias = (skip = null, limit = null) => {
        let _skip = skip !== null ? skip : parseInt(obj.paginador.page, 10);
        let _limit = limit !== null ? limit : parseInt(obj.paginador.limit, 10);

        setTimeout(() => {
            $http({
                method: 'POST', url: urlCategorias,
                data: { categoria: { 
                    opc: "buscar", 
                    find: obj.txtfind, 
                    skip: _skip, 
                    limit: _limit,
                    historico: obj.historico ? 0 : 1 
                } },
                headers: { 'Content-Type': undefined },
                transformRequest: construirFormData
            }).then(function (res) {
                if (res.data.Bandera == 1) {
                    obj.noCategorias = res.data.Data.noRegistros;
                    obj.categorias = res.data.Data.Registros;
                } else { Toast.fire({ icon: 'error', title: res.data.mensaje }); }
            }, () => Toast.fire({ icon: 'error', title: 'Error de conexión' }));
        }, 100);
    };

    obj.btnNuevaCategoria = () => {
        obj.nuevo = true;
        obj.categoria = { opc: "new" };
        obj.img = "Images/boxed-bg.jpg";
        document.getElementById("txtfile").value = "";
        $('.custom-file-label').html('Seleccionar archivo...');
        $("#mcategoria").modal("show");
    };

    obj.btnCrearCategoria = () => {
        if (!obj.categoria.Categoria || obj.categoria.Categoria.trim() === "") {
            Toast.fire({ icon: 'warning', title: 'Ingresa el nombre de la categoría.' });
            return;
        }
        $http({
            method: 'POST', url: urlCategorias,
            data: { categoria: obj.categoria },
            headers: { 'Content-Type': undefined },
            transformRequest: construirFormData
        }).then(function (res) {
            if (res.data.Bandera == 1) {
                $("#mcategoria").modal("hide");
                obj.getCategorias();
                Toast.fire({ icon: 'success', title: res.data.mensaje });
            } else { Toast.fire({ icon: 'error', title: res.data.mensaje }); }
        });
    };

    obj.opcEditar = (categoria) => {
        obj.nuevo = false;
        obj.categoria = angular.copy(categoria);
        obj.categoria.opc = "edit";
        obj.img = obj.categoria.foto ? "../../images/Categorias/" + obj.categoria._id + ".png" : "Images/boxed-bg.jpg";
        document.getElementById("txtfile").value = "";
        $('.custom-file-label').html('Cambiar archivo...');
        $("#mcategoria").modal("show");
    };

    obj.btnEditarCategoria = () => {
        if (!obj.categoria.Categoria || obj.categoria.Categoria.trim() === "") {
            Toast.fire({ icon: 'warning', title: 'El nombre no puede quedar vacío.' });
            return; 
        }
        $http({
            method: 'POST', url: urlCategorias,
            data: { categoria: obj.categoria },
            headers: { 'Content-Type': undefined },
            transformRequest: construirFormData
        }).then(function (res) {
            if (res.data.Bandera == 1) {
                $("#mcategoria").modal("hide");
                obj.getCategorias();
                Toast.fire({ icon: 'success', title: res.data.mensaje });
            } else { Toast.fire({ icon: 'error', title: res.data.mensaje }); }
        });
    };

    obj.toggleStatus = (categoria) => {
        let nuevoOpc = categoria.Status == 1 ? "disabled" : "enabled";
        let msjTitulo = categoria.Status == 1 ? "¿Dar de BAJA?" : "¿ACTIVAR Categoría?";
        let msjTexto = categoria.Status == 1 ? `La categoría "${categoria.Categoria}" pasará al histórico.` : `La categoría "${categoria.Categoria}" volverá a estar visible.`;
        let icono = categoria.Status == 1 ? 'warning' : 'question';
        let colorBtn = categoria.Status == 1 ? '#dc3545' : '#28a745';

        Swal.fire({
            title: msjTitulo, text: msjTexto, icon: icono,
            showCancelButton: true, confirmButtonColor: colorBtn, cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $http({
                    method: 'POST', url: urlCategorias,
                    data: { categoria: { opc: nuevoOpc, _id: categoria._id, Categoria: categoria.Categoria } },
                    headers: { 'Content-Type': undefined },
                    transformRequest: construirFormData
                }).then(function (res) {
                    if (res.data.Bandera == 1) {
                        obj.getCategorias(); 
                        Toast.fire({ icon: 'success', title: res.data.mensaje });
                    } else { Toast.fire({ icon: 'error', title: res.data.mensaje }); }
                });
            }
        });
    };

    obj.opcEliminar = (_id) => {
        Swal.fire({
            title: '¿Eliminar Permanentemente?',
            html: "⚠️ ¡ADVERTENCIA!<br>Esta acción borrará la categoría y su imagen de forma definitiva.",
            icon: 'error',
            showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt"></i> Sí, ELIMINAR', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $http({
                    method: 'POST', url: urlCategorias,
                    data: { categoria: { opc: "delete", _id: _id } },
                    headers: { 'Content-Type': undefined },
                    transformRequest: construirFormData
                }).then(function (res) {
                    if (res.data.Bandera == 1) {
                        obj.getCategorias();
                        Toast.fire({ icon: 'success', title: res.data.mensaje });
                    } else { Toast.fire({ icon: 'error', title: res.data.mensaje }); }
                });
            }
        });
    };

    angular.element(document).ready(function () {
        var fileInput1 = document.getElementById('txtfile');
        if(fileInput1) {
            fileInput1.addEventListener('change', function (e) {
                var file = fileInput1.files[0];
                if (file) {
                    if (file.size <= 512000) {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            obj.img = reader.result;
                            obj.$apply();
                        }
                        reader.readAsDataURL(file);
                        $(this).next('.custom-file-label').html(file.name);
                    } else {
                        Toast.fire({ icon: 'warning', title: 'La Imagen supera los 512 KB' });
                        fileInput1.value = "";
                        $(this).next('.custom-file-label').html('Seleccionar archivo...');
                    }
                }
            });
        }
        obj.getCategorias();
    });
}