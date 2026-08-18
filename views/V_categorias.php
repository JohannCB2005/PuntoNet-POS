<?php
// Restringir el acceso: Solo usuarios logueados con rol de Administrador pueden ver esta vista
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    echo "<h1>Acceso denegado</h1>";
    exit;
}

// Cargar modelo de categorías y listar las categorías activas
require_once dirname(__DIR__) . '/models/M_Categoria.php';
$modelCat = M_Categoria::singleton();
$categorias = $modelCat->listar(); 
?>

<div class="container-fluid px-0">
    <!-- Encabezado de Página -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Categorías</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">Organiza los productos en categorías.</p>
        </div>
        <button class="gp-btn-primary d-flex align-items-center gap-2 border-0" data-bs-toggle="modal" data-bs-target="#nuevaCategoriaModal">
            <i class="bi bi-plus-lg"></i>
            <span>Nueva Categoría</span>
        </button>
    </div>

    <!-- Tarjeta Principal de Categorías -->
    <div class="gp-card">
        <!-- Barra de Búsqueda -->
        <div class="row mb-3">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted" id="search-addon">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0 text-sm" id="searchCategorias" placeholder="Buscar categoría..." aria-label="Buscar" aria-describedby="search-addon" style="box-shadow: none; font-size: 14px;">
                </div>
            </div>
        </div>

        <!-- Tabla de Categorías -->
        <div class="table-responsive">
            <table class="table align-middle text-sm" id="tableCategorias" style="font-size: 14px;">
                <thead>
                    <tr class="text-muted border-bottom" style="font-size: 13px;">
                        <th scope="col" class="pb-3" style="width: 50px;">#</th>
                        <th scope="col" class="pb-3">Categoría</th>
                        <th scope="col" class="pb-3">Descripción</th>
                        <th scope="col" class="pb-3">Estado</th>
                        <th scope="col" class="pb-3 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorias)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-tags-fill fs-2 mb-2 d-block"></i>
                                No se encontraron categorías.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $contador = 1; ?>
                        <?php foreach ($categorias as $cat): ?>
                            <tr class="border-bottom category-row">
                                <td class="text-muted py-3">
                                    <?php echo $contador++; ?>
                                </td>
                                <td class="fw-semibold text-dark py-3">
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </td>
                                <td class="text-muted">
                                    <?php echo htmlspecialchars($cat['descripcion'] ? $cat['descripcion'] : '-'); ?>
                                </td>
                                <td>
                                    <span class="<?php echo $cat['estado'] == 1 ? 'gp-badge-success' : 'gp-badge-danger'; ?>">
                                        <?php echo $cat['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Botón Editar: Carga datos en data-attributes para el modal -->
                                        <button class="btn btn-link text-muted p-1 hover-text-primary edit-cat-btn" 
                                                data-id="<?php echo $cat['id_categoria']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($cat['nombre']); ?>"
                                                data-descripcion="<?php echo htmlspecialchars($cat['descripcion']); ?>"
                                                title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <!-- Botón Eliminar: Ejecuta la desactivación por ID -->
                                        <button class="btn btn-link text-muted p-1 hover-text-danger delete-cat-btn" 
                                                data-id="<?php echo $cat['id_categoria']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($cat['nombre']); ?>"
                                                title="Eliminar">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
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

<!-- Modal: Nueva Categoría -->
<div class="modal fade" id="nuevaCategoriaModal" tabindex="-1" aria-labelledby="nuevaCategoriaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="nuevaCategoriaModalLabel">Nueva Categoría</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <form id="formNuevaCategoria">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="new_nombre" class="form-label fw-semibold" style="font-size: 13px;">Nombre de categoría</label>
                        <input type="text" class="form-control" id="new_nombre" placeholder="Ej. Alimentos" required autocomplete="off">
                    </div>
                    <div>
                        <label for="new_descripcion" class="form-label fw-semibold" style="font-size: 13px;">Descripción</label>
                        <textarea class="form-control" id="new_descripcion" rows="3" placeholder="Breve descripción de los productos en esta categoría"></textarea>
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

<!-- Modal: Editar Categoría -->
<div class="modal fade" id="editarCategoriaModal" tabindex="-1" aria-labelledby="editarCategoriaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header gp-bg-primary text-white border-0 py-3" style="border-radius: 15px 15px 0 0;">
                <h6 class="modal-title fw-bold" id="editarCategoriaModalLabel">Editar Registro</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <form id="formEditarCategoria">
                <input type="hidden" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_nombre" class="form-label fw-semibold" style="font-size: 13px;">Nombre de categoría</label>
                        <input type="text" class="form-control" id="edit_nombre" required autocomplete="off">
                    </div>
                    <div>
                        <label for="edit_descripcion" class="form-label fw-semibold" style="font-size: 13px;">Descripción</label>
                        <textarea class="form-control" id="edit_descripcion" rows="3"></textarea>
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

<!-- JavaScript para Operaciones CRUD -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Filtrado dinámico de la tabla en base a la barra de búsqueda
        const searchInput = document.getElementById('searchCategorias');
        const rows = document.querySelectorAll('.category-row');

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

        // 2. Evento Submit para registrar Nueva Categoría
        const formNueva = document.getElementById('formNuevaCategoria');
        if (formNueva) {
            formNueva.addEventListener('submit', async (e) => {
                e.preventDefault();
                const nombre = document.getElementById('new_nombre').value.trim();
                const descripcion = document.getElementById('new_descripcion').value.trim();

                try {
                    const response = await fetch('./controllers/C_Categoria.php?action=crear', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ nombre, descripcion })
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
                            confirmButtonColor: '#23284E'
                        });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // 3. Manejador para rellenar campos y abrir el Modal de Edición
        const editModal = new bootstrap.Modal(document.getElementById('editarCategoriaModal'));
        document.querySelectorAll('.edit-cat-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('edit_id').value = btn.dataset.id;
                document.getElementById('edit_nombre').value = btn.dataset.nombre;
                document.getElementById('edit_descripcion').value = btn.dataset.descripcion;
                editModal.show();
            });
        });

        // 4. Evento Submit para guardar cambios de la Categoría editada
        const formEditar = document.getElementById('formEditarCategoria');
        if (formEditar) {
            formEditar.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id_categoria = document.getElementById('edit_id').value;
                const nombre = document.getElementById('edit_nombre').value.trim();
                const descripcion = document.getElementById('edit_descripcion').value.trim();

                try {
                    const response = await fetch('./controllers/C_Categoria.php?action=actualizar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id_categoria, nombre, descripcion })
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
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                }
            });
        }

        // 5. Manejador del Clic para desactivar/eliminar lógicamente una Categoría
        document.querySelectorAll('.delete-cat-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id_categoria = btn.dataset.id;
                const nombre = btn.dataset.nombre;

                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `Deseas eliminar la categoría "${nombre}"`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('./controllers/C_Categoria.php?action=eliminar', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id_categoria })
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
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar al servidor.' });
                        }
                    }
                });
            });
        });
    });
</script>
