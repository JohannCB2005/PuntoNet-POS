<?php
session_start();

// Si ya hay sesión de cliente, no mostrar el formulario — seguir directo a donde correspondía ir.
if (isset($_SESSION['id_cliente'])) {
    $volver = $_GET['volver'] ?? '';
    header('Location: ' . ($volver === 'checkout' ? 'V_checkout.php' : '../../store.php'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$volver = htmlspecialchars($_GET['volver'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta — PuntoNet</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #1d4ed8;
            --primary-hover: #1e40af;
            --bg-main: #f0f4ff;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-main);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 16px;
        }
        .brand-link {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 24px;
        }
        .cuenta-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(29, 78, 216, 0.08);
            padding: 36px 32px;
            max-width: 440px;
            width: 100%;
        }
        .cuenta-card h4 { font-weight: 800; margin-bottom: 4px; }
        .cuenta-sub { color: var(--text-muted); font-size: 0.88rem; margin-bottom: 24px; }
        .step-section { display: none; }
        .step-section.active { display: block; }
        .form-label { font-weight: 600; font-size: 0.85rem; }
        .btn-primary-app {
            background: var(--primary);
            border: none;
            color: #fff;
            font-weight: 700;
            border-radius: 10px;
            padding: 11px;
            width: 100%;
        }
        .btn-primary-app:hover { background: var(--primary-hover); color: #fff; }
        .link-muted {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .link-muted:hover { color: var(--primary); }
        .code-input {
            letter-spacing: 6px;
            font-size: 1.4rem;
            font-weight: 700;
            text-align: center;
        }
    </style>
</head>
<body>

    <a href="../../store.php" class="brand-link">
        <img src="../../assets/logo.svg" height="32" alt="PuntoNet">
        PuntoNet
    </a>

    <div class="cuenta-card">

        <!-- ══ LOGIN ══ -->
        <div class="step-section active" id="stepLogin">
            <h4>Inicia sesión</h4>
            <p class="cuenta-sub">Ingresa a tu cuenta para comprar y ver tus pedidos.</p>
            <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <input type="email" id="loginEmail" class="form-control" placeholder="tucorreo@ejemplo.com">
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input type="password" id="loginPassword" class="form-control" placeholder="••••••••">
            </div>
            <button class="btn-primary-app mb-3" id="btnLogin">Ingresar</button>
            <div class="d-flex justify-content-between">
                <span class="link-muted" onclick="showStep('registro')">Crear cuenta</span>
                <span class="link-muted" onclick="showStep('forgot')">Olvidé mi contraseña</span>
            </div>
        </div>

        <!-- ══ REGISTRO ══ -->
        <div class="step-section" id="stepRegistro">
            <h4>Crea tu cuenta</h4>
            <p class="cuenta-sub">Con tu cuenta podrás ver el historial y estado de tus pedidos.</p>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label">DNI</label>
                    <input type="text" id="regDni" class="form-control" maxlength="8" placeholder="12345678">
                </div>
                <div class="col-6">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" id="regTelefono" class="form-control" maxlength="15" placeholder="987654321">
                </div>
                <div class="col-6">
                    <label class="form-label">Nombres</label>
                    <input type="text" id="regNombres" class="form-control" placeholder="Juan Carlos">
                </div>
                <div class="col-6">
                    <label class="form-label">Apellidos</label>
                    <input type="text" id="regApellidos" class="form-control" placeholder="Pérez García">
                </div>
                <div class="col-12">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" id="regEmail" class="form-control" placeholder="tucorreo@ejemplo.com">
                </div>
                <div class="col-6">
                    <label class="form-label">Contraseña</label>
                    <input type="password" id="regPassword" class="form-control" placeholder="••••••••">
                </div>
                <div class="col-6">
                    <label class="form-label">Confirmar</label>
                    <input type="password" id="regPasswordConfirm" class="form-control" placeholder="••••••••">
                </div>
            </div>
            <p class="cuenta-sub mb-2" style="font-size:0.78rem;">Mínimo 8 caracteres, con mayúscula, número y símbolo.</p>
            <button class="btn-primary-app mb-3" id="btnRegistro">Crear cuenta</button>
            <div class="text-center">
                <span class="link-muted" onclick="showStep('login')">Ya tengo cuenta</span>
            </div>
        </div>

        <!-- ══ VERIFICAR CÓDIGO ══ -->
        <div class="step-section" id="stepVerificar">
            <h4>Verifica tu correo</h4>
            <p class="cuenta-sub">Enviamos un código de 6 dígitos a <strong id="verificarEmailLabel"></strong>.</p>
            <input type="hidden" id="verificarEmail">
            <div class="mb-3">
                <input type="text" id="verificarCodigo" class="form-control code-input" maxlength="6" placeholder="000000">
            </div>
            <button class="btn-primary-app mb-2" id="btnVerificar">Verificar</button>
            <div class="text-center">
                <span class="link-muted" id="btnReenviar">Reenviar código</span>
            </div>
        </div>

        <!-- ══ OLVIDÉ MI CONTRASEÑA ══ -->
        <div class="step-section" id="stepForgot">
            <h4>Recuperar contraseña</h4>
            <p class="cuenta-sub">Te enviaremos un código para restablecerla.</p>
            <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <input type="email" id="forgotEmail" class="form-control" placeholder="tucorreo@ejemplo.com">
            </div>
            <button class="btn-primary-app mb-3" id="btnForgot">Enviar código</button>
            <div class="text-center">
                <span class="link-muted" onclick="showStep('login')">Volver a iniciar sesión</span>
            </div>
        </div>

        <!-- ══ RESTABLECER CONTRASEÑA ══ -->
        <div class="step-section" id="stepReset">
            <h4>Ingresa el código</h4>
            <p class="cuenta-sub">Revisa tu correo <strong id="resetEmailLabel"></strong> y define tu nueva contraseña.</p>
            <input type="hidden" id="resetEmail">
            <div class="mb-2">
                <input type="text" id="resetCodigo" class="form-control code-input" maxlength="6" placeholder="000000">
            </div>
            <div class="mb-2">
                <label class="form-label">Nueva contraseña</label>
                <input type="password" id="resetPassword" class="form-control" placeholder="••••••••">
            </div>
            <button class="btn-primary-app mb-3" id="btnReset">Restablecer contraseña</button>
        </div>

    </div>

    <script>
    const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
    const VOLVER = '<?php echo $volver; ?>';

    function showStep(nombre) {
        document.querySelectorAll('.step-section').forEach(s => s.classList.remove('active'));
        document.getElementById('step' + nombre.charAt(0).toUpperCase() + nombre.slice(1)).classList.add('active');
    }

    function redirigirDespuesDeLogin() {
        window.location.href = VOLVER === 'checkout' ? 'V_checkout.php' : '../../store.php';
    }

    async function llamar(action, body) {
        const res = await fetch('../../controllers/C_ClienteAuth.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    document.getElementById('btnLogin').addEventListener('click', async () => {
        const email = document.getElementById('loginEmail').value.trim();
        const password = document.getElementById('loginPassword').value;
        if (!email || !password) {
            Swal.fire({ icon: 'warning', text: 'Completa correo y contraseña.' });
            return;
        }
        try {
            const data = await llamar('login', { email, password, csrf_token: CSRF_TOKEN });
            if (data.success) {
                redirigirDespuesDeLogin();
            } else if (data.no_verificado) {
                document.getElementById('verificarEmail').value = email;
                document.getElementById('verificarEmailLabel').textContent = email;
                showStep('verificar');
                Swal.fire({ icon: 'info', text: data.mensaje });
            } else {
                Swal.fire({ icon: 'error', text: data.mensaje });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', text: 'Error de conexión.' });
        }
    });

    document.getElementById('btnRegistro').addEventListener('click', async () => {
        const payload = {
            numero_documento: document.getElementById('regDni').value.trim(),
            telefono: document.getElementById('regTelefono').value.trim(),
            nombres_razon_social: document.getElementById('regNombres').value.trim(),
            apellidos: document.getElementById('regApellidos').value.trim(),
            email: document.getElementById('regEmail').value.trim(),
            password: document.getElementById('regPassword').value,
        };
        const confirmar = document.getElementById('regPasswordConfirm').value;

        if (!payload.numero_documento || !payload.nombres_razon_social || !payload.email || !payload.password) {
            Swal.fire({ icon: 'warning', text: 'Completa todos los campos obligatorios.' });
            return;
        }
        if (payload.password !== confirmar) {
            Swal.fire({ icon: 'warning', text: 'Las contraseñas no coinciden.' });
            return;
        }

        try {
            const data = await llamar('registrar', payload);
            if (data.success) {
                document.getElementById('verificarEmail').value = payload.email;
                document.getElementById('verificarEmailLabel').textContent = payload.email;
                showStep('verificar');
                Swal.fire({ icon: 'success', text: data.mensaje });
            } else {
                Swal.fire({ icon: 'error', text: data.mensaje });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', text: 'Error de conexión.' });
        }
    });

    document.getElementById('btnVerificar').addEventListener('click', async () => {
        const email = document.getElementById('verificarEmail').value;
        const codigo = document.getElementById('verificarCodigo').value.trim();
        if (!codigo) {
            Swal.fire({ icon: 'warning', text: 'Ingresa el código que enviamos a tu correo.' });
            return;
        }
        try {
            const data = await llamar('verificar_email', { email, codigo });
            if (data.success) {
                Swal.fire({ icon: 'success', text: data.mensaje, timer: 1500, showConfirmButton: false })
                    .then(redirigirDespuesDeLogin);
            } else {
                Swal.fire({ icon: 'error', text: data.mensaje });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', text: 'Error de conexión.' });
        }
    });

    document.getElementById('btnReenviar').addEventListener('click', async () => {
        const email = document.getElementById('verificarEmail').value;
        try {
            const data = await llamar('reenviar_codigo', { email });
            Swal.fire({ icon: data.success ? 'success' : 'error', text: data.mensaje });
        } catch (e) {
            Swal.fire({ icon: 'error', text: 'Error de conexión.' });
        }
    });

    document.getElementById('btnForgot').addEventListener('click', async () => {
        const email = document.getElementById('forgotEmail').value.trim();
        if (!email) {
            Swal.fire({ icon: 'warning', text: 'Ingresa tu correo.' });
            return;
        }
        try {
            const data = await llamar('solicitar_reset', { email });
            document.getElementById('resetEmail').value = email;
            document.getElementById('resetEmailLabel').textContent = email;
            showStep('reset');
            Swal.fire({ icon: 'info', text: data.mensaje });
        } catch (e) {
            Swal.fire({ icon: 'error', text: 'Error de conexión.' });
        }
    });

    document.getElementById('btnReset').addEventListener('click', async () => {
        const email = document.getElementById('resetEmail').value;
        const codigo = document.getElementById('resetCodigo').value.trim();
        const password = document.getElementById('resetPassword').value;
        if (!codigo || !password) {
            Swal.fire({ icon: 'warning', text: 'Completa el código y tu nueva contraseña.' });
            return;
        }
        try {
            const data = await llamar('confirmar_reset', { email, codigo, password });
            if (data.success) {
                Swal.fire({ icon: 'success', text: data.mensaje }).then(() => showStep('login'));
            } else {
                Swal.fire({ icon: 'error', text: data.mensaje });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', text: 'Error de conexión.' });
        }
    });
    </script>
</body>
</html>
