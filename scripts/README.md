# 📁 scripts/ — Utilidades de desarrollo y migración

> ⚠️ **Esta carpeta está bloqueada en producción** vía `.htaccess`.  
> Los archivos aquí **NO son accesibles desde el navegador** cuando están en InfinityFree.  
> Úsalos únicamente en **entorno local (localhost)** o ejecuta el SQL equivalente manualmente en **phpMyAdmin**.

---

## Archivos incluidos

| Archivo | Propósito | ¿Usa BD? |
|---|---|---|
| `reset_db.php` | Elimina y recrea la BD local | Solo local |
| `setup_ecommerce.php` | Crea tablas `pedidos_online` y `detalle_pedidos_online` | Local y producción |

---

## ¿Cómo correrlos en Local?

```bash
# Desde la raíz del proyecto:
php scripts/setup_ecommerce.php
```

## ¿Cómo aplicar los cambios en Producción (InfinityFree)?

Como `.htaccess` bloquea el acceso HTTP, **copiar el SQL** de cada script y ejecutarlo manualmente en:

> **app.infinityfree.com → phpMyAdmin → Base de datos `if0_42381931_puntonet_pos`**

---

> ⚠️ `reset_db.php` usa `DROP DATABASE` y `CREATE DATABASE`, que están **prohibidos en InfinityFree** (Regla #1). Usar solo en local.
