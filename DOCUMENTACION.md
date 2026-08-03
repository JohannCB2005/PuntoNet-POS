# 📚 Documentación del Proyecto: PuntoNet — POS & Tienda Virtual

Este documento describe todas las capacidades, módulos y la arquitectura técnica del sistema **PuntoNet POS**, el cual integra un sistema de punto de venta (POS) administrativo con una tienda virtual pública (e-commerce) autogestionada.

---

## 🛠️ Arquitectura Técnica

El proyecto está diseñado bajo una arquitectura limpia y eficiente, optimizada para funcionar tanto en entornos locales (**XAMPP**) como en servidores de hosting gratuito restringidos (**InfinityFree**).

* **Backend**: PHP 8.x estructurado bajo el patrón **Modelo-Vista-Controlador (MVC)** nativo (sin frameworks pesados).
* **Base de Datos**: MySQL/MariaDB con transacciones explícitas de PDO (sin procedimientos almacenados para garantizar compatibilidad con InfinityFree).
* **Frontend POS**: HTML5, CSS estructurado, Bootstrap 5, Bootstrap Icons, SweetAlert2 y Datatables.
* **Tienda Virtual (`tienda.php`)**: SPA (Single Page Application) reactiva en Javascript Vanilla para procesamiento ultra rápido en el cliente sin recargas de página.

---

## 💻 Módulos del Panel Administrativo (POS)

El acceso a estos módulos requiere inicio de sesión en `index.php`. El sistema cuenta con control de accesos según roles: **Administrador** y **Vendedor (Cajero)**.

### 1. Panel de Control (Dashboard)
* Muestra resúmenes gráficos de ventas del día, ventas del mes y estadísticas críticas.
* Indicador de alertas de stock bajo y accesos rápidos a tareas recurrentes.

### 2. Gestión de Insumos (Catálogo de Productos)
* Alta, edición, visualización y eliminación lógica de productos.
* Cada insumo cuenta con: Nombre, Categoría, Unidad de Medida, Costo de Producción (rentabilidad), Precio Venta y Stock actual.
* **Control de Pesaje**: Opción de marcar si el producto requiere pesaje dinámico (ej: aves vivas que se pesan en balanza al vender) o si tiene contenido estándar fijo (sacos, javas).
* **Galería de Imágenes**: Subida de imágenes de producto (JPG, PNG, WebP) directamente al servidor con procesamiento automático de sustitución y previsualización en vivo.

### 3. Categorías y Unidades
* Mantenimiento de categorías de productos (Aves, Lácteos, Huevos, etc.).
* Registro y control de las unidades de medida (Unidad, Kilogramos, Litros, etc.) y sus abreviaciones.

### 4. Control de Inventario (Kardex)
* Registro histórico de todas las entradas (compras/producción) y salidas (ventas) de insumos.
* Control detallado de stock en piezas y peso neto, permitiendo auditorías precisas por lote de producción.

### 5. Punto de Venta (Nueva Venta)
* Formulario interactivo para registrar ventas presenciales de forma ágil.
* Búsqueda de clientes por DNI o Nombre con autocompletado y registro rápido desde la misma pantalla.
* Carrito de compras que calcula subtotales, IGV y total final en tiempo real.
* Integración de balanza virtual para insumos que requieren pesaje.
* Impresión de comprobante/ticket optimizado en formato térmico de 80mm.

### 6. Historial de Ventas
* Listado de todas las ventas físicas realizadas con filtros por fecha, comprobante y vendedor.
* Detalle de los artículos vendidos e IGV.
* Opción de anulación de ventas con devolución automática de inventario a través del Kardex.

### 7. Pedidos Online (E-commerce Link)
* Panel de control donde el personal aprueba o rechaza los pedidos hechos por los clientes desde la tienda virtual.
* **Aprobación**: Genera automáticamente la venta en el POS con el número de transacción ingresado y descuenta el stock de manera definitiva.
* **Rechazo**: Cancela el pedido y libera inmediatamente el stock que había sido reservado temporalmente.

