# CODEMAP.md

Mapa de referencia rápida de la arquitectura de este repositorio. Léelo antes de explorar el código a mano.

## 1. Visión general

**NISSI POS** — sistema de punto de venta e inventario para una tienda de uniformes/módulos escolares (UNP), con dos frentes:

- **Panel administrativo (POS)**: gestión de inventario, ventas presenciales, caja, kardex, cotizaciones y reportes. Requiere login y control de roles.
- **Tienda virtual pública** (`tienda.php`): catálogo público estilo *Click & Collect* (el cliente reserva/paga online y recoge en tienda), con checkout y pago vía Stripe.

**Stack**:
- Backend: **PHP** estructurado/POO puro, sin framework (MVC "a mano" + patrón DAO). No usa Composer autoload para el código propio (cada archivo hace `require_once` explícito).
- Base de datos: **MySQL/MariaDB** vía **PDO** con consultas preparadas. Sin stored procedures ni `DELIMITER` (requisito de compatibilidad con hosting compartido InfinityFree). El esquema completo vive en [base_datos_nissi.sql](base_datos_nissi.sql).
- Frontend: HTML5 + Bootstrap 5 + Bootstrap Icons + SweetAlert2 + Chart.js (panel admin), todo servido como PHP renderizado en servidor con JS vanilla embebido inline en cada vista (no hay build step, ni bundler, ni SPA framework).
- Gestor de paquetes: **Composer**, usado solo para 2 librerías puntuales (ver sección 5). Ver [composer.json](composer.json).
- Pagos: integración directa con la API REST de Stripe vía cURL puro (sin SDK) para compatibilidad con hosting sin `vendor/autoload`.

No hay CI, no hay tests automatizados, no hay linter/formatter configurado.

## 2. Estructura de carpetas de alto nivel

- **`/controllers`** — Un archivo `C_<Entidad>.php` por módulo. Actúan como endpoints HTTP: reciben `$_GET['action']`/JSON body, validan sesión/rol, delegan al modelo correspondiente y devuelven JSON (`header('Content-Type: application/json')`). Son el "router" de cada recurso (no hay un router central de la app más allá del switch en `index.php`).
- **`/models`** — Un archivo `M_<Entidad>.php` por entidad, clase Singleton que encapsula todo el acceso a datos (SQL vía PDO) para esa entidad. Equivalente a la capa DAO.
- **`/entities`** — Clases de datos planas (`<Entidad>.php`) con propiedades públicas y constructor; se pasan entre controlador → modelo como contenedor de datos. Sin lógica de negocio.
- **`/views`** — Vistas PHP (`V_<modulo>.php`) del panel admin, una por módulo de `index.php`. Cada vista mezcla HTML + PHP + JS inline (fetch a los controllers). No hay separación de "templates" ni motor de plantillas.
  - **`/views/layouts`** — `header.php`, `sidebar.php`, `navbar.php`, `footer.php`: el shell común del panel admin, incluido manualmente por `index.php` en cada request.
  - **`/views/public`** — Vistas de la tienda pública fuera del panel admin (checkout, confirmación de compra). No pasan por `index.php` ni por el sistema de rutas/roles.
- **`/config`** — Configuración de infraestructura: conexión a BD ([conexion.php](config/conexion.php)), claves de Stripe ([stripe.php](config/stripe.php)), config de API externa ([api.php](config/api.php)).
- **`/migrations`** — Scripts SQL sueltos para cambios incrementales de esquema posteriores al script base (ej. variantes de talla). No hay herramienta de migraciones (no versionado/automatizado); son SQL que se corren a mano.
- **`/scripts`** — Utilidades PHP de mantenimiento/setup ejecutadas manualmente por CLI o navegador (reset de BD, setup de módulos, tests manuales). Ver [scripts/README.md](scripts/README.md).
- **`/assets`** — Estáticos: imágenes de marca/hero, `productos/` (imágenes de catálogo subidas por el admin vía upload). No hay `assets/js/`: todo el JS del panel y de la tienda está inline en las vistas.
- **`/vendor`** — Dependencias Composer (`phpoffice/phpspreadsheet` para exportar reportes a Excel, y sus dependencias transitivas).

## 3. Convenciones

- **Naming de archivos**: prefijo por capa — `C_` controlador, `M_` modelo, `V_` vista, sin prefijo en `/entities`. El nombre tras el prefijo es la entidad/módulo en PascalCase o snake_case según el archivo (ej. `C_Producto.php`, `V_nueva_venta.php`).
- **Patrón de organización**: por **capa/tipo** (controllers/models/entities/views), no por feature. Dentro de cada capa, un archivo por entidad de negocio.
- **Patrón de diseño repetido**: todos los `M_*` son **Singleton** (`::singleton()`), obtienen la conexión PDO vía `Conexion::singleton()->getConexion()`. Los controladores instancian el modelo, nunca la conexión directamente.
- **Idioma**: código, comentarios, nombres de variables/BD y mensajes de usuario están en **español**.
- **Estilo de código**: sin linter/formatter configurado (no hay `.php-cs-fixer`, `phpcs`, etc.). Indentación 4 espacios, comentarios de bloque tipo docblock ligero encima de clases/métodos explicando propósito en español.
- **Respuestas de controllers**: siempre JSON con forma `{"success": bool, "mensaje": "..."}` o `{"success": bool, "data": ...}`.
- **Seguridad por archivo**: cada controlador repite manualmente `session_start()`, cabeceras de seguridad y el chequeo de rol al inicio del archivo (no hay middleware central, salvo el gate de rutas en `index.php`).

