'use strict';

var url = "./Modulo/Secciones/webprincipal/Ajax/webprincipal.php";
if (typeof window.Toast === 'undefined') {
    window.Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
}

var confirmarAccionWeb = (titulo, texto, icono, btnColor, btnText, accion) => {
    Swal.fire({
        title: titulo,
        text: texto,
        icon: icono,
        showCancelButton: true,
        confirmButtonColor: btnColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: btnText,
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) accion();
    });
};

tsuruVolks.controller('WebCtrl', ["$scope", "$http", "$timeout", WebCtrl]);

function WebCtrl($scope, $http, $timeout) {
    var obj = $scope;
    obj.imagen = { placeholder: "Agrega una imagen", Categoria: "", Estatus: 0, opc: "set", Disenio: "" };
    
    obj.databannerPrincipal = [];
    obj.catalogos = [];
    obj.compras = [];
    obj.nosotros = [];
    obj.carrousel = [];
    obj.dominio = "";

    obj.saveTab = (tabName) => {
        localStorage.setItem('TabActive', tabName);
    };

    if (localStorage.getItem("TabActive")) {
        let activeTab = localStorage.getItem("TabActive");
        $('#Tab' + activeTab).tab('show');
    } else {
        $('#TabPrincipal').tab('show');
    }

    const refreshSlick = () => {
        let $carousel = $('.slick2');
        if ($carousel.hasClass('slick-initialized')) {
            $carousel.slick('unslick');
        }
        $('.wrap-slick2').removeClass('slick-cargado');
        
        $timeout(() => {
            if($carousel.length) {
                $carousel.on('init', function() {
                    $('.wrap-slick2').addClass('slick-cargado');
                });
                $carousel.slick({
                    arrows: true,
                    prevArrow: '<button type="button" class="btn-carrusel-nav btn-carrusel-prev"><i class="fa fa-chevron-left fa-lg"></i></button>',
                    nextArrow: '<button type="button" class="btn-carrusel-nav btn-carrusel-next"><i class="fa fa-chevron-right fa-lg"></i></button>',
                    infinite: true,
                    autoplay: true,
                    autoplaySpeed: 3000,
                    slidesToShow: 1,
                    adaptiveHeight: false
                });
            }
        }, 300);
    };

    obj.btnsubirimagen = () => {
        let activePane = $('.tab-pane.active').attr('id');
        obj.imagen.Categoria = activePane;

        if (obj.imagen.file) {
            if (obj.imagen.Disenio) {
                obj.setImagenes(obj.imagen);
            } else {
                Toast.fire({ icon: 'warning', title: 'Selecciona si el banner es para Escritorio o Móvil.' });
            }
        } else {
            Toast.fire({ icon: 'warning', title: 'No has seleccionado ninguna imagen para subir.' });
        }
    };

    const executePost = (dataObj, successMsg) => {
        $http({
            method: 'POST',
            url: url,
            data: dataObj,
            headers: { 'Content-Type': undefined },
            transformRequest: function (data) {
                var formData = new FormData();
                for (var m in data.imagen) formData.append(m, data.imagen[m]);
                return formData;
            }
        }).then(function (res) {
            if (res.data.Bandera == 1) {
                Toast.fire({ icon: 'success', title: successMsg });
                obj.getImagenes({ opc: "get", Categoria: res.data.categoria, Estatus: 1 });
                
                if(dataObj.imagen.opc === "set"){
                    obj.imagen.file = null;
                    obj.imagen.name = null;
                    $('.custom-file-label').html('Seleccionar archivo de imagen...');
                    $('.archivos').val(''); 
                }

            } else {
                Toast.fire({ icon: 'error', title: res.data.mensaje });
            }
        }, function () {
            Toast.fire({ icon: 'error', title: 'Error de conexión con el servidor.' });
        });
    };

    obj.btnDesactivar = (id, categoria) => {
        confirmarAccionWeb(
            '¿Eliminar banner?',
            'Esta acción borrará la imagen permanentemente.',
            'error',
            '#dc3545',
            '<i class="fas fa-trash-alt"></i> Sí, eliminar',
            () => executePost({ imagen: { opc: "off", _id: id, Categoria: categoria } }, 'Imagen eliminada correctamente')
        );
    };

    obj.btnPausar = (id, categoria) => {
        confirmarAccionWeb(
            '¿Desactivar banner?',
            'El banner se mandará al historial y dejará de verse en la tienda.',
            'warning',
            '#ffc107',
            '<i class="fas fa-pause"></i> Sí, desactivar',
            () => executePost({ imagen: { opc: "pause", _id: id, Categoria: categoria } }, 'Banner desactivado')
        );
    };

    obj.btnDesimgcarrousel = (id, categoria) => {
        confirmarAccionWeb(
            '¿Quitar del carrousel?',
            'La imagen dejará de mostrarse en el inicio de la tienda.',
            'warning',
            '#ffc107',
            '<i class="fas fa-times"></i> Sí, quitar',
            () => executePost({ imagen: { opc: "offcarrousel", _id: id, Categoria: categoria } }, 'Imagen retirada del carrousel')
        );
    };

    obj.changePred = (id, categoria) => {
        confirmarAccionWeb(
            '¿Activar este banner?',
            'Reemplazará al banner que está actualmente en vivo para tus clientes.',
            'info',
            '#28a745',
            '<i class="fas fa-check"></i> Sí, activar',
            () => executePost({ imagen: { opc: "act", _id: id, Categoria: categoria } }, 'Banner actualizado y en vivo')
        );
    };

    obj.CarrouselPred = (id, categoria) => {
        executePost({ imagen: { opc: "carrouselPred", _id: id, Categoria: categoria } }, 'Imagen agregada al carrousel');
    };

    obj.setImagenes = (data) => {
        executePost({ imagen: data }, 'Imagen subida exitosamente');
    };

    const extractImageInfo = (targetObject, imgData) => {
        if (imgData && imgData.imagen) {
            let foto = new Image();
            foto.onload = function() {
                targetObject.width = this.width;
                targetObject.height = this.height;
                targetObject.formato = imgData.imagen.split('.').pop();
                obj.$apply(); 
            };
            foto.src = obj.dominio + "/images/Banners/" + imgData.imagen;
        }
    };

    obj.getImagenes = (data) => {
        $http({
            method: 'POST',
            url: url,
            data: { imagen: data },
            headers: { 'Content-Type': undefined },
            transformRequest: function (data) {
                var formData = new FormData();
                for (var m in data.imagen) formData.append(m, data.imagen[m]);
                return formData;
            }
        }).then(function (res) {
            if (res.data.Bandera == 1) {
                obj.dominio = res.data.dominio;
                
                let target = null;
                switch (res.data.categoria) {
                    case 'Principal': target = obj.databannerPrincipal = res.data.Data; obj.databannerPrincipal.disabled = res.data.Disabled; break;
                    case 'Catalogos': target = obj.catalogos = res.data.Data; obj.catalogos.disabled = res.data.Disabled; break;
                    case 'Compras': target = obj.compras = res.data.Data; obj.compras.disabled = res.data.Disabled; break;
                    case 'Nosotros': target = obj.nosotros = res.data.Data; obj.nosotros.disabled = res.data.Disabled; break;
                    case 'Carrousel': 
                        obj.carrousel = res.data.Data; 
                        obj.carrousel.disabled = res.data.Disabled; 
                        refreshSlick();
                        break;
                }

                if (target && target.Escritorio && target.Escritorio[0]) {
                    extractImageInfo(target.Escritorio[0], target.Escritorio[0]);
                }

            } else {
                Toast.fire({ icon: 'error', title: res.data.mensaje });
            }
        });
    };

    angular.element(document).ready(function () {
        $(".archivos").on("change", function (e) {
            var file = this.files[0];
            if (file) {
                if (file.size <= 1048576) { 
                    obj.imagen.name = file.name;
                    obj.$apply();
                    $(this).next('.custom-file-label').html(file.name);
                } else {
                    Toast.fire({ icon: 'warning', title: 'La imagen es muy pesada. Máximo 1MB.' });
                    this.value = ""; 
                    $(this).next('.custom-file-label').html('Seleccionar archivo de imagen...');
                }
            }
        });

        const categorias = ["Principal", "Catalogos", "Compras", "Nosotros", "Carrousel"];
        categorias.forEach(cat => obj.getImagenes({ opc: "get", Categoria: cat, Estatus: 1 }));
    });
}