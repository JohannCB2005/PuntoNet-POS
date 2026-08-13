<?php
// Validar el estado de la sesión e iniciarla si no existe una activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Generación del token CSRF para seguridad en el envío del formulario de acceso
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — NISSI POS</title>
    <link rel="icon" type="image/png" sizes="64x64" href="assets/favicons/favicon-64.png">
    <link rel="icon" type="image/png" sizes="128x128" href="assets/favicons/favicon-128.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/apple-touch-icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8eeff;
        }

        /* ── Contenedor principal ── */
        .login-wrapper {
            display: flex;
            width: 900px;
            max-width: 96vw;
            min-height: 540px;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 24px 64px -12px rgba(0,0,0,0.22), 0 8px 24px -6px rgba(0,0,0,0.10);
            background: #fff;
        }

        /* ── Panel izquierdo (Héroe) ── */
        .login-hero {
            flex: 0 0 42%;
            position: relative;
            background: url('assets/Login Cajera.jpeg') center center / cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 36px 32px;
        }

        .login-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                180deg,
                rgba(0,0,0,0.08) 0%,
                rgba(10,30,80,0.78) 100%
            );
        }

        .hero-text {
            position: relative;
            z-index: 1;
            color: #fff;
        }

        .hero-text h2 {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .hero-text h2 span {
            color: #93c5fd;
        }

        .hero-text p {
            font-size: 13.5px;
            color: rgba(255,255,255,0.80);
            line-height: 1.6;
        }

        /* ── Panel derecho (Formulario) ── */
        .login-form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 52px 48px;
        }

        .brand-name {
            font-size: 32px;
            font-weight: 800;
            color: #1d4ed8;
            letter-spacing: -1px;
            margin-bottom: 28px;
            text-transform: uppercase;
        }

        .brand-name span {
            color: #1e3a8a;
        }

        .login-title {
            font-size: 26px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 6px;
        }

        .login-subtitle {
            font-size: 13.5px;
            color: #6b7280;
            margin-bottom: 32px;
            line-height: 1.55;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-control {
            height: 46px;
            border-radius: 10px;
            border: 1.5px solid #d1d5db;
            font-size: 14px;
            color: #111827;
            transition: border-color 0.15s, box-shadow 0.15s;
            box-shadow: none !important;
        }

        .form-control:focus {
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29,78,216,0.12) !important;
        }

        .input-group .form-control {
            border-right: none;
            border-radius: 10px 0 0 10px;
        }

        .input-group .btn-toggle-pass {
            border: 1.5px solid #d1d5db;
            border-left: none;
            border-radius: 0 10px 10px 0;
            background: #fff;
            color: #9ca3af;
            padding: 0 14px;
            transition: color 0.15s;
        }

        .input-group .btn-toggle-pass:hover { color: #1d4ed8; }

        .input-group:focus-within .form-control,
        .input-group:focus-within .btn-toggle-pass {
            border-color: #1d4ed8;
        }

        .input-group:focus-within .btn-toggle-pass {
            box-shadow: 0 0 0 3px rgba(29,78,216,0.12);
        }

        .btn-login {
            height: 48px;
            border-radius: 10px;
            background: #1d4ed8;
            border: none;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: background 0.18s, transform 0.1s;
            width: 100%;
            margin-top: 24px;
        }

        .btn-login:hover { background: #1e40af; }
        .btn-login:active { transform: scale(0.985); }

        .btn-login:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }

        .login-footer {
            margin-top: 18px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
        }

        .login-footer a {
            color: #1d4ed8;
            font-weight: 600;
            text-decoration: none;
        }

        .login-footer a:hover { text-decoration: underline; }

        /* Diseño Responsivo */
        @media (max-width: 650px) {
            .login-hero { display: none; }
            .login-form-panel { padding: 36px 28px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <!-- PANEL IZQUIERDO: Héroe Visual -->
    <div class="login-hero">
        <div class="hero-text">
            <h2>Tu tienda de uniformes<br>en <span>un solo lugar</span></h2>
            <p>Gestiona ventas, inventario de uniformes y módulos escolares de forma rápida y profesional.</p>
        </div>
    </div>

    <!-- PANEL DERECHO: Formulario de Credenciales -->
    <div class="login-form-panel">
        <div class="brand-logo mb-4">
            <img src="assets/logo.svg" alt="PuntoNet" style="max-height: 65px; max-width: 100%; object-fit: contain;">
        </div>

        <h1 class="login-title">Iniciar Sesión 👋</h1>
        <p class="login-subtitle">
            Bienvenido al sistema de gestión de uniforms y módulos escolares.<br>
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
                confirmButtonColor: '#0284c7'
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
                    confirmButtonColor: '#1d4ed8'
                });
                btn.disabled = false;
                btn.innerHTML = 'Iniciar Sesión';
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo contactar al servidor.',
                confirmButtonColor: '#0284c7'
            });
            btn.disabled = false;
            btn.innerHTML = 'Iniciar Sesión';
        }
    });
</script>
</body>
</html>