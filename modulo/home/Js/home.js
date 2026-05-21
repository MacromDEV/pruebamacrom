var url = "./modulo/home/Ajax/home.php";

tsuruVolks.controller('homeCtrl', homeCtrl);
if ($_SESSION.iduser == null) {
    localStorage.clear();
}

function homeCtrl($scope, $http) {
    var obj = $scope;
    obj.Data = {};
    obj.productos = [];
    obj.promociones;

    // ==========================================
    // CONTROL DE PESTAÑAS
    // ==========================================
    obj.tabActiva = 'masVendidos';

    obj.cambiarTab = function(tab) {
        obj.tabActiva = tab;
    };

    // ==========================================
    // PROCESAMIENTO DE TEXTOS Y URLS
    // ==========================================
    obj.eachRefacciones = (array) => {
        if(!array) return;
        array.forEach(e => {
            e.NewUrlName = e["Producto"].replaceAll(" ","-");
            e.NewUrlName = e.NewUrlName.replaceAll(",","");
            e.NewUrlName = e.NewUrlName.normalize('NFD').replace(/[\u0300-\u036f]/g,"");
            e.NewAltName = e["Producto"].replaceAll(",","");
            if(e.stock == 0){
                e.agotado = true;
            }
        });
    }

    // ==========================================
    // OBTENCIÓN DE DATOS
    // ==========================================
    obj.getCategorias = async () => {
        $http({
            method: 'POST',
            url: url,
            data: { modelo: { opc: "buscar", tipo: "Categorias", home: true } },
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                obj.Data = res.data.Data;
                obj.eachRefacciones(obj.Data.masVendidos);
                obj.eachRefacciones(obj.Data.liquidacion);
                obj.eachRefacciones(obj.Data.nuevos); 
            } else {
                toastr.error("Error: Condición Incumplida");
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizo la conexion con el servidor");
        });
    }

    obj.getImagen = (e) => {
        return "images/Categorias/" + e._id + ".png";
    }

    obj.RefaccionDetalles = (_id) => {
        window.open("/catalogo/detalles/" + _id, "_self");
    }

    // ==========================================
    // INICIALIZACIÓN Y CARRUSEL
    // ==========================================
    angular.element(document).ready(function () {
        obj.getCategorias();

        setTimeout(() => {
            $('.slick2').slick({
                arrows: true,
                dots:true,
                infinite: true,
                autoplay:true,
                autoplaySpeed: 5000,
                slidesToShow: 1,
                adaptiveHeight: true,
                responsive: [
                    {
                        breakpoint: 1500,
                        settings: {
                            arrows: false,
                            cssEase: 'linear'
                        }
                    }
                ]
            });
        }, 500);

        $('.slick2').on('wheel', (function(e){
            e.preventDefault();
            if(e.originalEvent.deltaY < 0){
                $(this).slick('slickPrev');
            } else {
                $(this).slick('slickNext');
            }
        }));
    });
}