## 4. Puntos de entrada clave

- **[index.php](index.php)** — entrada única del **panel administrativo**. Hace auth check por `$_SESSION['id_usuario']`, valida el `modulo` pedido por `$_GET['modulo']` contra una whitelist `$routes` con roles permitidos, y hace `require_once` de la vista correspondiente dentro del layout (header/sidebar/navbar/footer). Este switch es el único "router" del panel.
- **[tienda.php](tienda.php)** — entrada de la **tienda pública** (catálogo), independiente del panel admin, sin autenticación, consume `C_Ecommerce.php` vía fetch.
- **[views/public/V_checkout.php](views/public/V_checkout.php)** → **[views/public/V_checkout_success.php](views/public/V_checkout_success.php)** — flujo de checkout público, consume `C_PaymentIntent.php` (Stripe) y `C_Ecommerce.php?action=crear_pedido`.
- **[config/conexion.php](config/conexion.php)** — configuración principal de BD (clase `Conexion`). Detecta entorno (localhost vs InfinityFree) y auto-crea/inicializa el esquema desde `base_datos_nissi.sql` si la BD está vacía.
- **[config/stripe.php](config/stripe.php)** / **[config/api.php](config/api.php)** — claves de Stripe (lee de `.env`, con fallback hardcoded) y token de API externa (consulta DNI/RUC vía apiperu).
- **Rutas/endpoints**: no hay tabla de rutas HTTP tipo REST; cada `controllers/C_*.php` se invoca directo por su path y despacha internamente por `?action=...` (switch interno). Ver sección 6 para mapeo por dominio.

## 5. Dependencias importantes

- **`phpoffice/phpspreadsheet`** (Composer, en `/vendor`) — declarada para exportar/importar `.xlsx`, pero **actualmente sin ningún consumidor en el código**: su único uso (`C_Importar.php`, importación de catálogo desde Excel) fue eliminado por muerto. Candidata a quitarse de `composer.json` en una futura limpieza.
- **PDO (ext-pdo_mysql)** — única capa de acceso a datos, sin ORM.
- **Bootstrap 5 + Bootstrap Icons** (CDN, no npm) — UI de ambos frentes (admin y tienda).
- **SweetAlert2** (CDN) — todos los modales/alertas de confirmación y error, en vez de `alert()`/`confirm()` nativos.
- **Chart.js** (CDN) — gráficos del Dashboard y Reportes.
- **Stripe API REST** (cURL directo, sin SDK) — pagos online del checkout público.

## 6. "Si buscas X, está en Y"

| Buscas... | Está en... |
|---|---|
| Autenticación / login / control de sesión | [controllers/C_Login.php](controllers/C_Login.php), [controllers/C_Logout.php](controllers/C_Logout.php), [entities/Usuario.php](entities/Usuario.php), [models/M_Usuario.php](models/M_Usuario.php), gate de rutas en [index.php](index.php) |
| Conexión / esquema de BD | [config/conexion.php](config/conexion.php), [base_datos_nissi.sql](base_datos_nissi.sql), cambios incrementales en `/migrations` |
| Layout / estilos globales del panel admin (sidebar, navbar, variables CSS) | [views/layouts/header.php](views/layouts/header.php) (define `:root` con variables `--gp-*` y estilos globales inline), `views/layouts/sidebar.php`, `navbar.php` |
| Estilos de la tienda pública | bloque `<style>` inline dentro de [tienda.php](tienda.php) y de cada vista en `/views/public` (no comparten CSS con el panel admin) |
| Lógica de negocio de ventas / carrito POS | [controllers/C_Venta.php](controllers/C_Venta.php), [models/M_Venta.php](models/M_Venta.php), [views/V_nueva_venta.php](views/V_nueva_venta.php) |
| Catálogo público / carrito de la tienda / pedidos online | [controllers/C_Ecommerce.php](controllers/C_Ecommerce.php), [models/M_Ecommerce.php](models/M_Ecommerce.php), [tienda.php](tienda.php), [views/V_pedidos_online.php](views/V_pedidos_online.php) (aprobación/rechazo en panel admin) |
| Pagos / Stripe | [config/stripe.php](config/stripe.php), [controllers/C_PaymentIntent.php](controllers/C_PaymentIntent.php), [views/public/V_checkout.php](views/public/V_checkout.php) |
| Inventario / productos / variantes de talla | [controllers/C_Producto.php](controllers/C_Producto.php), [models/M_Producto.php](models/M_Producto.php), [entities/Producto.php](entities/Producto.php) (campos `es_agrupador`/`id_producto_padre` para variantes) |
| Kardex (movimientos de stock) | [controllers/C_Kardex.php](controllers/C_Kardex.php), [models/M_Kardex.php](models/M_Kardex.php) |
| Caja chica / arqueo | [controllers/C_Caja.php](controllers/C_Caja.php), [models/M_Caja.php](models/M_Caja.php), [views/V_caja.php](views/V_caja.php), [views/V_control_cajas.php](views/V_control_cajas.php) |
| Cotizaciones | [controllers/C_Cotizacion.php](controllers/C_Cotizacion.php), [models/M_Cotizacion.php](models/M_Cotizacion.php), [entities/Cotizacion.php](entities/Cotizacion.php), [entities/DetalleCotizacion.php](entities/DetalleCotizacion.php) |
| Reportes | [controllers/C_Reporte.php](controllers/C_Reporte.php), [models/M_Reporte.php](models/M_Reporte.php) |
| Impresión de comprobantes/tickets | [views/V_ticket_print.php](views/V_ticket_print.php), [views/V_cotizacion_print.php](views/V_cotizacion_print.php) |
| Tests | No existen tests automatizados. |
| Setup/reset de entorno local | `/scripts` (`reset_db.php`, `setup_ecommerce.php`) |

