# Documentación de Módulos - PuntoNet POS

Este documento sirve como guía para el equipo de desarrollo, detallando la lógica de negocio, funcionamiento y la estructura de los módulos principales del sistema **PuntoNet POS**.

---

## 1. Arquitectura General
El sistema está construido utilizando un patrón **MVC (Modelo-Vista-Controlador)** en PHP nativo:
- **`views/`**: Contiene la interfaz de usuario. Cada módulo tiene su vista (ej. `V_trabajadores.php`).
- **`controllers/`**: Reciben las peticiones del frontend (mayormente vía `fetch` / AJAX), procesan la información y devuelven respuestas en formato JSON.
- **`models/`**: Contienen las sentencias SQL y la conexión PDO a la base de datos para interactuar con las tablas correspondientes.

El archivo principal **`index.php`** actúa como un enrutador (Front Controller) que, dependiendo del parámetro `?modulo=`, inyecta la vista solicitada en el layout principal.

---

## 2. Tienda Online (E-Commerce Público)
A diferencia de los módulos administrativos que operan bajo `index.php` con protección de sesión, la **Tienda Online es un entorno público independiente**.

- **Enlace de Acceso:** El público accede mediante el archivo independiente **`tienda.php`** ubicado en la raíz del proyecto.
- **Funcionamiento Front-end:** `tienda.php` carga su propio diseño web. Muestra los productos disponibles (Insumos) y permite a los usuarios agregar productos a un "carrito virtual" almacenado en su navegador o sesión pública.
- **Recepción en el Panel Administrativo:** Cuando un cliente finaliza una compra en `tienda.php`, los datos viajan a la base de datos. Los administradores y vendedores procesan estos pedidos dentro de `index.php?modulo=pedidos-online`, donde pueden aprobarlos, prepararlos o cancelarlos.

---

## 3. Módulo de Personas (Usuarios, Clientes y Trabajadores)
Este conjunto de módulos centraliza el registro de todas las identidades que interactúan con el sistema.

### Trabajadores (¡Actualización Crítica en Producción!)
Recientemente, se realizó un rediseño importante en la lógica de registro masivo de trabajadores.
- **Antes:** Existía un módulo aislado llamado "Importar Trabajadores".
- **Ahora (Actual):** La funcionalidad fue optimizada y **fusionada como un Modal dentro de la vista principal de Trabajadores**.

**Lógica de Importación Masiva (Excel):**
1. El usuario sube un `.xlsx` desde el botón "Importar XLSX" en la vista de Trabajadores.
2. El archivo se envía al controlador `C_Importar.php`.
3. Para evitar que el servidor colapse por falta de memoria RAM al leer miles de registros, la librería `PhpSpreadsheet` ha sido forzada a **leer solo los datos raw** (ignorando colores/estilos) y **se restringió el escaneo solo a las columnas con datos reales**.
4. **Validaciones Clave:** 
   - El sistema diferencia si se está subiendo una planilla de **Docentes** o de **CAS**, ya que las columnas varían en cada formato institucional. 
   - Si una fila tiene el DNI vacío, se ignora automáticamente para no registrar basura. 
   - Si un DNI ya existe, el sistema **actualiza** la información de dicho trabajador en lugar de duplicarlo.
   - Si el campo `VC_PERSONAL_DEPENDENCIA` viene vacío en el Excel, se asigna "General" por defecto.

---

## 4. Módulo de Inventario
Controla el flujo físico de productos.
- **Categorías:** Agrupación lógica de productos (Ej. Cárnicos, Lácteos, Huevos).
- **Insumos:** Productos finales disponibles para la venta.
- **Kardex:** Es el "historial de vida" de los productos. Cualquier modificación en el stock de un insumo (ya sea por compras, ventas, mermas o ajustes manuales) debe registrarse aquí con su respectivo tipo de movimiento (Entrada / Salida).

---

## 5. Módulo de Ventas y Caja
El núcleo transaccional del POS.
- **Mi Caja:** Para poder realizar ventas, un empleado debe abrir su caja asignando un monto base inicial. Al finalizar su turno, cierra la caja, sumando las ventas en efectivo, transferencias y vales.
- **Control de Cajas:** Módulo para el Administrador donde puede auditar todas las cajas abiertas y cerradas históricamente por todos los cajeros.
- **Nueva Venta:** Interfaz de Punto de Venta ágil. Permite agregar insumos, calcular totales y definir el método de pago. Soporta el uso de "Descuento por Planilla" (asociando la venta al DNI de un Trabajador registrado).
- **Historial:** Listado de tickets generados, con capacidad de anular ventas en caso de errores (lo cual devuelve automáticamente el stock al inventario).

---

## 6. Módulo de Vales Navideños / Campañas
Permite la emisión masiva o unitaria de "Tickets" de saldo a favor para los trabajadores.
- **Generación:** Se selecciona un Tipo de Trabajador o una Dependencia específica y se generan Vales con un monto asignado a nombre de cada uno.
- **Uso:** El trabajador puede usar este vale físico o virtual durante la pantalla de "Nueva Venta" como método de pago (canje).

---

## 7. Módulo de Reportes
- **Ventas:** Análisis gráfico y tabular de los ingresos diarios, semanales o mensuales.
- **Reporte de Planilla:** Especializado en extraer un resumen de todas las ventas que se hicieron usando "Descuento por Planilla". Este módulo emite un formato específico que puede ser entregado al departamento de RRHH para realizar los descuentos salariales respectivos a fin de mes.

---
*Documento actualizado en Julio 2026 para reflejar los últimos ajustes arquitectónicos.*
