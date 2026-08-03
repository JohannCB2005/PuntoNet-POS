# Sistema de Ventas de Productos de Granja (POS)

**Universidad Nacional de Piura (UNP) - Ingeniería Informática**

Este repositorio contiene el código fuente para el Sistema de Punto de Venta (POS) e inventario, desarrollado bajo la arquitectura MVC y el patrón DAO. Este documento define el alcance y las reglas de escritura de código para todos los colaboradores del proyecto. **Todos los commits deben respetar estas nomenclaturas.**

---

## 1. Definición del Sistema y Caso Real
* **El Problema en la UNP:** Actualmente, la gestión de productos y ventas de la Granja de Zootecnia se realiza de forma manual, lo que genera descuadres de inventario y lentitud en los reportes.
* **Justificación:** Este sistema web digitaliza el proceso mediante una interfaz interactiva y segura, resolviendo el problema de gestión real.

2. **Módulos del Sistema (Alcance Total)**
El sistema se compone de los siguientes 6 módulos funcionales:

1. **Módulo de Autenticación (Login & Sesiones):**
   * Formulario de inicio de sesión seguro.
   * Validación de contraseñas con `password_verify()`.
   * Redirección y bloqueo de rutas según el Rol (Admin/Vendedor).
2. **Módulo de Inventario:**
   * Gestión de Categorías (CRUD simple).
   * Gestión de Productos (CRUD con control de Stock y Borrado Lógico).
3. **Módulo de Usuarios (Solo Admin):**
   * Registro de trabajadores con encriptación de claves (`password_hash()`).
   * Asignación de roles y control de acceso.
4. **Módulo de Clientes:**
   * Registro y edición de compradores (CRUD con Borrado Lógico).
   * Integración con consulta/búsqueda rápida y registro inteligente.
5. **Módulo de Ventas (Transaccional - CRÍTICO):**
   * Interfaz POS interactiva (Búsqueda de productos y Carrito temporal en JS/PHP).
   * Generación de la Venta (facturación).
   * Historial de ventas y anulación de boletas.
   * **Impresión de Comprobante:** Previsualización y descarga en múltiples formatos (Ticket térmico 80mm, Ticket térmico 58mm y formato A4 estándar) integrado tras registrar una venta y en el historial.
6. **Módulo de Reportes (Alta Nota):**
   * Dashboard con gráficos (Chart.js o similar) consumiendo datos vía AJAX/JSON.
   * Reporte tabular exportable a PDF (usando librerías como FPDF o Dompdf).

**Roles de Usuario:**
  * `Administrador`: Acceso total (Gestión de usuarios, inventario completo y reportes)[cite: 4].
  * `Vendedor`: Acceso limitado (Apertura/cierre de caja, consulta de stock, registro de clientes y realización de ventas).[cite: 4].

## 3. Stack Tecnológico
* **Frontend (Cliente Ligero):** HTML5, CSS3, JavaScript (DOM/AJAX), Bootstrap 5.
* **Backend:** PHP estructurado/POO, manejo de sesiones, procesamiento de datos y control de usuarios.
* **Base de Datos:** MySQL con PDO (conexión y operaciones CRUD: Insertar, Consultar, Actualizar, Eliminar).

## 4. Estrategia de Base de Datos (Híbrida)
Para garantizar la integridad financiera y el rendimiento, el acceso a datos se divide en dos enfoques:
* **Consultas Preparadas PDO:** Utilizadas en los DAOs para todos los CRUDs estándar (Usuarios, Clientes, Productos, Categorías).
* **Procedimientos Almacenados con Transacciones:** Uso obligatorio para procesos que afecten múltiples tablas para evitar inconsistencias. Ejemplo:
  * `sp_registrar_venta`: Controla inserciones en ventas, detalle_ventas y actualización de stock.
  * `sp_anular_venta`: Controla la anulación y la devolución del stock al inventario.

## 3. Metodología y División del Equipo
El proyecto seguirá 5 fases formales: 
1) Análisis del problema 
2) Diseño del sistema 
3) Desarrollo 
4) Pruebas 
5) Implementación[cite: 4]. Trabajaremos en paralelo mediante ramas (`branches`) en GitHub.

**Asignación de tareas:**
1. **Arquitectura y BD (Max):** Diseño de BD, creación de la clase `Conexion.php` y programación de las **Clases Entidades**.
2. **Lógica de Módulos (Álvaro):** Programación de DAOs y Controladores para el CRUD de Inventario/Clientes. Implementación del *Manejo de Sesiones*[cite: 4].
3. **Módulo de Ventas (Integrante 3):** Lógica de transacciones (carrito temporal, restar stock, registrar detalle).
4. **Frontend y Alta Nota (Integrante 4):** Maquetación con JS/DOM, validaciones y desarrollo del módulo de Reportes (Gráficos, PDF, APIs)[cite: 4].

