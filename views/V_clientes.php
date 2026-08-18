<?php
// Validar que exista una sesión activa
if (!isset($_SESSION['id_usuario'])) {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

require_once dirname(__DIR__) . '/models/M_Cliente.php';

$modelCliente = M_Cliente::singleton();
$clientes = $modelCliente->listarClientes();
?>

<div class="container-fluid px-0">
    <!-- Encabezado de Página -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Clientes</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Administra la información de los compradores y clientes.</p>
        </div>
        <button class="gp-btn-primary d-flex align-items-center gap-2 border-0" data-bs-toggle="modal" data-bs-target="#nuevoClienteModal">
            <i class="bi bi-plus-lg"></i>
            <span>Nuevo Cliente</span>
        </button>
    </div>

    <!-- Panel de Clientes -->
    <div class="gp-card">
        <!-- Barra de Búsqueda -->
        <div class="row mb-3">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted" id="search-addon">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0 text-sm" id="searchClientes" placeholder="Buscar cliente..." aria-label="Buscar" aria-describedby="search-addon" style="box-shadow: none; font-size: 14px;">
                </div>
            </div>
        </div>

        <!-- Tabla del Listado de Clientes -->
        <div class="table-responsive">
            <table class="table align-middle text-sm" id="tableClientes" style="font-size: 14px;">
                <thead>
                    <tr class="text-muted border-bottom" style="font-size: 13px;">
                        <th scope="col" class="pb-3">Nombres / Razón Social</th>
                        <th scope="col" class="pb-3">N° Documento</th>
                        <th scope="col" class="pb-3">Teléfono</th>
                        <th scope="col" class="pb-3">Dirección</th>
                        <th scope="col" class="pb-3">Tipo Cliente</th>
                        <th scope="col" class="pb-3">Estado</th>
                        <th scope="col" class="pb-3 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-person-badge-fill fs-2 mb-2 d-block"></i>
                                No se encontraron clientes.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cli): ?>
                            <tr class="border-bottom cliente-row">
                                <td class="py-3">
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-dark"><?php
                                            $ap = $cli['apellidos'] ?? '';
                                            $nm = $cli['nombres_razon_social'] ?? '';
                                            echo htmlspecialchars($ap ? $ap . ', ' . $nm : $nm);
                                        ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-dark fw-medium"><?php echo htmlspecialchars($cli['numero_documento'] ?? ''); ?></span>
                                        <small class="text-muted" style="font-size: 11px;"><?php echo $cli['tipo_documento'] == 1 ? 'DNI' : 'RUC'; ?></small>
                                    </div>
                                </td>
                                <td class="text-muted">
                                    <?php echo htmlspecialchars(($cli['telefono'] ?? '') ? $cli['telefono'] : '-'); ?>
                                </td>
                                <td class="text-muted" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars(($cli['direccion'] ?? '') ? $cli['direccion'] : '-'); ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-2.5 py-1.5 fw-semibold" style="font-size: 11px;">
                                        <?php 
                                            if ($cli['tipo_cliente'] == 1) echo 'Persona Natural';
                                            elseif ($cli['tipo_cliente'] == 2) echo 'Persona Jurídica';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $cli['estado'] == 1 ? 'gp-badge-success' : 'gp-badge-danger'; ?>">
                                        <?php echo $cli['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Cargar los datos del cliente en atributos data para mapeo del script -->
                                        <button class="btn btn-link text-muted p-1 hover-text-primary edit-cliente-btn" 
                                                data-id="<?php echo $cli['id_cliente']; ?>"
                                                data-tipodoc="<?php echo $cli['tipo_documento']; ?>"
                                                data-numdoc="<?php echo htmlspecialchars($cli['numero_documento'] ?? ''); ?>"
                                                data-nombres="<?php echo htmlspecialchars($cli['nombres_razon_social'] ?? ''); ?>"
                                                data-apellidos="<?php echo htmlspecialchars($cli['apellidos'] ?? ''); ?>"
                                                data-direccion="<?php echo htmlspecialchars($cli['direccion'] ?? ''); ?>"
                                                data-telefono="<?php echo htmlspecialchars($cli['telefono'] ?? ''); ?>"
                                                data-tipocli="<?php echo $cli['tipo_cliente']; ?>"
                                                title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <!-- No se permite eliminar lógicamente al Cliente ID 1 (Público General) para no romper el flujo de ventas -->
                                        <?php if ($cli['id_cliente'] != 1): ?>
                                            <button class="btn btn-link text-muted p-1 hover-text-danger delete-cliente-btn" 
                                                    data-id="<?php echo $cli['id_cliente']; ?>"
                                                    data-nombre="<?php
                                                        $ap = $cli['apellidos'] ?? '';
                                                        $nm = $cli['nombres_razon_social'] ?? '';
                                                        echo htmlspecialchars($ap ? $ap . ', ' . $nm : $nm);
                                                    ?>"
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

<!-- Modal: Nuevo Cliente -->
<div class="modal fade" id="nuevoClienteModal" tabindex="-1" aria-labelledby="nuevoClienteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="nuevoClienteModalLabel">Nuevo Cliente</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body p-4" style="font-size: 13px;">
                <!-- Campo oculto requerido para compatibilidad de registro -->
                <input type="hidden" id="new_direccion" value="">

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Tipo Doc. Identidad <span class="text-danger">*</span></label>
                        <select class="form-select" id="new_tipo_doc" style="box-shadow: none; height: 38px; font-size: 13.5px;">
                            <option value="1" selected>DNI</option>
                            <option value="2">RUC</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Número <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="new_num_doc" placeholder="Ej. 78945612" style="box-shadow: none; height: 38px; font-size: 13.5px;" autocomplete="off">
                            <button type="button" class="btn gp-bg-primary text-white fw-semibold d-flex align-items-center gap-1 px-3" id="new_searchApiBtn" style="height: 38px; border: none;">
                                <i class="bi bi-search"></i> <span id="new_searchApiBtnText">RENIEC</span>
                            </button>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Nombres / Razón Social <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_nombres" placeholder="Nombres o Razón Social" style="box-shadow: none; height: 38px; font-size: 13.5px;" autocomplete="off">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Apellidos (Opcional)</label>
                        <input type="text" class="form-control" id="new_apellidos" placeholder="Apellidos" style="box-shadow: none; height: 38px; font-size: 13.5px;" autocomplete="off">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Tipo de cliente</label>
                        <select class="form-select" id="new_tipo_cli" style="box-shadow: none; height: 38px; font-size: 13.5px;">
                            <option value="1" selected>Natural</option>
                            <option value="2">Jurídica</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted fw-semibold mb-1">Teléfono</label>
                        <input type="text" class="form-control" id="new_telefono" placeholder="Teléfono / Celular" style="box-shadow: none; height: 38px; font-size: 13.5px;" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-2" style="border-radius: 0 0 15px 15px;">
                <button type="button" class="btn btn-light fw-semibold px-4" data-bs-dismiss="modal" style="font-size: 13.5px; height: 38px;">Cancelar</button>
                <button type="button" class="gp-btn-primary border-0 fw-semibold px-4" id="new_saveClientBtn" style="font-size: 13.5px; height: 38px;">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar Cliente -->
<div class="modal fade" id="editarClienteModal" tabindex="-1" aria-labelledby="editarClienteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="editarClienteModalLabel">Editar Registro</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <form id="formEditarCliente">
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
                            <label for="edit_nombres" class="form-label fw-semibold" style="font-size: 13px;">Nombres / Razón Social</label>
                            <input type="text" class="form-control" id="edit_nombres" required autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="edit_apellidos" class="form-label fw-semibold" style="font-size: 13px;">Apellidos (Opcional)</label>
                            <input type="text" class="form-control" id="edit_apellidos" autocomplete="off">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="edit_telefono" class="form-label fw-semibold" style="font-size: 13px;">Teléfono</label>
                            <input type="text" class="form-control" id="edit_telefono" autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="edit_tipo_cli" class="form-label fw-semibold" style="font-size: 13px;">Tipo Cliente</label>
                            <select class="form-select" id="edit_tipo_cli" required>
                                <option value="1">Persona Natural</option>
                                <option value="2">Persona Jurídica</option>
                            </select>
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

<!-- JavaScript para Operaciones CRUD de Clientes -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Filtrado dinámico de clientes (barra de búsqueda)
        const searchInput = document.getElementById('searchClientes');
        const rows = document.querySelectorAll('.cliente-row');

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

        // 2. Elementos del formulario para consulta RENIEC/SUNAT API
        const new_tipoDoc     = document.getElementById('new_tipo_doc');
        const new_numDoc      = document.getElementById('new_num_doc');
        const new_searchBtn   = document.getElementById('new_searchApiBtn');
        const new_searchTxt   = document.getElementById('new_searchApiBtnText');
        const new_nombres     = document.getElementById('new_nombres');
        const new_tipoCli     = document.getElementById('new_tipo_cli');
        const new_direccion   = document.getElementById('new_direccion');
        const new_saveBtn     = document.getElementById('new_saveClientBtn');
        const new_planillaFields = document.getElementById('new_planilla_fields');

        // Toggle campos de planilla
        new_tipoCli.addEventListener('change', () => {
            if (new_tipoCli.value === '3') {
                new_planillaFields.style.display = 'block';
            } else {
                new_planillaFields.style.display = 'none';
            }
        });

        const edit_tipoCli = document.getElementById('edit_tipo_cli');
        const edit_planillaFields = document.getElementById('edit_planilla_fields');
        edit_tipoCli.addEventListener('change', () => {
            if (edit_tipoCli.value === '3') {
                edit_planillaFields.style.display = 'flex';
            } else {
                edit_planillaFields.style.display = 'none';
            }
        });

        // Alternar el texto del botón y resetear inputs al cambiar de tipo de documento
        new_tipoDoc.addEventListener('change', () => {
            new_searchTxt.innerText = new_tipoDoc.value === '1' ? 'RENIEC' : 'SUNAT';
            new_numDoc.value = '';
            new_nombres.value = '';
        });

        // Búsqueda remota a través del endpoint API de C_Cliente
        new_searchBtn.addEventListener('click', async () => {
            const docNum  = new_numDoc.value.trim();
            const docType = new_tipoDoc.value;

            if (!docNum) {
                Swal.fire({ icon:'warning', title:'Número requerido', text:'Ingrese el número de documento.', confirmButtonColor:'#23284E' });
                return;
            }
            if (docType === '1' && docNum.length !== 8) {
                Swal.fire({ icon:'warning', title:'DNI inválido', text:'El DNI debe tener exactamente 8 dígitos.', confirmButtonColor:'#23284E' });
                return;
            }
            if (docType === '2' && docNum.length !== 11) {
                Swal.fire({ icon:'warning', title:'RUC inválido', text:'El RUC debe tener exactamente 11 dígitos.', confirmButtonColor:'#23284E' });
                return;
            }

            new_searchBtn.disabled = true;
            const orig = new_searchTxt.innerText;
            new_searchTxt.innerText = 'Buscando...';

            try {
                const res  = await fetch('./controllers/C_Cliente.php?action=buscar_api_only', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ numero_documento: docNum })
                });
                const data = await res.json();

                if (data.success) {
                    new_nombres.value   = data.data.nombres  || data.data.nombre || '';
                    document.getElementById('new_apellidos').value = data.data.apellidos || '';
                    new_direccion.value = data.data.direccion || '';
                    new_tipoCli.value   = data.data.tipo_cliente || '1';
                    Swal.fire({ icon:'success', title:'¡Datos obtenidos!', text:'Se cargaron los datos automáticamente.', showConfirmButton:false, timer:1400 });
                } else {
                    Swal.fire({ icon:'error', title:'No encontrado', text: data.mensaje, confirmButtonColor:'#23284E' });
                }
            } catch (err) {
                Swal.fire({ icon:'error', title:'Error de red', text:'No se pudo conectar al servidor.', confirmButtonColor:'#23284E' });
            } finally {
                new_searchBtn.disabled = false;
                new_searchTxt.innerText = orig;
            }
        });

        // Forzar a que solo se ingresen números en el campo de documento
        new_numDoc.addEventListener('input', () => {
            new_numDoc.value = new_numDoc.value.replace(/\D/g, '');
        });

        // Registrar un nuevo cliente localmente
        new_saveBtn.addEventListener('click', async () => {
            const tipo_documento       = new_tipoDoc.value;
            const numero_documento     = new_numDoc.value.trim();
            const nombres_razon_social = new_nombres.value.trim();
            const apellidos            = document.getElementById('new_apellidos').value.trim();
            const telefono             = document.getElementById('new_telefono').value.trim();
            const tipo_cliente         = new_tipoCli.value;
            const direccion            = new_direccion.value.trim();
            
            if (!numero_documento || !nombres_razon_social) {
                Swal.fire({ icon:'warning', title:'Campos obligatorios', text:'El número de documento y el nombre son requeridos.', confirmButtonColor:'#23284E' });
                return;
            }

            new_saveBtn.disabled = true;
            try {
                const response = await fetch('./controllers/C_Cliente.php?action=crear', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        tipo_documento, numero_documento, nombres_razon_social, apellidos, 
                        telefono, tipo_cliente, direccion
                    })
                });
                const data = await response.json();

                if (data.success) {
                    Swal.fire({ icon:'success', title:'¡Creado!', text: data.mensaje, showConfirmButton:false, timer:1500 })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon:'error', title:'Error', text: data.mensaje, confirmButtonColor:'#23284E' });
                }
            } catch (error) {
                Swal.fire({ icon:'error', title:'Error', text:'No se pudo conectar al servidor.' });
            } finally {
                new_saveBtn.disabled = false;
            }
        });

        // Resetear campos del modal cuando se cierra
        document.getElementById('nuevoClienteModal').addEventListener('hidden.bs.modal', () => {
            new_numDoc.value    = '';
            new_nombres.value   = '';
            new_direccion.value = '';
            new_tipoCli.value   = '1';
            new_tipoDoc.value   = '1';
            new_searchTxt.innerText = 'RENIEC';
            document.getElementById('new_apellidos').value = '';
            document.getElementById('new_telefono').value  = '';
        });

        // Rellenar campos y abrir modal para edición de cliente
        const editModal = new bootstrap.Modal(document.getElementById('editarClienteModal'));
        document.querySelectorAll('.edit-cliente-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('edit_id').value = btn.dataset.id;
                document.getElementById('edit_tipo_doc').value = btn.dataset.tipodoc;
                document.getElementById('edit_num_doc').value = btn.dataset.numdoc;
                
                let nombres = btn.dataset.nombres || '';
                let apellidos = btn.dataset.apellidos || '';
                if (!apellidos && nombres.indexOf(',') !== -1) {
                    const partes = nombres.split(',');
                    apellidos = partes[0].trim();
                    nombres = partes.slice(1).join(',').trim();
                }
                document.getElementById('edit_nombres').value = nombres;
                document.getElementById('edit_apellidos').value = apellidos;
                
                document.getElementById('edit_direccion').value = btn.dataset.direccion;
                document.getElementById('edit_telefono').value = btn.dataset.telefono;
                document.getElementById('edit_tipo_cli').value = btn.dataset.tipocli;
                
                editModal.show();
            });
        });

        // Confirmar y procesar cambios del cliente editado
        const formEditar = document.getElementById('formEditarCliente');
        if (formEditar) {
            formEditar.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id_cliente = document.getElementById('edit_id').value;
                const tipo_documento = document.getElementById('edit_tipo_doc').value;
                const numero_documento = document.getElementById('edit_num_doc').value.trim();
                const nombres_razon_social = document.getElementById('edit_nombres').value.trim();
                const apellidos = document.getElementById('edit_apellidos').value.trim();
                const telefono = document.getElementById('edit_telefono').value.trim();
                const tipo_cliente = document.getElementById('edit_tipo_cli').value;
                const direccion = document.getElementById('edit_direccion').value.trim();

                try {
                    const response = await fetch('./controllers/C_Cliente.php?action=actualizar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            id_cliente, tipo_documento, numero_documento, nombres_razon_social, apellidos, 
                            telefono, tipo_cliente, direccion
                        })
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
                            confirmButtonColor: '#23284E'
                        });
                    }
                } catch (error) {
                    Swal.fire({ icon:'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // Eliminar lógicamente un cliente del listado activo
        document.querySelectorAll('.delete-cliente-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id_cliente = btn.dataset.id;
                const nombre = btn.dataset.nombre;

                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `Deseas eliminar al cliente "${nombre}"`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#23284E',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('./controllers/C_Cliente.php?action=eliminar', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id_cliente })
                            });
                            const data = await response.json();

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Eliminado!',
                                    text: data.mensaje,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => window.location.reload());
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.mensaje,
                                    confirmButtonColor: '#23284E'
                                });
                            }
                        } catch (error) {
                            Swal.fire({ icon:'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                        }
                    }
                });
            });
        });
    });
</script>
