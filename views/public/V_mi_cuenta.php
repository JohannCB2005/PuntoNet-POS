<?php
session_start();
if (!isset($_SESSION['id_cliente'])) {
    header('Location: V_cuenta.php?volver=mi_cuenta');
    exit;
}
$clienteNombre = $_SESSION['cliente_nombre'] ?? '';
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
            --bg-main: #f0f4ff;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg-main); color: var(--text-dark); }
        .navbar { background: rgba(255,255,255,.95); box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .navbar-brand { font-weight: 800; color: var(--primary) !important; }
        .cuenta-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            padding: 24px 28px;
        }
        .co-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: block; }
        .co-input {
            width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 10px;
            font-size: 0.95rem; transition: border-color .2s;
        }
        .co-input:focus { outline: none; border-color: var(--primary); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-2" href="../../store.php">
                <img src="../../assets/logo.svg" alt="PuntoNet" height="30">
                <span>PuntoNet</span>
            </a>
            <a href="V_mis_pedidos.php" class="btn btn-outline-primary rounded-pill btn-sm">
                <i class="bi bi-bag-check me-1"></i> Mis Pedidos
            </a>
        </div>
    </nav>

    <div class="container" style="padding-top:90px; padding-bottom:60px; max-width:520px;">
        <h3 class="fw-bold mb-1">Mi Cuenta</h3>
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
