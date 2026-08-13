    </div> <!-- Cierre del contenedor principal (wrapper) -->

    <!-- Bootstrap 5 Bundle JS con dependencias de Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script de control interactivo de la barra lateral (Sidebar) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('toggle-sidebar');
            const mobileToggleBtn = document.getElementById('mobile-toggle-sidebar');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');

            // Abre/cierra el sidebar móvil junto con su fondo oscuro, y bloquea el
            // scroll del documento mientras está abierto (si no, al arrastrar sobre
            // el fondo se desplaza la página que hay detrás).
            const setSidebarMovil = (abierto) => {
                if (!sidebar) return;
                sidebar.classList.toggle('show', abierto);
                if (backdrop) backdrop.classList.toggle('show', abierto);
                document.body.style.overflow = abierto ? 'hidden' : '';
            };

            // Escritorio: alternar modo iconos. En tablet (992–1199px) el sidebar
            // arranca colapsado por CSS, así que ahí el botón añade `expanded`; en
            // pantallas grandes sigue funcionando con `collapsed` como siempre.
            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', () => {
                    const esTablet = window.matchMedia('(min-width: 992px) and (max-width: 1199.98px)').matches;
                    sidebar.classList.toggle(esTablet ? 'expanded' : 'collapsed');
                });
            }

            // Móvil: botón hamburguesa.
            if (mobileToggleBtn && sidebar) {
                mobileToggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    setSidebarMovil(!sidebar.classList.contains('show'));
                });
            }

            // Cerrar tocando el fondo oscuro.
            if (backdrop) {
                backdrop.addEventListener('click', () => setSidebarMovil(false));
            }

            // Cerrar tocando fuera (se mantiene el comportamiento que ya existía).
            document.addEventListener('click', (e) => {
                if (sidebar && sidebar.classList.contains('show') && !sidebar.contains(e.target) && e.target !== mobileToggleBtn) {
                    setSidebarMovil(false);
                }
            });

            // Cerrar con Escape: en móvil el sidebar tapa la pantalla completa.
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show')) {
                    setSidebarMovil(false);
                }
            });

            // Al volver a escritorio, deshacer el estado móvil: si no, el body se
            // quedaba con overflow:hidden y la página no scrolleaba.
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 992 && sidebar && sidebar.classList.contains('show')) {
                    setSidebarMovil(false);
                }
            });
        });
    </script>

    <!-- Script de despliegue/plegado por grupos del menú (acordeón) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const STORAGE_KEY = 'nissi_sidebar_grupos';
            const grupos = Array.from(document.querySelectorAll('.sidebar-menu .sidebar-group'));
            const activo = document.querySelector('.sidebar-menu .menu-item.active');

            const leerEstado = () => {
                try {
                    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
                } catch (e) {
                    return {};
                }
            };
            const guardarEstado = (nombre, abierto) => {
                try {
                    const estado = leerEstado();
                    estado[nombre] = abierto;
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(estado));
                } catch (e) { /* almacenamiento no disponible: ignora */ }
            };
            const aplicar = (grupo, abierto) => {
                grupo.classList.toggle('open', abierto);
                const header = grupo.querySelector('.menu-header');
                if (header) header.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            };

            grupos.forEach(grupo => {
                const nombre = grupo.dataset.group;
                const header = grupo.querySelector('.menu-header');
                if (!header) return;

                // Por defecto los grupos arrancan plegados (solo se ve el nombre de
                // la categoría); si el usuario ya eligió su estado, se respeta.
                aplicar(grupo, leerEstado()[nombre] === true);

                header.addEventListener('click', () => {
                    const abierto = grupo.classList.toggle('open');
                    header.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                    guardarEstado(nombre, abierto);
                });
            });

            // El grupo que contiene el módulo actual siempre se muestra desplegado,
            // aunque el usuario lo hubiera plegado antes (si no, el módulo en uso
            // quedaría oculto). No sobrescribe la preferencia guardada.
            if (activo) {
                const grupoActivo = activo.closest('.sidebar-group');
                if (grupoActivo) aplicar(grupoActivo, true);
            }
        });
    </script>
</body>
</html>