## 4. Arquitectura y Carpetas (MVC + Singleton)
El proyecto respetará la separación estricta en capas basada en los laboratorios del curso, implementando el Patrón Singleton para la gestión de la base de datos:
```text
/puntonet_pos
├── assets/                 <-- FRONTEND (Territorio del Integrante 4)
│   ├── css/                # Estilos personalizados adicionales a Bootstrap
│   ├── js/                 # Lógica de cliente, AJAX, validaciones y gráficos
│   ├── img/                # Logos, avatares, imágenes de productos
│   └── vendor/             # Librerías externas (Bootstrap, Chart.js, FPDF)
│
├── config/                 <-- BACKEND (Tu territorio)
│   └── conexion.php        # Archivo con la conexión PDO
│   └── rutas.php           # Aquí es donde se maneja la lógica de a dónde va el usuario
│
├── controllers/            <-- BACKEND (Territorio de Álvaro y Módulo Ventas)
│   └── .gitkeep            # Aquí irán C_Venta.php, C_Usuario.php, etc.
│
├── entities/               <-- BACKEND (Tu territorio)
│   └── .gitkeep            # Clases puras para llenado de datos (Producto.php, Venta.php). 
│
├── models/                 <-- BACKEND (Territorio de Álvaro y Módulo Ventas)
│   └── .gitkeep            # Archivos M_*.php con el Patrón Singleton y sentencias SQL
│
├── views/                  <-- FRONTEND / UI (Territorio del Integrante 4)
│   ├── layouts/            # Partes repetitivas: header.php, footer.php, navbar.php
│   └── .gitkeep            # Las pantallas V_*.php (V_nueva_venta.php, V_lista_productos.php)
│
├── base_datos.sql          # (Ya lo tienes)
├── README.md               # (Ya lo tienes)
└── index.php               <-- ENRUTADOR PRINCIPAL (El puente de todo)
```

## 5. Reglas de Seguridad (¡OBLIGATORIO!)
[cite_start]Para cumplir con los criterios de evaluación, todo código subido debe considerar:

* [cite_start]**Validación de entradas:** Usar `htmlspecialchars()` o equivalentes en PHP, además de validaciones en JS.
* **Protección SQL:** Prohibido concatenar variables en SQL. [cite_start]Usar siempre sentencias preparadas de PDO (`prepare` / `execute`).
* [cite_start]**Contraseñas:** Uso estricto de `password_hash()` y `password_verify()` para el login.

Siguiendo las directrices del curso, todas las clases del directorio /models implementarán el Patrón Singleton para la conexión PDO.

* **Objetivo:** Garantizar que exista una única instancia de conexión a la base de datos por petición, optimizando la memoria RAM del servidor y previniendo la saturación de conexiones en MySQL.

* **Implementación:** Se utilizará un atributo estático privado $instancia y un método público estático singleton() que verificará la existencia de la conexión antes de instanciarla.

---

## 6. Reglas de Nomenclatura
Para mantener un código limpio, aplicaremos el estándar PSR.

### 6.1. Base de Datos (`snake_case`)
* **Tablas:** En plural (`productos`, `usuarios`, `ventas`).
* **Columnas:** En singular (`id_producto`, `nombre`, `precio_unitario`).

### 6.2. Clases y Archivos PHP (`PascalCase`)
El nombre del archivo debe ser exactamente igual al nombre de la clase.
* **Entidades:** `Producto.php`, `Usuario.php`, `Cliente.php`.
* **Modelos (DAOs):** Prefijo `M_` (`M_Producto.php`, `M_Cliente.php`).
* **Controladores:** Prefijo `C_` (`C_Producto.php`, `C_Cliente.php`).

### 6.3. Variables y Propiedades (`camelCase`)
* **Correcto:** `$precioUnitario`, `$listaProductos`, `$idVenta`.
* **Incorrecto:** `$precio_unitario`, `$PrecioUnitario`.

### 6.4. Funciones y Métodos (`camelCase` + Verbo)
* **Correcto:** `obtenerProducto()`, `registrarVenta()`, `listarTodos()`.
* **Incorrecto:** `Producto()`, `ver_venta()`.

### 6.5. Vistas HTML/PHP (`snake_case`)
* **Correcto:** `lista_productos.php`, `nueva_venta.php`.
* **Incorrecto:** `ListaProductos.php`, `NuevaVenta.php`.

---

## 7. Regla de Oro en POO: Uso de Clases Entidades
Siempre usaremos las Clases Entidades para llenarlos de datos y no pasar tantos parámetros sueltos en las funciones. Está estrictamente prohibido pasar parámetros individuales para registrar o actualizar.

🔴 **INCORRECTO:**
```php
public function registrarProducto($nombre, $descripcion, $precio, $stock) { ... }
```
🟢 CORRECTO:
```php
// 1. Llenamos la Clase Entidad
$nuevoProducto = new Producto();
$nuevoProducto->nombre = $_POST['nombre'];
$nuevoProducto->precio = $_POST['precio'];

// 2. Pasamos la Entidad completa al DAO
$productoDAO->registrarProducto($nuevoProducto);
```
## 8. Entregables Finales
* El equipo es responsable de compilar los siguientes productos para la evaluación final:  
* Aplicación web 100% funcional y código fuente organizado.  
* Base de datos exportada.  
* Informe Académico: Documento con Introducción, Problema, Objetivos, Marco teórico, Diseño del sistema, Desarrollo, Resultados y Conclusiones.  
* Exposición final.  