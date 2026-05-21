<?php
require_once "tv-admin/asset/Clases/dbconectar.php";
require_once "tv-admin/asset/Clases/ConexionMySQL.php";

date_default_timezone_set('America/Mexico_City');

$conn = new HelperMySql($array_principal["server"], $array_principal["user"], $array_principal["pass"], $array_principal["db"]);

$estado_pagina = 'valido';
$mensaje_bloqueo = '';
$data = null;

if(!isset($_GET['token'])){
    $estado_pagina = 'invalido';
    $mensaje_bloqueo = 'No se proporcionó un token de seguridad válido.';
} else {
    $token = addslashes($_GET['token']);
    $sql = "SELECT * FROM password_resets WHERE token = '$token' AND used = 0 LIMIT 1";
    $result = $conn->query($sql);
    $data = $conn->fetch($result);

    if(!$data){
        $estado_pagina = 'invalido';
        $mensaje_bloqueo = 'Este enlace es inválido o ya ha sido utilizado anteriormente.';
    } elseif(strtotime($data["expires_at"]) < time()){
        $estado_pagina = 'expirado';
        $mensaje_bloqueo = 'Por seguridad, este enlace ha expirado (límite de 15 minutos).';
    }
}

$exito = false;
if($_SERVER["REQUEST_METHOD"] === "POST" && $estado_pagina === 'valido'){
    if(strlen($_POST["password"]) < 8){
        $error = "La contraseña debe tener mínimo 8 caracteres.";
    } elseif($_POST["password"] !== $_POST["password_confirm"]){
        $error = "Las contraseñas no coinciden.";
    } else {
        $nuevoHash = password_hash($_POST["password"], PASSWORD_DEFAULT);

        // Actualizar contraseña
        $updateUser = "UPDATE Cseguridad SET password = '$nuevoHash' WHERE _id = '{$data["user_id"]}'";
        $conn->query($updateUser);

        // Quemar el token
        $updateToken = "UPDATE password_resets SET used = 1 WHERE id = '{$data["id"]}'";
        $conn->query($updateToken);
        
        // Limpiar intentos fallidos
        $sqlUsername = "SELECT username FROM Cseguridad WHERE _id = '{$data["user_id"]}' LIMIT 1";
        $resUser = $conn->query($sqlUsername);
        $userData = $conn->fetch($resUser);

        if ($userData && !empty($userData['username'])) {
            $username = addslashes($userData['username']);
            $deleteIntentos = "DELETE FROM login_intentos WHERE username = '$username'";
            $conn->query($deleteIntentos);
        }
        
        $exito = true;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Restablecer contraseña | Macrom Autopartes</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --primary-red: #de0007;
    --primary-dark: #a50004;
    --success: #27ae60;
    --warning: #f1c40f;
    --error: #e74c3c;
    --bg: #fff;
    --bg-page: #f8f8f8;
    --input-border: #ccc;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--bg-page);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: clamp(10px, 2vw, 20px);
}

.reset-container {
    background: var(--bg);
    padding: clamp(20px, 5vw, 40px);
    border-radius: clamp(12px, 2vw, 16px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.12);
    width: 100%;
    max-width: 400px;
    text-align: center;
    transition: transform 0.3s ease;
}

.logo-mascota { display: flex; justify-content: center; align-items: center; gap: clamp(10px, 3vw, 15px); margin-bottom: clamp(20px, 5vw, 25px); }
.logo-mascota img { height: clamp(40px, 8vw, 50px); object-fit: contain; border-radius: 8px; }
h2 { color: var(--primary-red); margin-bottom: clamp(20px, 5vw, 30px); font-weight: 600; font-size: clamp(1.3rem, 4vw, 1.6rem); }

.error-icon { font-size: 60px; color: var(--error); margin-bottom: 20px; }
.warning-icon { font-size: 60px; color: var(--warning); margin-bottom: 20px; }
.btn-back { display: inline-block; background-color: var(--primary-red); color: #fff; padding: 12px 25px; border-radius: 8px; margin-top: 20px; text-decoration: none; font-weight: 600; transition: background 0.3s; }
.btn-back:hover { background-color: var(--primary-dark); }
.error-text { color: #555; font-size: 15px; line-height: 1.5; margin-bottom: 10px; }

.password-toggle { position: relative; margin-bottom: clamp(12px, 3vw, 20px); }
.password-toggle input[type="password"], .password-toggle input[type="text"] { width: 100%; padding: clamp(12px, 3vw, 14px) clamp(40px, 8vw, 45px) clamp(12px, 3vw, 14px) clamp(12px, 3vw, 15px); border: 1px solid var(--input-border); border-radius: clamp(8px, 2vw, 8px); font-size: clamp(14px, 3vw, 16px); transition: border-color 0.3s, box-shadow 0.3s; }
.password-toggle input:focus { border-color: var(--primary-red); box-shadow: 0 0 5px rgba(222,0,7,0.3); outline: none; }
.password-toggle .toggle-eye { position: absolute; right: clamp(8px, 2vw, 10px); top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--primary-red); font-size: clamp(16px, 4vw, 18px); z-index: 2; }
.password-toggle .validation-icon { position: absolute; right: clamp(30px, 6vw, 35px); top: 50%; transform: translateY(-50%); font-size: clamp(14px, 3vw, 16px); z-index: 1; }

button[type="submit"] { background-color: var(--primary-red); color: #fff; padding: clamp(12px, 3vw, 14px) 0; border: none; border-radius: clamp(8px, 2vw, 8px); font-size: clamp(15px, 4vw, 16px); cursor: pointer; width: 100%; transition: background 0.3s, transform 0.2s; }
button[type="submit"]:hover { background-color: var(--primary-dark); transform: translateY(-2px); }

.error { color: var(--error); margin-bottom: clamp(10px,2vw,15px); font-weight:500; animation: fadeIn 0.5s; }
.strength { height: clamp(8px, 2vw, 10px); width: 100%; border-radius: clamp(6px,2vw,8px); margin-top: 5px; margin-bottom: clamp(10px,2vw,15px); background: #eee; overflow: hidden; }
.strength-bar { height: 100%; width: 0; border-radius: clamp(6px,2vw,8px); transition: width 0.4s ease, background 0.4s ease; }
.match-status { font-size: clamp(12px, 3vw, 14px); margin-top: 5px; height: 18px; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 5px; }
.match-yes { color: var(--success); }
.match-no { color: var(--error); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>
<div class="reset-container">

    <div class="logo-mascota">
        <img src="https://macromautopartes.com/images/icons/logo%20original.svg" alt="Logo Macrom">
        <img src="https://macromautopartes.com/images/usuarios/Avatar-Lobo-Macrom-Grande.webp" alt="Mascota Macrom">
    </div>

    <?php if($estado_pagina !== 'valido'): ?>
        
        <?php if($estado_pagina === 'expirado'): ?>
            <i class="fas fa-clock warning-icon"></i>
            <h2>Enlace Expirado</h2>
        <?php else: ?>
            <i class="fas fa-times-circle error-icon"></i>
            <h2>Enlace Inválido</h2>
        <?php endif; ?>
        
        <p class="error-text"><?php echo $mensaje_bloqueo; ?></p>
        <a href="https://macromautopartes.com//login" class="btn-back">Volver al inicio de sesión</a>

    <?php else: ?>
        
        <h2>Restablecer contraseña</h2>
        <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>

        <form method="POST">
            <div class="password-toggle">
                <input type="password" name="password" id="password" placeholder="Nueva contraseña" required>
                <button type="button" class="toggle-eye" onclick="togglePassword('password', this)">
                    <i class="fas fa-eye"></i>
                </button>
                <span id="icon-password" class="validation-icon"></span>
            </div>
            <div class="strength">
                <div id="strength-bar" class="strength-bar"></div>
            </div>

            <div class="password-toggle">
                <input type="password" name="password_confirm" id="password_confirm" placeholder="Confirmar contraseña" required>
                <button type="button" class="toggle-eye" onclick="togglePassword('password_confirm', this)">
                    <i class="fas fa-eye"></i>
                </button>
                <span id="icon-confirm" class="validation-icon"></span>
            </div>
            <div id="match-status" class="match-status"></div>

            <button type="submit">Actualizar contraseña</button>
        </form>
    <?php endif; ?>

</div>

<?php if($exito): ?>
<script>
    Swal.fire({
        title: '¡Contraseña Actualizada!',
        text: 'Tu contraseña se ha restablecido correctamente. Ya puedes iniciar sesión.',
        icon: 'success',
        confirmButtonColor: '#de0007',
        confirmButtonText: 'Ir a Iniciar Sesión',
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'https://macromautopartes.com/login';
        }
    });
</script>
<?php endif; ?>

<script>
function togglePassword(id, btn){
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if(input.type === "password"){
        input.type = "text";
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = "password";
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

const passwordInput = document.getElementById('password');
const strengthBar = document.getElementById('strength-bar');
const passwordConfirm = document.getElementById('password_confirm');
const matchStatus = document.getElementById('match-status');
const iconPassword = document.getElementById('icon-password');
const iconConfirm = document.getElementById('icon-confirm');

if(passwordInput && passwordConfirm) {
    passwordInput.addEventListener('input', () => { updateStrength(); checkMatch(); });
    passwordConfirm.addEventListener('input', checkMatch);
}

function updateStrength(){
    const val = passwordInput.value;
    let strength = 0;
    if(val.length >= 8) strength++;
    if(val.match(/[a-z]/) && val.match(/[A-Z]/)) strength++;
    if(val.match(/[0-9]/)) strength++;
    if(val.match(/[\W]/)) strength++;

    switch(strength){
        case 0:
        case 1:
            strengthBar.style.width = '25%'; strengthBar.style.background = 'var(--error)';
            iconPassword.textContent = '❌'; iconPassword.style.color = 'var(--error)'; break;
        case 2:
            strengthBar.style.width = '50%'; strengthBar.style.background = 'var(--warning)';
            iconPassword.textContent = '⚠️'; iconPassword.style.color = 'var(--warning)'; break;
        case 3:
        case 4:
            strengthBar.style.width = strength === 3 ? '75%' : '100%'; strengthBar.style.background = 'var(--success)';
            iconPassword.textContent = '✔️'; iconPassword.style.color = 'var(--success)'; break;
    }
}

function checkMatch(){
    if(passwordConfirm.value.length === 0){ matchStatus.textContent = ''; iconConfirm.textContent = ''; return; }
    if(passwordInput.value === passwordConfirm.value){
        matchStatus.textContent = 'Las contraseñas coinciden'; matchStatus.className = 'match-status match-yes';
        iconConfirm.textContent = '✔️'; iconConfirm.style.color = 'var(--success)';
    } else {
        matchStatus.textContent = 'Las contraseñas no coinciden'; matchStatus.className = 'match-status match-no';
        iconConfirm.textContent = '❌'; iconConfirm.style.color = 'var(--error)';
    }
}
</script>
</body>
</html>