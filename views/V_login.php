<?php
// Validar el estado de la sesión e iniciarla si no existe una activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Generación del token CSRF para seguridad en el envío del formulario de acceso
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once dirname(__DIR__) . '/config/settings.php';
require_once dirname(__DIR__) . '/config/marca.php';
$loginMarcaNombre = marcaVar('nombre');
$loginMarcaLogo   = marcaLogo('LOGO_CLARO', 'assets/logo-nissi.svg');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — <?php echo htmlspecialchars($loginMarcaNombre); ?> POS</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(configuracion('LOGO_FAVICON', 'assets/favicon-nissi.svg?v=3')); ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="assets/favicons/favicon-64.png?v=3">
    <link rel="icon" type="image/png" sizes="128x128" href="assets/favicons/favicon-128.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/apple-touch-icon.png?v=3">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy: #23284E;
            --navy-700: #31386b;
            --navy-900: #191d3a;
            --accent: #BD1721;
            --accent-hover: #e03440;
            --ink: #1d2136;
            --muted: #838aa3;
            --line: #ececf0;
            --paper: #f6f6f8;
        }
        <?php echo marcaCssVars(); ?>

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef0f7;
        }

        /* ── Contenedor principal ── */
        .login-wrapper {
            display: flex;
            width: 960px;
            max-width: 96vw;
            min-height: 580px;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 80px -20px rgba(18,21,43,.35), 0 10px 30px -8px rgba(18,21,43,.12);
            background: #fff;
        }

        /* ── Panel izquierdo (Héroe) ── */
        .login-hero {
            flex: 0 0 44%;
            position: relative;
            background: url('assets/Login Cajera.jpeg') center center / cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 40px 34px;
        }

        .login-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                180deg,
                rgba(var(--navy-rgb),0.05) 0%,
                rgba(25,29,58,0.82) 100%
            );
        }

        .login-hero::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--accent), #e03440);
        }

        .hero-text {
            position: relative;
            z-index: 1;
            color: #fff;
        }

        .hero-kicker {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: .68rem; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
            color: #f0a3a8; margin-bottom: 14px;
        }

        .hero-text h2 {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 30px;
            font-weight: 700;
            line-height: 1.18;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .hero-text h2 span {
            color: #f0a3a8;
            font-style: italic;
        }

        .hero-text p {
            font-size: 13.5px;
            color: rgba(255,255,255,0.80);
            line-height: 1.65;
            max-width: 300px;
        }

        /* ── Panel derecho (Formulario) ── */
        .login-form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 56px 56px;
        }

        .brand-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .brand-logo img {
            max-height: 96px;
            max-width: 100%;
            object-fit: contain;
        }

        .login-title {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 30px;
            font-weight: 700;
            color: var(--navy);
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            text-align: center;
        }

        .login-subtitle {
            font-size: 13.5px;
            color: var(--muted);
            margin-bottom: 34px;
            line-height: 1.6;
            text-align: center;
            max-width: 330px;
            margin-left: auto;
            margin-right: auto;
        }

        .form-label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 7px;
        }

        .form-control {
            height: 50px;
            border-radius: 12px;
            border: 1.5px solid var(--line);
            font-size: 14.5px;
            color: var(--ink);
            background: var(--paper);
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
            box-shadow: none !important;
        }

        .form-control::placeholder { color: #aab0c4; }

        .form-control:focus {
            border-color: var(--navy);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(var(--navy-rgb),0.10) !important;
        }

        .input-group .form-control {
            border-right: none;
            border-radius: 12px 0 0 12px;
        }

        .input-group .btn-toggle-pass {
            border: 1.5px solid var(--line);
            border-left: none;
            border-radius: 0 12px 12px 0;
            background: var(--paper);
            color: var(--muted);
            padding: 0 16px;
            transition: color 0.15s, background 0.15s;
        }

        .input-group .btn-toggle-pass:hover { color: var(--navy); background: #fff; }

        .input-group:focus-within .form-control,
        .input-group:focus-within .btn-toggle-pass {
            border-color: var(--navy);
        }

        .input-group:focus-within .btn-toggle-pass { background: #fff; }

        .btn-login {
            height: 50px;
            border-radius: 12px;
            background: var(--navy);
            border: none;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            transition: background 0.18s, transform 0.1s, box-shadow 0.2s;
            width: 100%;
            margin-top: 26px;
        }

        .btn-login:hover { background: var(--navy-700); box-shadow: 0 10px 24px -8px rgba(var(--navy-rgb),.5); }
        .btn-login:active { transform: scale(0.985); }

        .btn-login:disabled {
            background: #c6c9d4;
            cursor: not-allowed;
            box-shadow: none;
        }

        .login-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }

        .login-footer a {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }

        .login-footer a:hover { text-decoration: underline; }

        /* Diseño Responsivo */
        @media (max-width: 700px) {
            .login-hero { display: none; }
            .login-form-panel { padding: 40px 28px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <!-- PANEL IZQUIERDO: Héroe Visual -->
    <div class="login-hero">
        <div class="hero-text">
            <span class="hero-kicker"><span style="width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block"></span> NISSI POS</span>
            <h2>Tu tienda de uniformes<br>en <span>un solo lugar</span></h2>
            <p>Gestiona ventas, inventario de uniformes y módulos escolares de forma rápida y profesional.</p>
        </div>
    </div>

    <!-- PANEL DERECHO: Formulario de Credenciales -->
    <div class="login-form-panel">
        <div class="brand-logo mb-4">
            <img src="<?php echo htmlspecialchars($loginMarcaLogo); ?>" alt="<?php echo htmlspecialchars($loginMarcaNombre); ?>" style="max-height: 65px; max-width: 100%; object-fit: contain;">
        </div>

        <h1 class="login-title">Iniciar Sesión</h1>
        <p class="login-subtitle">
            Bienvenido al sistema de gestión de uniformes y módulos escolares.<br>
            Ingresa tus credenciales para continuar.
        </p>

        <form id="loginForm" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">Usuario</label>
                <input
                    type="text"
                    class="form-control"
                    id="username"
                    placeholder="Ingresa tu usuario"
                    autocomplete="username"
                    required>
            </div>

            <div class="mb-1">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required>
                    <button type="button" class="btn-toggle-pass" id="togglePass" tabindex="-1">
                        <i class="bi bi-eye" id="togglePassIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                Iniciar Sesión
            </button>
            <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        </form>

        <div class="login-footer">
            <a href="#" onclick="return false;">¿Olvidaste tu contraseña?</a>
        </div>
    </div>
</div>

<!-- Lógica JavaScript para control visual y envío AJAX con CSRF token -->
<script>
    // Alternar visibilidad de la contraseña
    document.getElementById('togglePass').addEventListener('click', function () {
        const pass = document.getElementById('password');
        const icon = document.getElementById('togglePassIcon');
        if (pass.type === 'password') {
            pass.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            pass.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });

    // Envío del formulario de inicio de sesión
    document.getElementById('loginForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const csrf_token = document.getElementById('csrf_token').value;
        const btn      = document.getElementById('loginBtn');

        if (!username || !password) {
            Swal.fire({
                icon: 'warning',
                title: 'Campos requeridos',
                text: 'Por favor ingresa tu usuario y contraseña.',
                confirmButtonColor: '#23284E'
            });
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Verificando...';

        try {
            const response = await fetch('controllers/C_Login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password, csrf_token })
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Bienvenido!',
                    text: data.mensaje,
                    showConfirmButton: false,
                    timer: 1200,
                    timerProgressBar: true
                }).then(() => {
                    window.location.href = '/';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Acceso denegado',
                    text: data.mensaje,
                    confirmButtonColor: '#BD1721'
                });
                btn.disabled = false;
                btn.innerHTML = 'Iniciar Sesión';
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo contactar al servidor.',
                confirmButtonColor: '#23284E'
            });
            btn.disabled = false;
            btn.innerHTML = 'Iniciar Sesión';
        }
    });
</script>
</body>
</html>