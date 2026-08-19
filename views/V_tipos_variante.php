<?php
// Restringir el acceso: Solo usuarios logueados con rol de Administrador pueden ver esta vista
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

// Cargar modelo de tipos de variante
require_once dirname(__DIR__) . '/models/M_TipoVariante.php';
$modelTipo = M_TipoVariante::singleton();
$tiposVariante = $modelTipo->listar();
?>

<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Tipos de Variante</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Define las opciones de variante que se ofrecen al crear un producto (tallas, colores, materiales, etc.).</p>
        </div>
        <button class="gp-btn-primary d-flex align-items-center gap-2 border-0" data-bs-toggle="modal" data-bs-target="#nuevoTipoModal">
            <i class="bi bi-plus-lg"></i>
            <span>Nuevo Tipo de Variante</span>
        </button>
    </div>

    <div class="gp-card">
        <div class="row mb-3">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted" id="search-addon">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0 text-sm" id="searchTipos" placeholder="Buscar tipo de variante..." aria-label="Buscar" aria-describedby="search-addon" style="box-shadow: none; font-size: 14px;">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle text-sm" id="tableTipos" style="font-size: 14px;">
                <thead>
                    <tr class="text-muted border-bottom" style="font-size: 13px;">
                        <th scope="col" class="pb-3" style="width: 50px;">#</th>
                        <th scope="col" class="pb-3">Tipo de variante</th>
                        <th scope="col" class="pb-3">Origen</th>
                        <th scope="col" class="pb-3">Opciones</th>
                        <th scope="col" class="pb-3">Estado</th>
                        <th scope="col" class="pb-3 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tiposVariante)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-grid-3x3-gap fs-2 mb-2 d-block"></i>
                                No se encontraron tipos de variante.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $contador = 1; ?>
                        <?php foreach ($tiposVariante as $tv): ?>
                            <?php
                                $esSistema = $tv['tipo'] === 'sistema';
                                $esLibre   = $tv['modo'] === 'libre';
                                $opcionesTipo = ($esSistema && !$esLibre)
                                    ? $modelTipo->obtenerOpcionesSistema($tv['codigo'])
                                    : ($esSistema ? [] : $modelTipo->obtenerOpciones((int) $tv['id_tipo_variante']));
                            ?>
                            <tr class="border-bottom tipo-row"
                                data-id="<?php echo $tv['id_tipo_variante']; ?>"
                                data-nombre="<?php echo htmlspecialchars($tv['nombre']); ?>"
                                data-codigo="<?php echo htmlspecialchars($tv['codigo']); ?>"
                                data-tipo="<?php echo $tv['tipo']; ?>"
                                data-modo="<?php echo $tv['modo']; ?>"
                                data-opciones="<?php echo htmlspecialchars(json_encode($modelTipo->obtenerOpciones((int) $tv['id_tipo_variante']), JSON_UNESCAPED_UNICODE)); ?>">
                                <td class="text-muted py-3"><?php echo $contador++; ?></td>
                                <td class="fw-semibold text-dark py-3">
                                    <?php echo htmlspecialchars($tv['nombre']); ?>
                                    <span class="text-muted d-block" style="font-size:11px; font-weight:400;">código: <?php echo htmlspecialchars($tv['codigo']); ?></span>
                                </td>
                                <td class="py-3">
                                    <?php if ($esSistema): ?>
                                        <span class="gp-badge-success">Sistema</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border">Personalizado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <?php if ($esLibre): ?>
                                        <span class="text-muted" style="font-size:13px;">Etiqueta escrita en la modal</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($opcionesTipo as $op): ?>
                                                <span class="badge rounded-pill bg-light text-dark border d-inline-flex align-items-center gap-1"
                                                      style="font-size:12px; padding:.35em .65em;">
                                                    <?php echo htmlspecialchars($op['nombre']); ?>
                                                    <button type="button" class="btn btn-link p-0 op-edit-btn" title="Editar opción"
                                                            data-id="<?php echo $op['id_opcion']; ?>"
                                                            data-nombre="<?php echo htmlspecialchars($op['nombre']); ?>"
                                                            <?php if ($esSistema): ?>data-tsistema="<?php echo htmlspecialchars($tv['codigo']); ?>"<?php endif; ?>
                                                            style="font-size:12px;"><i class="bi bi-pencil-fill text-primary"></i></button>
                                                    <button type="button" class="btn btn-link p-0 op-del-btn" title="Eliminar opción"
                                                            data-id="<?php echo $op['id_opcion']; ?>"
                                                            data-nombre="<?php echo htmlspecialchars($op['nombre']); ?>"
                                                            <?php if ($esSistema): ?>data-tsistema="<?php echo htmlspecialchars($tv['codigo']); ?>"<?php endif; ?>
                                                            style="font-size:12px;"><i class="bi bi-x-circle-fill text-danger"></i></button>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <span class="<?php echo $tv['estado'] == 1 ? 'gp-badge-success' : 'gp-badge-danger'; ?>">
                                        <?php echo $tv['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="text-end py-3">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-link text-muted p-1 hover-text-primary toggle-tipo-btn"
                                                data-id="<?php echo $tv['id_tipo_variante']; ?>"
                                                title="<?php echo $tv['estado'] == 1 ? 'Desactivar' : 'Activar'; ?>">
                                            <i class="bi bi-power"></i>
                                        </button>
                                        <?php if (!$esSistema): ?>
                                            <button class="btn btn-link text-muted p-1 hover-text-primary edit-tipo-btn"
                                                    data-id="<?php echo $tv['id_tipo_variante']; ?>"
                                                    title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button class="btn btn-link text-muted p-1 hover-text-danger delete-tipo-btn"
                                                    data-id="<?php echo $tv['id_tipo_variante']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($tv['nombre']); ?>"
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

<!-- Modal: Nuevo Tipo de Variante -->
<div class="modal fade" id="nuevoTipoModal" tabindex="-1" aria-labelledby="nuevoTipoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="nuevoTipoModalLabel">Nuevo Tipo de Variante</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <form id="formNuevoTipo">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="new_nombre_tipo" class="form-label fw-semibold" style="font-size: 13px;">Nombre del tipo</label>
                        <input type="text" class="form-control" id="new_nombre_tipo" placeholder="Ej. Color, Material" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label for="new_modo_tipo" class="form-label fw-semibold" style="font-size: 13px;">¿Cómo se eligen las opciones?</label>
                        <select class="form-select" id="new_modo_tipo" onchange="actualizarModo('new')">
                            <option value="lista" selected>Lista de opciones (se marca cuáles aplican)</option>
                            <option value="libre">Etiqueta escrita (se escribe a mano al crear el producto)</option>
                        </select>
                    </div>
                    <div id="new_panel_opciones">
                        <label class="form-label fw-semibold" style="font-size: 13px;">Opciones</label>
                        <div id="new_opciones_rows" class="d-flex flex-column gap-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="new_add_opcion">
                            <i class="bi bi-plus-lg"></i> Añadir opción
                        </button>
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

<!-- Modal: Editar Tipo de Variante -->
<div class="modal fade" id="editarTipoModal" tabindex="-1" aria-labelledby="editarTipoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="editarTipoModalLabel">Editar Tipo de Variante</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <form id="formEditarTipo">
                <input type="hidden" id="edit_id_tipo">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_nombre_tipo" class="form-label fw-semibold" style="font-size: 13px;">Nombre del tipo</label>
                        <input type="text" class="form-control" id="edit_nombre_tipo" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label for="edit_modo_tipo" class="form-label fw-semibold" style="font-size: 13px;">¿Cómo se eligen las opciones?</label>
                        <select class="form-select" id="edit_modo_tipo" onchange="actualizarModo('edit')">
                            <option value="lista">Lista de opciones (se marca cuáles aplican)</option>
                            <option value="libre">Etiqueta escrita (se escribe a mano al crear el producto)</option>
                        </select>
                    </div>
                    <div id="edit_panel_opciones">
                        <label class="form-label fw-semibold" style="font-size: 13px;">Opciones</label>
                        <div id="edit_opciones_rows" class="d-flex flex-column gap-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="edit_add_opcion">
                            <i class="bi bi-plus-lg"></i> Añadir opción
                        </button>
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

<script>
    function crearFilaOpcion(contenedor, valor) {
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-2';
        row.innerHTML = `
            <input type="text" class="form-control form-control-sm opc-nombre" placeholder="Nombre de la opción (ej. Rojo)" value="${valor || ''}" style="font-size:13px;">
            <button type="button" class="btn btn-sm btn-outline-danger opc-del" title="Quitar"><i class="bi bi-x-lg"></i></button>
        `;
        row.querySelector('.opc-del').addEventListener('click', () => row.remove());
        contenedor.appendChild(row);
    }

    function actualizarModo(prefix) {
        const modo = document.getElementById(prefix + '_modo_tipo').value;
        const panel = document.getElementById(prefix + '_panel_opciones');
        panel.style.display = modo === 'lista' ? '' : 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Filtrado de la tabla
        const searchInput = document.getElementById('searchTipos');
        const rows = document.querySelectorAll('.tipo-row');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.toLowerCase().trim();
                rows.forEach(row => {
                    row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
                });
            });
        }

        // Modal nuevo: añadir opciones y reset
        const formNuevo = document.getElementById('formNuevoTipo');
        if (formNuevo) {
            document.getElementById('nuevoTipoModal').addEventListener('show.bs.modal', () => {
                document.getElementById('new_nombre_tipo').value = '';
                document.getElementById('new_modo_tipo').value = 'lista';
                document.getElementById('new_opciones_rows').innerHTML = '';
                crearFilaOpcion(document.getElementById('new_opciones_rows'), null);
                actualizarModo('new');
            });
            document.getElementById('new_add_opcion').addEventListener('click', () => {
                crearFilaOpcion(document.getElementById('new_opciones_rows'), null);
            });
            formNuevo.addEventListener('submit', async (e) => {
                e.preventDefault();
                const nombre = document.getElementById('new_nombre_tipo').value.trim();
                const modo = document.getElementById('new_modo_tipo').value;
                const opciones = Array.from(document.querySelectorAll('#new_opciones_rows .opc-nombre'))
                    .map(i => i.value.trim()).filter(x => x !== '');
                if (modo === 'lista' && opciones.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'Agrega al menos una opción.' });
                    return;
                }
                try {
                    const response = await fetch('./controllers/C_TipoVariante.php?action=crear', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ nombre, modo, opciones: opciones.map(n => ({ nombre: n })) })
                    });
                    const data = await response.json();
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: '¡Creado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#23284E' });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // Modal editar
        const editModal = new bootstrap.Modal(document.getElementById('editarTipoModal'));
        document.querySelectorAll('.edit-tipo-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('.tipo-row');
                document.getElementById('edit_id_tipo').value = row.dataset.id;
                document.getElementById('edit_nombre_tipo').value = row.dataset.nombre;
                document.getElementById('edit_modo_tipo').value = row.dataset.modo;
                const contenedor = document.getElementById('edit_opciones_rows');
                contenedor.innerHTML = '';
                let opciones = [];
                try { opciones = JSON.parse(row.dataset.opciones || '[]'); } catch (e) { opciones = []; }
                if (opciones.length === 0) crearFilaOpcion(contenedor, null);
                else opciones.forEach(op => crearFilaOpcion(contenedor, op.nombre));
                actualizarModo('edit');
                editModal.show();
            });
        });
        document.getElementById('edit_add_opcion').addEventListener('click', () => {
            crearFilaOpcion(document.getElementById('edit_opciones_rows'), null);
        });

        const formEditar = document.getElementById('formEditarTipo');
        if (formEditar) {
            formEditar.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id_tipo_variante = document.getElementById('edit_id_tipo').value;
                const nombre = document.getElementById('edit_nombre_tipo').value.trim();
                const modo = document.getElementById('edit_modo_tipo').value;
                const opciones = Array.from(document.querySelectorAll('#edit_opciones_rows .opc-nombre'))
                    .map(i => i.value.trim()).filter(x => x !== '');
                if (modo === 'lista' && opciones.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'Agrega al menos una opción.' });
                    return;
                }
                try {
                    const response = await fetch('./controllers/C_TipoVariante.php?action=actualizar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id_tipo_variante, nombre, modo, opciones: opciones.map(n => ({ nombre: n })) })
                    });
                    const data = await response.json();
                    if (data.success) {
                        editModal.hide();
                        Swal.fire({ icon: 'success', title: '¡Actualizado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#23284E' });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // Editar una opción individual
        document.querySelectorAll('.op-edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                Swal.fire({
                    title: 'Editar opción',
                    input: 'text',
                    inputLabel: 'Nombre de la opción',
                    inputValue: btn.dataset.nombre,
                    showCancelButton: true,
                    confirmButtonColor: '#23284E',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    inputValidator: (value) => {
                        if (!value || !value.trim()) return 'El nombre no puede estar vacío.';
                    }
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('./controllers/C_TipoVariante.php?action=editar_opcion', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    id_opcion: btn.dataset.id,
                                    nombre: result.value.trim(),
                                    tipo_sistema: btn.dataset.tsistema || ''
                                })
                            });
                            const data = await response.json();
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: '¡Actualizado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                                    .then(() => window.location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#23284E' });
                            }
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                        }
                    }
                });
            });
        });

        // Eliminar una opción individual
        document.querySelectorAll('.op-del-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const nombre = btn.dataset.nombre;
                Swal.fire({
                    title: '¿Eliminar opción?',
                    text: `Se eliminará la opción "${nombre}". Los productos que la usen conservarán el valor actual.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('./controllers/C_TipoVariante.php?action=eliminar_opcion', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id_opcion: btn.dataset.id, tipo_sistema: btn.dataset.tsistema || '' })
                            });
                            const data = await response.json();
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: '¡Eliminada!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                                    .then(() => window.location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#23284E' });
                            }
                        } catch (error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                        }
                    }
                });
            });
        });

        // Toggle estado
        document.querySelectorAll('.toggle-tipo-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    const response = await fetch('./controllers/C_TipoVariante.php?action=toggle', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id_tipo_variante: btn.dataset.id })
                    });
                    const data = await response.json();
                    if (data.success) window.location.reload();
                    else Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#23284E' });
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        });

        // Eliminar
        document.querySelectorAll('.delete-tipo-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const nombre = btn.dataset.nombre;
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `Deseas eliminar el tipo de variante "${nombre}"`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('./controllers/C_TipoVariante.php?action=eliminar', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id_tipo_variante: id })
                            });
                            const data = await response.json();
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: '¡Eliminado!', text: data.mensaje, showConfirmButton: false, timer: 1500 })
                                    .then(() => window.location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#23284E' });
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