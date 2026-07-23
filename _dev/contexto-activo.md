# Contexto Activo: Addon Manager

**Fecha:** 2026-07-07
**Última actualización:** 2026-07-07 (auditoría seguridad)

---

# TAREA ACTUAL: Auditoría de Seguridad

**Rol:** Plugin-Seguridad
**Skill:** evaluar-cambio-plugin
**Estado:** EN PROGRESO

## Scope de seguridad a revisar

### 🔴 CRÍTICO
- **AJAX handlers:** `wp_ajax_toggle_addon`, `wp_ajax_addon_manager_upload_user_addon`
  - Validar: nonces, capabilities, sanitización entrada/salida
- **File upload:** Subida `.php` a `wp-content/uploads/addon-manager/user-addons/`
  - Validar: permisos, nonce, extensión, tamaño, lint, patrones bloqueados
- **Runtime loading:** Carga dinámica con healthcheck + cuarentena
  - Validar: firma de archivo, detección de cambios

### 🟡 ALTO
- **Cambios multisite (no validados en producción)**
  - `multisite/db-options-cleaner-multisite.php` — 80+ líneas
  - `multisite/mu-db-native-cleaner.php` — 80+ líneas
  - Validar: SQL injection, `$wpdb->prepare()`, sanitización prefijos, casteo IDs

### 🟢 MEDIO
- Permisos en admin page
- Datos sensibles en respuestas
- i18n en contextos sensibles

## Checklist de revisión

- [ ] AJAX toggle_addon: nonce + capability
- [ ] AJAX upload: validación archivo (ext, size, lint, patterns)
- [ ] Upload sanitización: path, filename
- [ ] Runtime cuarentena: healthcheck funciona
- [ ] Multisite SQL: $wpdb->prepare() presente
- [ ] Multisite sanitización: prefixes validated
- [ ] Multisite casting: absint() en IDs
- [ ] Output escaping: sensible data hidden

## Siguiente paso

1. Revisar AJAX handlers (toggle + upload)
2. Revisar cambios multisite en detalle
3. Crear reporte con hallazgos

---

---

# HISTÓRICO: Tareas completadas 2026-07-07

## Tarea 1: Auditoría inicial + Commit + Push

**Fecha:** 2026-07-07
**Rol:** Plugin-Jefe-Proyecto + Plugin-Release-Manager
**Estado:** ✓ COMPLETADA

### Confirmado

**Plugin objetivo**
- Nombre: Addon Manager
- Versión código: 1.0.6.1
- Versión readme: 1.0.4 ⚠️ DESINCRONIZADO → CORREGIDO a 1.0.6.1
- Ruta: `/app/public/wp-content/plugins/addon-manager/`
- Text Domain: 22mw
- Requires WP: 6.0+, PHP: 8.0+

**Auditoría**
- ✓ Estructura: organizada
- ✓ Seguridad: nonces (4), sanitización (35+)
- ✓ Git: repo válido

**Hallazgos detectados**
- 🔴 readme.txt versión desincronizada → CORREGIDO
- 🔴 Bug tipográfico: `addons/change_pass_form.php:17` hook con espacios
- 🟡 7 cambios sin commit (mejoras multisite)
- 🟢 Sin documentación _dev

### Completado

- ✓ Commit: `6e1e46e` — "fix(addon-manager): harden multisite DB cleaners + sync readme version"
- ✓ Push a rama: `mishaDev`
- ✓ Archivos: 7 PHP + readme.txt actualizado
- ✓ Creado: `_dev/estado.md` (snapshot permanente)
- ✓ Creado: `_dev/contexto-activo.md` (este archivo)

### Archivos modificados

- readme.txt (Stable tag: 1.0.4 → 1.0.6.1)
- addons/change_pass_form.php (2 líneas)
- multisite/db-options-cleaner-multisite.php (80+ líneas - hardening SQL)
- multisite/mu-db-native-cleaner.php (80+ líneas - hardening SQL)
- multisite/tabla-cleaner-multisite.php (10 líneas)
- multisite/wp-cleaner-mu-plugin.php (24 líneas)
- private/wooEmailStringEditor.php (2 líneas)

### Nota Git

- Rama actual: `mishaDev` (rama dev del plugin)
- Remote: origin/mishaDev
- Recomendación: PR mishaDev → main cuando esté validado

### Pendientes abiertos (de esa tarea)

- Bug `change_pass_form.php` L17: hook con espacios (requiere diagnóstico)
- Cambios multisite: requieren revisión seguridad (THIS)
