# 📋 Reglas de Compatibilidad con InfinityFree
## Proyecto: PuntoNet — `puntonet.site.je`

> Este documento establece las restricciones y lineamientos técnicos que **todo el equipo debe respetar**
> para garantizar que el código funcione correctamente en el entorno de hosting gratuito **InfinityFree**.
> Estas reglas surgen de problemas reales detectados durante el despliegue del proyecto.

---

## 🔴 REGLAS CRÍTICAS (rompen la aplicación si se ignoran)

### 1. Nunca usar `CREATE DATABASE` ni `USE` en scripts PHP o SQL ejecutados desde código

InfinityFree **no otorga el privilegio `CREATE` a nivel de servidor**. La base de datos debe existir previamente,
creada manualmente desde el panel de control de InfinityFree.

**❌ Prohibido en archivos `.sql` ejecutados por PHP o subidos vía phpMyAdmin como inicialización:**
```sql
CREATE DATABASE IF NOT EXISTS mi_base_de_datos;
USE mi_base_de_datos;
```

**✅ Correcto:** Crear la base de datos desde el panel → sección **MySQL Databases**, y comentar estas líneas en el SQL:
```sql
-- CREATE DATABASE IF NOT EXISTS if0_42381931_puntonet_pos ...;
-- USE if0_42381931_puntonet_pos;
```

---

### 2. Nunca usar `CREATE PROCEDURE`, `CREATE FUNCTION` ni `CREATE TRIGGER`

InfinityFree **no permite la creación de Stored Procedures, Functions ni Triggers** desde cuentas de usuario estándar.
Toda la lógica transaccional **debe estar en el backend PHP**.

**❌ Prohibido:**
```sql
DELIMITER $$
CREATE PROCEDURE sp_registrar_venta(...) BEGIN ... END $$
DELIMITER ;
```

**✅ Correcto:** Implementar la lógica en PHP usando **PDO con transacciones explícitas**:
```php
$pdo->beginTransaction();
try {
    // lógica aquí
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}
```

> Los procedimientos `sp_registrar_usuario`, `sp_registrar_cliente`, `sp_registrar_venta`
> y `sp_anular_venta` ya fueron migrados a `M_Usuario.php`, `M_Cliente.php` y `M_Venta.php`.

---

### 3. Credenciales de conexión — Formato obligatorio de InfinityFree

InfinityFree genera automáticamente el nombre de usuario y la base de datos con el prefijo de la cuenta.
**Nunca inventar nombres personalizados**.

| Parámetro  | Valor correcto para este proyecto       |
|------------|-----------------------------------------|
| `$host`    | `sql210.infinityfree.com`               |
| `$dbname`  | `if0_42381931_puntonet_pos`              |
| `$user`    | `if0_42381931`                          |
| `$pass`    | La contraseña del panel de vPanel       |

Archivo de configuración: `config/conexion.php`

---

### 4. Nombre de base de datos — siempre con prefijo `if0_42381931_`

InfinityFree **fuerza el prefijo** de la cuenta en todos los nombres de bases de datos.
Al crear una base de datos en el panel llamada `puntonet_pos`, el nombre real queda como `if0_42381931_puntonet_pos`.

**❌ Incorrecto:**
```php
$dbname = 'puntonet_pos';
```

**✅ Correcto:**
```php
$dbname = 'if0_42381931_puntonet_pos';
```

---

## 🟡 REGLAS IMPORTANTES (pueden causar errores silenciosos)

### 5. No subir archivos con la lógica de auto-creación de base de datos activa

El bloque `inicializarBaseDatos()` en `conexion.php` intentará ejecutar `CREATE DATABASE`
si la BD está vacía. Esto fallará en InfinityFree. Para evitar el problema:

- **Siempre importar el esquema manualmente** desde **phpMyAdmin** antes del primer despliegue.
- El método `inicializarBaseDatos()` puede dejarse como fallback para entorno local (localhost), pero en producción la BD ya debe tener tablas.

---

### 6. No usar `DELIMITER` en scripts SQL ejecutados por phpMyAdmin

phpMyAdmin **no soporta el comando `DELIMITER`** de la misma forma que el cliente MySQL en consola.
Usar `DELIMITER $$` en phpMyAdmin causa errores de sintaxis.

**❌ Incorrecto al ejecutar en phpMyAdmin:**
```sql
DELIMITER $$
CREATE PROCEDURE ... $$
DELIMITER ;
```

**✅ Correcto en phpMyAdmin:** Ejecutar solo sentencias DDL y DML estándar sin `DELIMITER`.

---

### 7. Usar subconsultas en lugar de IDs hardcodeados en scripts de inserción

