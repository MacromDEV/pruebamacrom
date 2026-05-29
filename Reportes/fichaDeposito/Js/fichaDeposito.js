'use strict'

var urlfichadeposito = "Ajax/FichaDeposito.php";
var RptsuruVolks = angular.module('RptsuruVolks',[])

RptsuruVolks.controller('FichaDepositoCtrl', ["$scope","$http", FichaDepositoCtrl]);

function FichaDepositoCtrl($scope,$http){
    var obj = $scope;
    obj.fichaData = {};

    obj.sendData = () =>{
        $http.post(urlfichadeposito, {}).then(function successCallback(res) {
            if(res.data.Bandera == 1){
                obj.fichaData = res.data.Data;
            }else{
                toastr.error(res.data.mensaje);
                setTimeout(() => { location.href = "../../home"; }, 2500);
            }
        }, function errorCallback(res) {
            toastr.error("Error: no se conectó con el servidor");
        });
    }

    angular.element(document).ready(function () {
        obj.sendData();
    });
}