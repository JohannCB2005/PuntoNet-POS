import re

with open('tienda.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Head changes
content = content.replace('<title>Tienda Online - Granja UNP</title>', '<title>NISSI STORE | Tienda de Uniformes</title>')
content = content.replace('<link rel="icon" type="image/png" href="assets/logo_unp.png">', '<link rel="icon" type="image/png" href="assets/Logo navegador PuntoNet.png">')

# 2. CSS variables
content = content.replace('--primary: #15803d;', '--primary: #1d4ed8;\n            --bg-main: #f0f4ff;')
content = content.replace('--primary-hover: #166534;', '--primary-hover: #1e40af;')
content = content.replace('background-color: #f9fafb;', 'background-color: var(--bg-main);')

# 3. Hero overlay
content = content.replace('rgba(21, 128, 61, 0.85) 0%, rgba(6, 78, 59, 0.9) 100%', 'rgba(29, 78, 216, 0.85) 0%, rgba(30, 64, 175, 0.9) 100%')
content = content.replace('rgba(21, 128, 61, 0.15)', 'rgba(29, 78, 216, 0.15)')

# 4. Product stock badge
content = content.replace('color: #059669; background: #d1fae5;', 'color: #1d4ed8; background: #dbeafe;')

# 5. Navbar and Toggle Button
navbar_old = '''<button class="btn btn-light rounded-pill px-3 position-relative border shadow-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#filtrosOffcanvas" id="btnFiltrarNavbar" style="height: 38px;" title="Filtrar productos">
                    <i class="bi bi-sliders text-success"></i>
                    <span class="fw-semibold text-dark fs-6">Filtros</span>
                    <span id="filtrosCountBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light d-none" style="font-size: 0.65rem; padding: 3px 6px;">0</span>
                </button>

                <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                    <img src="assets/logo_unp.png" alt="Logo" height="30" onerror="this.src='https://via.placeholder.com/30?text=UNP'">
                    <span>Granja UNP <small class="text-muted fw-normal fs-6">Click & Collect</small></span>
                </a>'''

navbar_new = '''<button id="btnToggleSidebar" class="btn btn-light border rounded-2 px-2 py-1 position-relative" 
                        onclick="toggleSidebar()" title="Filtros" style="height:38px; width:45px;">
                    <i class="bi bi-list fs-4 text-primary"></i>
                    <span id="filtrosCountBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light d-none" style="font-size: 0.65rem; padding: 3px 6px;">0</span>
                </button>

                <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                    <img src="assets/Logo navegador PuntoNet.png" alt="PuntoNet" height="30">
                    <span>NISSI <small class="text-muted fw-normal fs-6">STORE</small></span>
                </a>'''
content = content.replace(navbar_old, navbar_new)

# 6. Hero Text
hero_old = '''<h1>Productos Frescos Directo a ti</h1>
            <p>Reserva online, paga por Yape y recoge en la Granja UNP.</p>'''
hero_new = '''<h1>Bienvenido a NISSI STORE</h1>
            <p>Encuentra los mejores uniformes escolares. Reserva online y recoge en tienda.</p>'''
content = content.replace(hero_old, hero_new)

# 7. Add Sidebar CSS and remove float button CSS
css_old_btn = '''/* Botón de filtrar flotante */
        .btn-filtrar-flotante {
            position: fixed;
            bottom: 25px;
            left: 25px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 24px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(21, 128, 61, 0.4);
            z-index: 1030;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-filtrar-flotante:hover {
            background-color: var(--primary-hover);
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 6px 20px rgba(21, 128, 61, 0.5);
            color: white;
        }
        .btn-filtrar-flotante:active {
            transform: translateY(0) scale(0.97);
        }'''

css_sidebar = '''/* Sidebar integrado */
        .filtros-sidebar-inline {
            width: 280px;
            min-width: 280px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 24px 20px;
            position: sticky;
            top: 80px;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            transform-origin: left;
        }
        .filtros-sidebar-inline.sidebar-hidden {
            width: 0;
            min-width: 0;
            padding: 0;
            border: none;
            overflow: hidden;
            margin-right: -1rem; /* Adjust gap when hidden */
            opacity: 0;
        }
        @media (max-width: 768px) {
            #mainLayout { flex-direction: column; }
            .filtros-sidebar-inline {
                width: 100%;
                min-width: 100%;
                position: static;
                max-height: none;
                margin-bottom: 20px;
            }
            .filtros-sidebar-inline.sidebar-hidden {
                display: none;
                margin-right: 0;
            }
        }'''
content = content.replace(css_old_btn, css_sidebar)

# 8. Main Layout Wrapper
main_wrapper_old = '''<!-- Catálogo -->
        <div class="row g-4 my-4" id="catalogoContainer">
            <!-- Cargando -->
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-success" role="status"></div>
                <p class="mt-2 text-muted fw-semibold">Cargando catálogo...</p>
            </div>
        </div>
    </div>'''

main_wrapper_new = '''<!-- Catálogo y Sidebar Layout -->
        <div class="d-flex gap-4 align-items-start" id="mainLayout">
            <!-- SIDEBAR DE FILTROS -->
            <aside id="filtrosSidebar" class="filtros-sidebar-inline">
                <!-- Filtros content will be moved here -->
                FILTROS_PLACEHOLDER
            </aside>

            <!-- CATÁLOGO -->
            <div id="catalogoWrapper" class="flex-grow-1 w-100">
                <div class="row g-4 mb-4" id="catalogoContainer">
                    <!-- Cargando -->
                    <div class="col-12 text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted fw-semibold">Cargando catálogo...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>'''
content = content.replace(main_wrapper_old, main_wrapper_new)

# 9. Move offcanvas content to placeholder and delete offcanvas wrapper
offcanvas_regex = re.compile(r'<!-- Offcanvas de Filtros -->\s*<div class="offcanvas offcanvas-start" tabindex="-1" id="filtrosOffcanvas">\s*<div class="offcanvas-header bg-light">.*?</div>\s*<div class="offcanvas-body">(.*?)</div>\s*</div>', re.DOTALL)
match = offcanvas_regex.search(content)
if match:
    filtros_content = match.group(1)
    content = content.replace(match.group(0), '')
    content = content.replace('FILTROS_PLACEHOLDER', filtros_content)
else:
    print("Could not find offcanvas content")

# 10. Fix text-success to text-primary
content = content.replace('text-success', 'text-primary')
content = content.replace('btn-success', 'btn-primary')
content = content.replace('btn-outline-secondary w-100', 'btn-outline-primary w-100')

# 11. Add toggle function
script_add = '''
        let sidebarVisible = true;
        function toggleSidebar() {
            sidebarVisible = !sidebarVisible;
            document.getElementById('filtrosSidebar').classList.toggle('sidebar-hidden', !sidebarVisible);
        }
'''
content = content.replace('let catalogo = [];', script_add + '\n        let catalogo = [];')

with open('tienda.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("tienda.php modified successfully.")
