var urlModel = "./modulo/ProcesoCompra/ProcesoCompra.php";

tsuruVolks.controller('ProcesoCompraCtrl', ProcesoCompraCtrl);
(function () {
    if ($_SESSION["iduser"] == null){
        window.location.href = '/';
    }
})()

function ProcesoCompraCtrl($scope, $http, $location) {
    var obj = $scope;
    obj.formularioPC = {};
    obj.cliente = {};
    obj.domicilios = [];
    obj.metodosPago = [
        {nombre: 'Deposito/Transferencia Bancaria', value: 'Deposito'},
        {nombre: 'Tarjeta de Crédito / Débito', value: 'Tarjeta'}
    ];
    obj.step = 'principal';

    obj.init = function() {
        $http.post(urlModel, { Costumer: { opc: "getC" } }).then(function(res) {
            if (res.data.Bandera == 1) {
                obj.cliente = res.data.Data;
                obj.formularioPC.profile = {
                    id: obj.cliente._id,
                    correo: obj.cliente.correo
                };
                // Inicializar datos de envío por defecto
                obj.formularioPC.Codigo_postal = obj.cliente.Codigo_postal;
                obj.formularioPC.Colonia = obj.cliente.Colonia;
                obj.formularioPC.Domicilio = obj.cliente.Domicilio;
                obj.formularioPC.ciudad = obj.cliente.ciudad;
                obj.formularioPC.estado = obj.cliente.estado;
                
                if($_SESSION["CarritoPrueba"]){
                    obj.datacarrito = $_SESSION["CarritoPrueba"];
                    obj.calcularSubtotal();
                }
            }
        });
    };

    obj.calcularSubtotal = function() {
        obj.subtotal = 0;
        angular.forEach(obj.datacarrito, function(prod) {
            obj.subtotal += (prod.Cantidad * prod.Precio);
        });
        obj.formularioPC.Subtotal = obj.subtotal;
        obj.recalcularTotal();
    };

    obj.recalcularTotal = function() {
        obj.total = obj.subtotal;
        if(obj.formularioPC.Cenvio && obj.formularioPC.Cenvio.Costo) {
            obj.total += parseFloat(obj.formularioPC.Cenvio.Costo);
        }
        if(obj.formularioPC.descuento) {
            obj.total -= parseFloat(obj.formularioPC.descuento);
        }
        obj.formularioPC.Importe = obj.total;
    };

    // --- PROCESAR PAGO FINAL ---
    obj.procesarPago = function(form) {
        if(!form.$valid) { toastr.warning("Por favor rellena los campos obligatorios"); return; }
        if(!obj.formularioPC.Cenvio) { toastr.warning("Selecciona una opción de envío"); return; }
        if(!obj.formularioPC.metodoPago) { toastr.warning("Selecciona un método de pago"); return; }

        toastr.info("Procesando pedido, espera un momento...");
        obj.formularioPC.opc = 'buy2';
        
        $http.post(urlModel, { Costumer: obj.formularioPC }).then(function(res) {
            if (res.data.Bandera == 1) {
                // Éxito Depósito
                if (obj.formularioPC.metodoPago == 'Deposito') {
                    obj.setLock('efectivo');
                    window.location.href = '/ProcesoCompra/paso3';
                } 
                // Éxito Tarjeta: Redirigir a MIT
                else if (obj.formularioPC.metodoPago == 'Tarjeta') {
                    toastr.success("Pedido generado. Redirigiendo a pasarela de pago segura...");
                    obj.setLock('tarjeta');
                    window.location.href = res.data.data;
                }
            } else {
                toastr.error(res.data.mensaje);
            }
        });
    };

    // Función centralizada para bloquear vista (reemplaza Model.php)
    obj.setLock = function(tipoPago) {
        $http.post(urlModel, { modelo: { opc: tipoPago } }).then(function(res) {
            if (res.data.Bandera == 1) {
                console.log("Vista bloqueada satisfactoriamente para: " + tipoPago);
            }
        });
    };

    // ==========================================
    // MOTOR DE CONFETI
    // ==========================================
    const initConfetti = () => {
        const canvas = document.getElementById('canvas');
        if(!canvas) return; 

        const button = document.getElementById('button');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        let cx = ctx.canvas.width / 2;
        let cy = ctx.canvas.height / 2;

        let confetti = [];
        const confettiCount = 300;
        const gravityConfetti = 0.3;
        const dragConfetti = 0.075;
        const terminalVelocity = 3;

        const colors = [
            { front: '#7b5cff', back: '#6245e0' },
            { front: '#b3c7ff', back: '#8fa5e5' },
            { front: '#5c86ff', back: '#345dd1' },
            { front: '#FFFF33', back: '#FFCC00' },
            { front: '#FF33FF', back: '#CC33FF' }
        ];
        
        const randomRange = (min, max) => Math.random() * (max - min) + min;

        const initConfettoVelocity = (xRange, yRange) => {
            const x = randomRange(xRange[0], xRange[1]);
            const range = yRange[1] - yRange[0] + 1;
            let y = yRange[1] - Math.abs(randomRange(0, range) + randomRange(0, range) - range);
            if (y >= yRange[1] - 1) {
                y += (Math.random() < .25) ? randomRange(1, 3) : 0;
            }
            return { x: x, y: -y };
        }

        function Confetto() {
            this.randomModifier = randomRange(0, 99);
            this.color = colors[Math.floor(randomRange(0, colors.length))];
            this.dimensions = { x: randomRange(5, 9), y: randomRange(8, 15) };
            this.position = { x: randomRange(canvas.width - button.offsetWidth / 2, canvas.width / 12), y: randomRange(canvas.height + button.offsetHeight / 2, canvas.height / 8) };
            this.rotation = randomRange(0, 2 * Math.PI);
            this.scale = { x: 1, y: 1 };
            this.velocity = initConfettoVelocity([-9, 9], [6, 11]);
        }

        Confetto.prototype.update = function () {
            this.velocity.x -= this.velocity.x * dragConfetti;
            this.velocity.y = Math.min(this.velocity.y + gravityConfetti, terminalVelocity);
            this.velocity.x += Math.random() > 0.5 ? Math.random() : -Math.random();
            this.position.x += this.velocity.x;
            this.position.y += this.velocity.y;
            this.scale.y = Math.cos((this.position.y + this.randomModifier) * 0.09);
        }

        window.initBurst = () => {
            for (let i = 0; i < confettiCount; i++) {
                confetti.push(new Confetto());
            }
        }

        const render = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            confetti.forEach((confetto, index) => {
                let width = (confetto.dimensions.x * confetto.scale.x);
                let height = (confetto.dimensions.y * confetto.scale.y);
                ctx.translate(confetto.position.x, confetto.position.y);
                ctx.rotate(confetto.rotation);
                confetto.update();
                ctx.fillStyle = confetto.scale.y > 0 ? confetto.color.front : confetto.color.back;
                ctx.fillRect(-width / 2, -height / 2, width, height);
                ctx.setTransform(1, 0, 0, 1, 0, 0);
            })
            confetti.forEach((confetto, index) => {
                if (confetto.position.y >= canvas.height) confetti.splice(index, 1);
            });
            window.requestAnimationFrame(render);
        }

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            cx = ctx.canvas.width / 2;
            cy = ctx.canvas.height / 2;
        });

        render();
    }

    // ==========================================
    // INICIALIZACIÓN
    // ==========================================
    angular.element(document).ready(function () {
        initConfetti();
        const currentUrl = $location.absUrl();

        if(window.location.pathname === "/ProcesoCompra" || window.location.pathname === "/ProcesoCompra/") {
            obj.init();
        }

        if (currentUrl.includes("/ProcesoCompra/paso3")) {
            if($_SESSION["padlock"] != "lock"){
                setTimeout(() => { if(window.initBurst) window.initBurst(); }, 1500);
            }
        }
        
        if (currentUrl.includes("/ProcesoCompra/cc")) {
            if($_SESSION["padlock"] != "lock"){
                setTimeout(() => { 
                    let tarjetaExito = document.querySelector('.tarjeta-respuesta--exito');
        
                    if(tarjetaExito && window.initBurst) {
                        console.log("Pago Aprobado - Lanzando Confeti");
                        window.initBurst(); 
                    } else {
                        console.log("Pago Declinado - Sin Confeti");
                    }
                }, 800); 
            }
        }
    });
}