## 7. Cosas no obvias

- **No hay autoload propio**: cada archivo hace `require_once` explícito de sus dependencias (config → entities → models). Ningún controlador toca `vendor/autoload.php` hoy (ver nota sobre `phpspreadsheet` en la sección 5); el código deliberadamente evita Composer para poder correr en hosting compartido (InfinityFree) sin restricciones de `composer install`.
- **Auto-inicialización de BD**: si `Conexion` detecta una base de datos vacía (sin tablas) o inexistente, **crea la BD y ejecuta `base_datos_nissi.sql` automáticamente** en el primer request (ver `inicializarBaseDatos()` en `config/conexion.php`). Esto reemplaza deliberadamente el uso de stored procedures/`DELIMITER`, no soportados en InfinityFree.
- **Detección de entorno por `HTTP_HOST`**: no hay variable de entorno tipo `APP_ENV`; `conexion.php` decide credenciales de BD mirando si el host es `localhost`/`127.0.0.1`/CLI vs. producción (InfinityFree), con credenciales de producción **hardcodeadas como fallback** en el propio archivo.
- **Stripe sin SDK**: `C_PaymentIntent.php` llama a la API REST de Stripe con cURL crudo en vez de `stripe-php`, por la misma restricción de hosting compartido. Las claves se leen de `.env` (vía `parse_ini_file`, no una librería dotenv) con fallback hardcodeado a placeholders.
- **Productos con variantes de talla**: `productos` tiene una relación auto-referenciada (`es_agrupador` + `id_producto_padre`) para modelar "producto padre" (no vendible directamente) con variantes hijas por talla, en vez de una tabla de variantes separada. Ver [migrations/add_product_variants.sql](migrations/add_product_variants.sql) y `catalogo_agrupado` en `C_Ecommerce.php`/`M_Ecommerce.php`.
- **Carrito del ecommerce vive solo en el cliente**: el carrito de `tienda.php` se maneja en JS (array `cart` en memoria + `sessionStorage`) y solo se persiste en BD al confirmar el pedido (`crear_pedido`); el stock se "reserva" en ese momento, no al agregar al carrito.
- **Dos frentes de estilos distintos**: el panel admin usa la paleta `--gp-*` definida en `views/layouts/header.php`; la tienda pública (`tienda.php` y `views/public/*`) define su propia paleta `--primary`/`--bg-main` inline, sin compartir CSS ni variables con el panel. Si tocas estilos, confirma en cuál de los dos frentes estás.
- **`views/public/*` no pasa por `index.php`**: no tiene el gate de sesión/rol ni el layout admin; son páginas PHP standalone con su propio `<html>` completo, pensadas para clientes anónimos.
- **Roles hardcodeados como strings**: `'Administrador'` / `'Vendedor'` se comparan como strings literales en controllers, modelos y en el switch de `index.php` — no hay enum/constantes centralizadas para roles.
- **Módulo de personal universitario eliminado**: el subsistema de Trabajadores/Vales/Facultades/Reporte-Planilla (controllers, modelos, entidades y vistas `C_Trabajador`, `C_Vale`, `C_Facultad`, `C_TipoTrabajador`, `M_*` y `V_*` correspondientes) fue removido por estar completamente muerto: sin rutas en `index.php`, sin tablas en `base_datos_nissi.sql`, y `C_Vale.php` no tenía autenticación. Sigue disponible en el historial de git si se necesita reactivar.

---

Este archivo debe actualizarse cuando la estructura cambie significativamente (nuevas carpetas top-level, cambio de framework, nuevas convenciones).
