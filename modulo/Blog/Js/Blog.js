var url_Blog = "/modulo/Blog/Ajax/Blog.php";

tsuruVolks
    .controller('BlogCtrl', ["$scope", "$http", BlogCtrl])
    .controller('BlogDetallesCtrl', ["$scope", "$http", BlogDetallesCtrl])
    .filter('bypass', ['$sce', ($sce) => {
        return function (html) {
            return html ? $sce.trustAsHtml(html) : '';
        };
    }]);

function BlogCtrl($scope, $http) {
    let obj = $scope;
    obj.skip = 0;
    obj.limit = 6;
    obj.entradas = [];

    obj.btnBlodDetalles = (id) => {
        window.location.href = "/Blog/detalles/" + id;
    }

    obj.getEntradas = (opc = "get", skip = 0, limit = 6) => {
        $http({
            method: 'POST',
            url: url_Blog,
            data: { opc: opc, skip: skip, limit: limit },
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                obj.entradas = res.data.Data;
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizó la conexión con el servidor");
        });
    }

    angular.element(document).ready(function () {
        obj.getEntradas("get", obj.skip, obj.limit);
    });
}

function BlogDetallesCtrl($scope, $http) {
    let obj = $scope;
    obj.id;
    obj.entrada = {};
    obj.posts = [];

    obj.getOneEntradas = (opc = "getOne", id) => {
        $http({
            method: 'POST',
            url: url_Blog,
            data: { opc: opc, id: id },
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                obj.entrada = res.data.Data;
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizó la conexión con el servidor");
        });
    }

    obj.btnBlodDetalles = (id) => {
        window.location.href = "/Blog/detalles/" + id;
    }

    obj.getPost = (opc, id, x, y) => {
        $http({
            method: 'POST',
            url: url_Blog,
            data: { opc: opc, id: id, skip: x, limit: y },
        }).then(function successCallback(res) {
            if (res.data.Bandera == 1) {
                obj.posts = res.data.Data;
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se realizó la conexión con el servidor");
        });
    }

    angular.element(document).ready(function () {
        if(obj.id) {
            obj.getOneEntradas("getOne", obj.id);
            obj.getPost("getPost", obj.id, 0, 3);
        }
    });
}