Los IDs auto-incrementales pueden variar entre entornos (local vs producción). Para los scripts de seeding:

**❌ Frágil:**
```sql
INSERT INTO insumos (id_categoria, ...) VALUES (3, ...);
```

**✅ Robusto:**
```sql
INSERT INTO insumos (id_categoria, ...)
VALUES ((SELECT id_categoria FROM categorias WHERE nombre = 'Aves' LIMIT 1), ...);
```

---

### 8. Todo el contenido del script `base_datos.sql` debe ser SQL estándar

El archivo `base_datos.sql` es ejecutado por `conexion.php` línea por línea via `PDO::exec()`.
Solo se soportan sentencias `CREATE TABLE`, `INSERT INTO` y comentarios `--`.

**No incluir:**
- `DELIMITER`
- `CREATE PROCEDURE / FUNCTION / TRIGGER`
- `CREATE DATABASE / USE`
- Comandos del cliente MySQL (`\q`, `SOURCE`, etc.)

---

## 🟢 BUENAS PRÁCTICAS RECOMENDADAS

### 9. Usar siempre `IF NOT EXISTS` en los `CREATE TABLE`

Evita errores al re-ejecutar el script de inicialización:
```sql
CREATE TABLE IF NOT EXISTS roles ( ... );
```

### 10. Siempre usar `utf8mb4` como charset

InfinityFree soporta `utf8mb4`. Garantiza compatibilidad con tildes, eñes y emojis:
```sql
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
```php
PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
```

### 11. Verificar errores con `PDO::ERRMODE_EXCEPTION`

Siempre mantener activado el modo de excepción en PDO para detectar errores de BD:
```php
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
```

### 12. No exponer credenciales ni mensajes de error en producción

En el archivo `conexion.php`, reemplazar los `die()` con mensajes genéricos antes de subir a producción,
o redirigir a una página de error personalizada. Los mensajes detallados de PDO solo deben verse en local.

---

## 📁 Estructura de archivos relevante

```
puntonet_pos/
├── index.php                       ← Front Controller principal (con sesión)
├── tienda.php                      ← Entry point público del e-commerce (sin sesión)
│
├── config/
│   └── conexion.php                ← Credenciales de BD (NO versionar con contraseña real)
│
├── entities/                       ← Entidades del dominio (Cliente, Trabajador, Venta, Vale...)
├── models/                         ← Modelos de acceso a BD (M_Cliente, M_Venta, M_Trabajador...)
├── controllers/                    ← Controladores AJAX (C_Cliente, C_Venta, C_Importar...)
│
├── views/
│   ├── layouts/                    ← sidebar.php, header.php, footer.php
│   ├── V_clientes.php
│   ├── V_trabajadores.php
│   ├── V_nueva_venta.php
│   └── ...                         ← Resto de vistas del panel
│
├── vendor/                         ← Dependencias Composer (PhpSpreadsheet)
│
├── scripts/                        ← Utilidades de desarrollo y migración
│   ├── .htaccess                   ← 🔒 Bloquea acceso HTTP en producción
│   ├── README.md                   ← Instrucciones de uso
│   ├── reset_db.php                ← Solo local: elimina y recrea la BD
│   ├── setup_plan.php              ← Migración: tipos_trabajador, dependencias
│   ├── setup_ecommerce.php         ← Migración: pedidos_online
│   ├── setup_vales.php             ← Obsoleto (reemplazado por setup_plan.php)
│   └── test_ecommerce.php          ← Pruebas locales del e-commerce
│
├── base_datos.sql                  ← Esquema principal (sin CREATE DB, USE ni STORED PROCEDURES)
└── INFINITYFREE_REGLAS.md          ← Este archivo
```

> ⚠️ **Regla sobre `scripts/`:** La carpeta está protegida con `.htaccess` y **no es accesible desde el navegador** en producción.
> Para aplicar migraciones en InfinityFree, copiar el SQL del archivo correspondiente y ejecutarlo manualmente en phpMyAdmin.

---

## 🔑 Datos de Producción (Referencia Rápida)

| Concepto           | Valor                          |
|--------------------|--------------------------------|
| Servidor MySQL     | `sql210.infinityfree.com`      |
| Base de Datos      | `if0_42381931_puntonet_pos`    |
| Usuario MySQL      | `if0_42381931`                 |
| Dominio            | `puntonet.site.je`             |
| Panel de Control   | `app.infinityfree.com`         |
| Directorio Raíz    | `/home/vol10_8/infinityfree.com/if0_42381931/htdocs` |

---

*Documento generado el 2026-07-10. Actualizar ante cualquier cambio de configuración en el servidor.*
