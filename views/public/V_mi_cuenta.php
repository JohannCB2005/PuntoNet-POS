<?php
require_once dirname(__DIR__, 2) . '/config/sesion_segura.php';
session_start();
if (!isset($_SESSION['id_cliente'])) {
    header('Location: /cuenta?volver=mi_cuenta');
    exit;
}
$clienteNombre = $_SESSION['cliente_nombre'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta — NISSI</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/favicon-nissi.svg?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../assets/css/tienda.css?v=4">
    <?php require_once dirname(__DIR__, 2) . '/config/marca.php'; echo marcaCss(); ?>

    <style>
        body { background: var(--paper); color: var(--ink); }
        .navbar { background: rgba(255,255,255,.9); backdrop-filter: blur(14px); }
        .navbar-brand { font-family: var(--font-display); font-weight: 800; color: var(--navy) !important; }
        .navbar-brand em { font-style: normal; color: var(--accent); }
        @media (max-width: 576px) { .nav-label { display: none; } }
        .cuenta-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 26px 28px;
            box-shadow: var(--shadow);
        }
        .co-label { font-size: .72rem; font-weight: 700; color: var(--ink-soft); margin-bottom: 7px; display: block; text-transform: uppercase; letter-spacing: .08em; }
        .co-input {
            width: 100%; padding: 12px 15px; border: 1.5px solid var(--line-strong); border-radius: var(--radius-sm);
            font-size: .95rem; transition: border-color .2s, box-shadow .2s;
        }
        .co-input:focus { outline: none; border-color: var(--navy); box-shadow: 0 0 0 3px rgba(var(--navy-rgb),.12); }
        .page-title { font-family: var(--font-display); font-weight: 700; color: var(--navy); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/tienda">
                <span>NISSI<em>.</em></span>
            </a>
            <a href="/mis-pedidos" class="btn btn-outline-primary rounded-pill btn-sm">
                <i class="bi bi-bag-check"></i><span class="nav-label"> Mis Pedidos</span>
            </a>
        </div>
    </nav>

    <div class="container" style="padding-top:90px; padding-bottom:60px; max-width:520px;">
        <h3 class="page-title mb-1">Mi Cuenta</h3>
        <p class="text-muted mb-4">Hola<?php echo $clienteNombre ? ', ' . htmlspecialchars($clienteNombre) : ''; ?>.</p>

        <div class="cuenta-card">
            <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2"></i>Cambiar contraseña</h5>
            <form id="formPassword">
                <div class="mb-3">
                    <label class="co-label">Contraseña actual</label>
                    <input type="password" id="passwordActual" class="co-input" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="co-label">Nueva contraseña</label>
                    <input type="password" id="passwordNueva" class="co-input" required autocomplete="new-password">
                    <div class="form-text">Mínimo 8 caracteres, con una mayúscula, un número y un símbolo.</div>
                </div>
                <div class="mb-3">
                    <label class="co-label">Confirmar nueva contraseña</label>
                    <input type="password" id="passwordConfirmar" class="co-input" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary rounded-pill px-4 w-100" id="btnGuardarPassword">
                    Actualizar contraseña
                </button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('formPassword').addEventListener('submit', async (e) => {
        e.preventDefault();

        const passwordActual    = document.getElementById('passwordActual').value;
        const passwordNueva     = document.getElementById('passwordNueva').value;
        const passwordConfirmar = document.getElementById('passwordConfirmar').value;

        if (passwordNueva !== passwordConfirmar) {
            Swal.fire({ icon: 'error', title: 'No coinciden', text: 'La nueva contraseña y su confirmación no son iguales.' });
            return;
        }

        const btn = document.getElementById('btnGuardarPassword');
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        try {
            const res  = await fetch('../../controllers/C_ClienteAuth.php?action=cambiar_password', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ password_actual: passwordActual, password_nueva: passwordNueva }),
            });
            const json = await res.json();

            if (json.success) {
                Swal.fire({ icon: 'success', title: 'Listo', text: json.mensaje });
                document.getElementById('formPassword').reset();
            } else {
                Swal.fire({ icon: 'error', title: 'No se pudo actualizar', text: json.mensaje || 'Intenta de nuevo.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Intenta de nuevo en unos segundos.' });
        } finally {
            btn.disabled = false;
            btn.textContent = 'Actualizar contraseña';
        }
    });
    </script>
</body>
</html>
