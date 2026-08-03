# Documentación de Módulos - PuntoNet POS

Este documento sirve como guía para el equipo de desarrollo, detallando la lógica de negocio, funcionamiento y la estructura de los módulos principales del sistema **PuntoNet POS**.

---

## 1. Arquitectura General
El sistema está construido utilizando un patrón **MVC (Modelo-Vista-Controlador)** en PHP nativo:
- **`views/`**: Contiene la interfaz de usuario. Cada módulo tiene su vista (ej. `V_productos.php`).
- **`controllers/`**: Reciben las peticiones del frontend (mayormente vía `fetch` / AJAX), procesan la información y devuelven respuestas en formato JSON.
- **`models/`**: Contienen las sentencias SQL y la conexión PDO a la base de datos para interactuar con las tablas correspondientes.

El archivo principal **`index.php`** actúa como un enrutador (Front Controller) que, dependiendo del parámetro `?modulo=`, inyecta la vista solicitada en el layout principal.

---

## 2. Tienda Online (E-Commerce Público)
A diferencia de los módulos administrativos que operan bajo `index.php` con protección de sesión, la **Tienda Online es un entorno público independiente**.

- **Enlace de Acceso:** El público accede mediante el archivo independiente **`tienda.php`** ubicado en la raíz del proyecto.
- **Funcionamiento Front-end:** `tienda.php` carga su propio diseño web. Muestra los productos disponibles (Productos) y permite a los usuarios agregar productos a un "carrito virtual" almacenado en su navegador o sesión pública.
- **Recepción en el Panel Administrativo:** Cuando un cliente finaliza una compra en `tienda.php`, los datos viajan a la base de datos. Los administradores y vendedores procesan estos pedidos dentro de `index.php?modulo=pedidos-online`, donde pueden aprobarlos, prepararlos o cancelarlos.

---

## 3. Módulo de Personas (Usuarios y Clientes)
Este conjunto de módulos centraliza el registro de todas las identidades que interactúan con el sistema.

> El antiguo módulo de personal universitario (Trabajadores, Vales, Facultades, Reporte Planilla) se eliminó del código por estar completamente muerto: no tenía rutas en `index.php` ni tablas en el esquema vivo (`base_datos_nissi.sql`). Sigue disponible en el historial de git si se necesita reactivar.

---

## 4. Módulo de Inventario
Controla el flujo físico de productos.
- **Categorías:** Agrupación lógica de productos (Ej. Cárnicos, Lácteos, Huevos).
- **Productos:** Productos finales disponibles para la venta.
- **Kardex:** Es el "historial de vida" de los productos. Cualquier modificación en el stock de un producto (ya sea por compras, ventas, mermas o ajustes manuales) debe registrarse aquí con su respectivo tipo de movimiento (Entrada / Salida).

---

## 5. Módulo de Ventas y Caja
El núcleo transaccional del POS.
- **Mi Caja:** Para poder realizar ventas, un empleado debe abrir su caja asignando un monto base inicial. Al finalizar su turno, cierra la caja, sumando las ventas en efectivo, transferencias y vales.
- **Control de Cajas:** Módulo para el Administrador donde puede auditar todas las cajas abiertas y cerradas históricamente por todos los cajeros.
- **Nueva Venta:** Interfaz de Punto de Venta ágil. Permite agregar productos, calcular totales y definir el método de pago.
- **Historial:** Listado de tickets generados, con capacidad de anular ventas en caso de errores (lo cual devuelve automáticamente el stock al inventario).

---

## 6. Módulo de Reportes
- **Ventas:** Análisis gráfico y tabular de los ingresos diarios, semanales o mensuales.

---
*Documento actualizado en Agosto 2026 para reflejar los últimos ajustes arquitectónicos.*
