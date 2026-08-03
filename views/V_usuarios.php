<?php
// Restricción de acceso: Solo el Administrador puede listar o realizar mantenimiento de usuarios
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

// Cargar modelos de Usuarios y Roles para desplegar las vistas y formularios modales
require_once dirname(__DIR__) . '/models/M_Usuario.php';
require_once dirname(__DIR__) . '/models/M_Rol.php';

$modelUser = M_Usuario::singleton();
$usuarios = $modelUser->listarUsuarios();

$modelRol = M_Rol::singleton();
$roles = $modelRol->listar();
?>

<div class="container-fluid px-0">
    <!-- Encabezado de Página -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Usuarios</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Administra los accesos y roles de los trabajadores del sistema.</p>
        </div>
        <button class="gp-btn-primary d-flex align-items-center gap-2 border-0" data-bs-toggle="modal" data-bs-target="#nuevoUsuarioModal">
            <i class="bi bi-plus-lg"></i>
            <span>Nuevo Usuario</span>
        </button>
    </div>

    <!-- Panel de Usuarios -->
    <div class="gp-card">
        <!-- Barra de Búsqueda -->
        <div class="row mb-3">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted" id="search-addon">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0 text-sm" id="searchUsuarios" placeholder="Buscar usuario..." aria-label="Buscar" aria-describedby="search-addon" style="box-shadow: none; font-size: 14px;">
                </div>
            </div>
        </div>

        <!-- Tabla del Listado de Usuarios -->
        <div class="table-responsive">
            <table class="table align-middle text-sm" id="tableUsuarios" style="font-size: 14px;">
                <thead>
                    <tr class="text-muted border-bottom" style="font-size: 13px;">
                        <th scope="col" class="pb-3">Nombre Completo</th>
                        <th scope="col" class="pb-3">N° Documento</th>
                        <th scope="col" class="pb-3">Nombre Usuario</th>
                        <th scope="col" class="pb-3">Rol</th>
                        <th scope="col" class="pb-3">Teléfono</th>
                        <th scope="col" class="pb-3">Estado</th>
                        <th scope="col" class="pb-3 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people-fill fs-2 mb-2 d-block"></i>
                                No se encontraron usuarios.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usr): ?>
                            <tr class="border-bottom usuario-row">
                                <td class="py-3">
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars(($usr['nombres_razon_social'] ?? '') . ' ' . ($usr['apellidos'] ?? '')); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-dark fw-medium"><?php echo htmlspecialchars($usr['numero_documento'] ?? ''); ?></span>
                                        <small class="text-muted" style="font-size: 11px;"><?php echo $usr['tipo_documento'] == 1 ? 'DNI' : 'RUC'; ?></small>
                                    </div>
                                </td>
                                <td class="font-mono text-dark fw-medium">
                                    <?php echo htmlspecialchars($usr['username'] ?? ''); ?>
                                </td>
                                <td>
                                    <!-- Insignias diferenciadas por rol de usuario -->
                                    <span class="badge <?php echo $usr['rol'] === 'Administrador' ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-20' : 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20'; ?> px-2.5 py-1.5 fw-semibold" style="font-size: 11px;">
                                        <?php echo htmlspecialchars($usr['rol'] ?? ''); ?>
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?php echo htmlspecialchars(($usr['telefono'] ?? '') ? $usr['telefono'] : '-'); ?>
                                </td>
                                <td>
                                    <span class="<?php echo $usr['estado'] == 1 ? 'gp-badge-success' : 'gp-badge-danger'; ?>">
                                        <?php echo $usr['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Mapeo de atributos data para el formulario de edición -->
                                        <button class="btn btn-link text-muted p-1 hover-text-primary edit-usuario-btn" 
                                                data-id="<?php echo $usr['id_usuario']; ?>"
                                                data-tipodoc="<?php echo $usr['tipo_documento']; ?>"
                                                data-numdoc="<?php echo htmlspecialchars($usr['numero_documento'] ?? ''); ?>"
                                                data-nombres="<?php echo htmlspecialchars($usr['nombres_razon_social'] ?? ''); ?>"
                                                data-apellidos="<?php echo htmlspecialchars($usr['apellidos'] ?? ''); ?>"
                                                data-direccion="<?php echo htmlspecialchars($usr['direccion'] ?? ''); ?>"
                                                data-telefono="<?php echo htmlspecialchars($usr['telefono'] ?? ''); ?>"
                                                data-rolid="<?php echo $usr['id_rol']; ?>"
                                                data-username="<?php echo htmlspecialchars($usr['username'] ?? ''); ?>"
                                                title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <!-- Impedir la auto-eliminación lógica de la sesión activa -->
                                        <?php if ($usr['id_usuario'] != $_SESSION['id_usuario']): ?>
                                            <button class="btn btn-link text-muted p-1 hover-text-danger delete-usuario-btn" 
                                                    data-id="<?php echo $usr['id_usuario']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars(($usr['nombres_razon_social'] ?? '') . ' ' . ($usr['apellidos'] ?? '')); ?>"
                                                    title="Eliminar">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Nuevo Usuario -->
<div class="modal fade" id="nuevoUsuarioModal" tabindex="-1" aria-labelledby="nuevoUsuarioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="nuevoUsuarioModalLabel">Nuevo Usuario</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <form id="formNuevoUsuario">
                <div class="modal-body p-4">
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="new_tipo_doc" class="form-label fw-semibold" style="font-size: 13px;">Tipo Documento</label>
                            <select class="form-select" id="new_tipo_doc" required>
                                <option value="1" selected>DNI</option>
                                <option value="2">RUC</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="new_num_doc" class="form-label fw-semibold" style="font-size: 13px;">Número Documento</label>
                            <input type="text" class="form-control" id="new_num_doc" placeholder="N° Documento" required autocomplete="off">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="new_nombres" class="form-label fw-semibold" style="font-size: 13px;">Nombres</label>
                            <input type="text" class="form-control" id="new_nombres" placeholder="Nombres" required autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="new_apellidos" class="form-label fw-semibold" style="font-size: 13px;">Apellidos</label>
                            <input type="text" class="form-control" id="new_apellidos" placeholder="Apellidos" required autocomplete="off">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="new_telefono" class="form-label fw-semibold" style="font-size: 13px;">Teléfono</label>
                            <input type="text" class="form-control" id="new_telefono" placeholder="Ej. 987654321" autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="new_rol" class="form-label fw-semibold" style="font-size: 13px;">Rol de Usuario</label>
                            <select class="form-select" id="new_rol" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r['id_rol']; ?>"><?php echo htmlspecialchars($r['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="new_username" class="form-label fw-semibold" style="font-size: 13px;">Nombre Usuario</label>
                            <input type="text" class="form-control" id="new_username" placeholder="Username" required autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="new_password" class="form-label fw-semibold" style="font-size: 13px;">Contraseña</label>
                            <input type="password" class="form-control" id="new_password" placeholder="Contraseña" required>
                            <!-- Barra visual evaluadora de complejidad de clave -->
                            <div class="progress mt-2" style="height: 5px;">
                                <div id="pass_strength_bar" class="progress-bar bg-danger" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <small id="pass_strength_text" class="text-muted" style="font-size: 11px; font-weight: 500;">Mínimo 8 caracteres, 1 mayúscula, 1 número y 1 símbolo</small>
                        </div>
                    </div>
                    <div>
                        <label for="new_direccion" class="form-label fw-semibold" style="font-size: 13px;">Dirección</label>
                        <input type="text" class="form-control" id="new_direccion" placeholder="Dirección del usuario" autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0">Crear registro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar Usuario -->
<div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-labelledby="editarUsuarioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="editarUsuarioModalLabel">Editar Registro</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <form id="formEditarUsuario">
                <input type="hidden" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="edit_tipo_doc" class="form-label fw-semibold" style="font-size: 13px;">Tipo Documento</label>
                            <select class="form-select" id="edit_tipo_doc" required>
                                <option value="1">DNI</option>
                                <option value="2">RUC</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="edit_num_doc" class="form-label fw-semibold" style="font-size: 13px;">Número Documento</label>
                            <input type="text" class="form-control" id="edit_num_doc" required autocomplete="off">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="edit_nombres" class="form-label fw-semibold" style="font-size: 13px;">Nombres</label>
                            <input type="text" class="form-control" id="edit_nombres" required autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="edit_apellidos" class="form-label fw-semibold" style="font-size: 13px;">Apellidos</label>
                            <input type="text" class="form-control" id="edit_apellidos" required autocomplete="off">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="edit_telefono" class="form-label fw-semibold" style="font-size: 13px;">Teléfono</label>
                            <input type="text" class="form-control" id="edit_telefono" autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="edit_rol" class="form-label fw-semibold" style="font-size: 13px;">Rol de Usuario</label>
                            <select class="form-select" id="edit_rol" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r['id_rol']; ?>"><?php echo htmlspecialchars($r['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="edit_username" class="form-label fw-semibold" style="font-size: 13px;">Nombre Usuario</label>
                            <input type="text" class="form-control" id="edit_username" required autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="edit_password" class="form-label fw-semibold" style="font-size: 13px;">Nueva Contraseña (Opcional)</label>
                            <input type="password" class="form-control" id="edit_password" placeholder="Solo si desea cambiarla">
                            <div class="progress mt-2" style="height: 5px; display: none;" id="edit_progress_container">
                                <div id="edit_pass_strength_bar" class="progress-bar bg-danger" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <small id="edit_pass_strength_text" class="text-muted" style="font-size: 11px; font-weight: 500;"></small>
                        </div>
                    </div>
                    <div>
                        <label for="edit_direccion" class="form-label fw-semibold" style="font-size: 13px;">Dirección</label>
                        <input type="text" class="form-control" id="edit_direccion" autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="gp-btn-primary border-0">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript para Operaciones CRUD y Validación de Fortaleza de Contraseñas -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Evaluar la fortaleza de la contraseña en una escala del 0 al 100
        function evaluatePassword(pass) {
            let strength = 0;
            if (pass.length >= 8) strength += 25;
            if (pass.match(/[A-Z]/)) strength += 25;
            if (pass.match(/[0-9]/)) strength += 25;
            if (pass.match(/[\W_]/)) strength += 25;
            return strength;
        }

        // Actualizar visualmente la barra de progreso de seguridad de contraseña
        function updateStrengthUI(input, bar, textEl, container = null) {
            const pass = input.value;
            if (container && pass.length === 0) {
                container.style.display = 'none';
                textEl.innerText = '';
                return;
            }
            if (container) container.style.display = 'flex';
            
            const score = evaluatePassword(pass);
            bar.style.width = score + '%';
            
            if (pass.length === 0) {
                bar.className = 'progress-bar bg-danger';
                textEl.innerText = 'Mínimo 8 caracteres, 1 mayúscula, 1 número y 1 símbolo';
                textEl.className = 'text-muted';
                return;
            }
            
            if (score <= 25) {
                bar.className = 'progress-bar bg-danger';
                textEl.innerText = 'Débil';
                textEl.className = 'text-danger';
            } else if (score <= 50) {
                bar.className = 'progress-bar bg-warning';
                textEl.innerText = 'Regular (Faltan requisitos)';
                textEl.className = 'text-warning';
            } else if (score <= 75) {
                bar.className = 'progress-bar bg-info';
                textEl.innerText = 'Buena';
                textEl.className = 'text-info';
            } else {
                bar.className = 'progress-bar bg-success';
                textEl.innerText = 'Fuerte y Segura';
                textEl.className = 'text-success';
            }
        }

        const newPassInput = document.getElementById('new_password');
        const newPassBar = document.getElementById('pass_strength_bar');
        const newPassText = document.getElementById('pass_strength_text');
        if (newPassInput) {
            newPassInput.addEventListener('input', () => updateStrengthUI(newPassInput, newPassBar, newPassText));
        }

        const editPassInput = document.getElementById('edit_password');
        const editPassBar = document.getElementById('edit_pass_strength_bar');
        const editPassText = document.getElementById('edit_pass_strength_text');
        const editProgressContainer = document.getElementById('edit_progress_container');
        if (editPassInput) {
            editPassInput.addEventListener('input', () => updateStrengthUI(editPassInput, editPassBar, editPassText, editProgressContainer));
        }

        // Búsqueda interactiva en el listado
        const searchInput = document.getElementById('searchUsuarios');
        const rows = document.querySelectorAll('.usuario-row');

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.toLowerCase().trim();
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    if (text.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Procesar inserción de Nuevo Usuario por AJAX
        const formNuevo = document.getElementById('formNuevoUsuario');
        if (formNuevo) {
            formNuevo.addEventListener('submit', async (e) => {
                e.preventDefault();
                const tipo_documento = document.getElementById('new_tipo_doc').value;
                const numero_documento = document.getElementById('new_num_doc').value.trim();
                const nombres_razon_social = document.getElementById('new_nombres').value.trim();
                const apellidos = document.getElementById('new_apellidos').value.trim();
                const telefono = document.getElementById('new_telefono').value.trim();
                const id_rol = document.getElementById('new_rol').value;
                const username = document.getElementById('new_username').value.trim();
                const password = document.getElementById('new_password').value;
                const direccion = document.getElementById('new_direccion').value.trim();

                try {
                    const response = await fetch('./controllers/C_Usuario.php?action=crear', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tipo_documento, numero_documento, nombres_razon_social, apellidos, telefono, id_rol, username, password, direccion })
                    });
                    const data = await response.json();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Creado!',
                            text: data.mensaje,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.mensaje,
                            confirmButtonColor: '#0284c7'
                        });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // Rellenar campos en el modal de Edición
        const editModal = new bootstrap.Modal(document.getElementById('editarUsuarioModal'));
        document.querySelectorAll('.edit-usuario-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('edit_id').value = btn.dataset.id;
                document.getElementById('edit_tipo_doc').value = btn.dataset.tipodoc;
                document.getElementById('edit_num_doc').value = btn.dataset.numdoc;
                document.getElementById('edit_nombres').value = btn.dataset.nombres;
                document.getElementById('edit_apellidos').value = btn.dataset.apellidos;
                document.getElementById('edit_direccion').value = btn.dataset.direccion;
                document.getElementById('edit_telefono').value = btn.dataset.telefono;
                document.getElementById('edit_rol').value = btn.dataset.rolid;
                document.getElementById('edit_username').value = btn.dataset.username;
                document.getElementById('edit_password').value = '';
                
                editModal.show();
            });
        });

        // Enviar actualización de Usuario por AJAX
        const formEditar = document.getElementById('formEditarUsuario');
        if (formEditar) {
            formEditar.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id_usuario = document.getElementById('edit_id').value;
                const tipo_documento = document.getElementById('edit_tipo_doc').value;
                const numero_documento = document.getElementById('edit_num_doc').value.trim();
                const nombres_razon_social = document.getElementById('edit_nombres').value.trim();
                const apellidos = document.getElementById('edit_apellidos').value.trim();
                const telefono = document.getElementById('edit_telefono').value.trim();
                const id_rol = document.getElementById('edit_rol').value;
                const username = document.getElementById('edit_username').value.trim();
                const password = document.getElementById('edit_password').value;
                const direccion = document.getElementById('edit_direccion').value.trim();

                try {
                    const response = await fetch('./controllers/C_Usuario.php?action=actualizar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id_usuario, tipo_documento, numero_documento, nombres_razon_social, apellidos, telefono, id_rol, username, password, direccion })
                    });
                    const data = await response.json();

                    if (data.success) {
                        editModal.hide();
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: data.mensaje,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.mensaje,
                            confirmButtonColor: '#0284c7'
                        });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // Eliminar lógicamente (suspender cuenta de acceso) un usuario
        document.querySelectorAll('.delete-usuario-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id_usuario = btn.dataset.id;
                const nombre = btn.dataset.nombre;

                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `Deseas eliminar al usuario "${nombre}" (esto suspenderá su acceso)`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, suspender',
                    cancelButtonText: 'Cancelar'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('./controllers/C_Usuario.php?action=eliminar', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id_usuario })
                            });
                            const data = await response.json();

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Desactivado!',
                                    text: data.mensaje,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => window.location.reload());
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.mensaje,
                                    confirmButtonColor: '#0284c7'
                                });
                            }
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                        }
                    }
                });
            });
        });
    });
</script>