### 8. Caja Chica y Registro
* **Mi Caja**: Control diario del cajero (Apertura de caja con monto inicial, registro de ingresos/egresos y Cierre de caja con balance del día).
* **Control de Cajas**: Histórico de arqueos de caja consultable por el administrador para auditoría financiera.

### 9. Gestión de Personal y Trabajadores (Universidad Nacional de Piura)
* **Gestor de Trabajadores**: Gestión de empleados universitarios asociados a su Facultad, Dependencia de trabajo, Tipo de Trabajador (Nombrado, Contratado) y código de planilla.
* **Vales Navideños**: Registro, asignación y canje de vales institucionales para la adquisición de productos de la granja.
* **Reporte Planilla**: Exportación de datos de consumos y deducciones por planilla para la oficina de remuneraciones.

---

## 🛒 Tienda Virtual Pública (`tienda.php`)

Es la vitrina de cara al cliente final, permitiéndole reservar productos para pagar y recoger de manera presencial en PuntoNet (*Click & Collect*).

### 1. Interfaz Premium e Interactiva
* **Hero Banner Dinámico**: Cabecera visual moderna con imagen de fondo del campo, overlay traslúcido verde corporativo y tipografía corta y elegante.
* **Buscador de Nombre Central**: Buscador en tiempo real ubicado debajo del Hero para encontrar productos al escribir los primeros caracteres.
* **Píldoras de Filtro (Pills)**: Visualización dinámica de los filtros activos en pantalla que pueden borrarse individualmente con un solo clic.

### 2. Panel Lateral de Filtros (Drawer)
* **Filtrado por Categorías**: Checkboxes autogenerados dinámicamente basados en el catálogo disponible en tiempo real.
* **Slider de Rango de Precios**: Deslizador doble superpuesto para fijar un precio mínimo y máximo de forma interactiva, junto con campos numéricos para ingreso manual preciso.
* **Ordenamiento**:
  - Por defecto (orden de catálogo).
  - De menor a mayor precio.
  - De mayor a menor precio.
  - Alfabéticamente de la A-Z.
* **Contador de Filtros**: Insignia roja (`badge`) en el botón de la cabecera que muestra en tiempo real cuántos filtros se están aplicando.

### 3. Tarjetas de Productos y Cesta de Compras
* Muestran la imagen real del producto cargada por el administrador en el POS con una micro-animación de zoom al posicionar el cursor (`hover`).
* Fallback de iconos vectoriales personalizados si el producto no tiene una imagen configurada.
* **Cesta Lateral (Offcanvas)**: Visualización y control de artículos añadidos con modificación rápida de cantidades.

### 4. Proceso de Pedido y Reserva (*Checkout*)
* Formulario limpio que requiere únicamente: **DNI**, **Nombres**, **Apellidos**, **Teléfono** y **Dirección**. Sin necesidad de registro de cuentas de usuario.
* **Método de Pago**: Pasarela simplificada integrada de código QR para pago instantáneo vía **Yape** con ingreso del número de operación.
* **Reserva de Stock**: Al confirmar el pedido, el sistema disminuye temporalmente el stock del insumo en el inventario para asegurar la disponibilidad de compra, a la espera de la aprobación del cajero en el POS administrativo.

---

## 🔒 Cumplimiento de Reglas de InfinityFree

Todo el desarrollo se ha realizado bajo el marco de reglas estrictas de [INFINITYFREE_REGLAS.md](file:///c:/xampp/htdocs/PuntoNet/INFINITYFREE_REGLAS.md):
1. **Sin dependencias del sistema**: Las subidas de imágenes usan PHP nativo.
2. **Eficiencia en Hosting Gratuito**: Los filtros de la tienda virtual se procesan en el navegador del cliente mediante JavaScript nativo, eliminando peticiones HTTP excesivas y evitando la suspensión de la cuenta por sobrecarga de CPU.
3. **Estructura de Base de Datos estándar**: El esquema cuenta con la columna `imagen` insertada en la definición de tabla de `insumos` sin usar triggers ni stored procedures.
