# Instrucciones para Claude Code — PuntoNet POS

Sistema POS + tienda online (uniformes escolares). PHP puro sin framework + MySQL/PDO.
Antes se llamaba "Granja-POS"; en el código aún quedan títulos "NISSI" (rebrand parcial e intencional).

## Dónde buscar contexto antes de explorar código

1. **[CODEMAP.md](CODEMAP.md)** — mapa de arquitectura: estructura, convenciones, puntos de entrada,
   "si buscas X está en Y", y una sección de "cosas no obvias". **Léelo primero**, evita re-explorar el repo.

2. **NotebookLM** — historial de decisiones y estado del proyecto entre sesiones.
   Notebook: `PuntoNet POS — Contexto del Proyecto` (`c6d2fed5-4a13-4d05-9caa-c5215e681818`).

   Consúltalo **antes de planificar trabajo no trivial**, para saber qué se hizo y qué quedó pendiente:
   ```bash
   export PATH="$HOME/bin:$PATH"
   notebooklm use c6d2fed5-4a13-4d05-9caa-c5215e681818
   notebooklm ask "¿En qué estado está el proyecto y qué quedó pendiente?"
   ```
   Para tareas triviales (un typo, un cambio de una línea) no hace falta.

   **Al cerrar una sesión con trabajo significativo**, si el usuario lo pide o si hubo cambios de
   arquitectura/decisiones nuevas, agrega una nota corta al notebook (no una fuente nueva cada vez):
   ```bash
   notebooklm ask "Resume: <qué cambió, qué quedó pendiente>" --save-as-note
   ```
   Y actualiza `CODEMAP.md` si cambió la estructura.

## Reglas operativas (aprendidas a golpes — no repetir estos errores)

- **La BD local tiene datos reales**: `puntonet_pos` (usuario `puntonet_user`) tiene 146 productos de
  catálogo importados. Ante un error de columna/tabla faltante, **migrar con `ALTER`/`RENAME` in-place**,
  nunca recrear la base desde cero.
- **`.env` nunca se commitea** (está en `.gitignore`). Contiene credenciales de Izipay (test), Brevo, token de
  APIPeru y los `DB_PROD_*` (vacíos hasta que el usuario configure el nuevo hosting). Ningún secreto
  debe volver a quedar hardcodeado en `config/*.php`.
- **`gh` (GitHub CLI) no está instalado** y no hay sudo. Para abrir un PR, entrega al usuario el link de
  comparación de GitHub (`.../compare/main...<rama>`) en vez de intentar `gh pr create`.
- **Repo privado a propósito**: el historial de git contiene una contraseña de producción antigua que no
  se pudo purgar. No hacerlo público sin rotar credenciales y limpiar historial.

## Verificación antes de dar por terminado un cambio

```bash
find . -name "*.php" -not -path "./vendor/*" -not -path "./.git/*" | while read -r f; do out=$(php -l "$f" 2>&1); if ! echo "$out" | grep -q "No syntax errors"; then echo "$f: $out"; fi; done
```
Y para vistas con JS inline, extraer los bloques `<script>` y pasarlos por `node --check`.
Levantar `php -S localhost:<puerto>` y comprobar que `index.php`, cada módulo del menú y `tienda.php`
respondan 200 sin errores en el log.
