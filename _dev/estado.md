# Estado del plugin: Addon Manager

## Ultima actualizacion

2026-07-22

---

## Resumen humano

Plugin **maduro y seguro** con arquitectura modular para gestionar addons (WordPress, WooCommerce, Multisite). Último release: **1.0.6.1** (06-26-2026). **Cambios sin commitear:** 7 archivos, 148 insertiones (principalmente hardening multisite). **Crítico:** readme.txt desincronizado (marca v1.0.4, debería ser 1.0.6.1).

---

## Estado general

**Bloqueado** — 7 cambios sin commit + bug tipográfico en `change_pass_form.php` + readme.txt desincronizado. Requiere limpieza Git + fix antes de cualquier uso en producción.

---

## Hecho estable

- ✓ Core de Addon Manager funcional (clase principal bien estructurada)
- ✓ GitHub Updater integrado
- ✓ Validaciones de seguridad presentes: nonces (4), sanitización (35+)
- ✓ Soporte multisite, WooCommerce, addons de usuario
- ✓ Sistema de cuarentena/healthcheck en runtime
- ✓ Assets estáticos (CSS/JS vanilla, sin build)
- ✓ Documentación en readme.txt completa

---

## Relevo actual

- Rol actual: Desarrollador
- Último relevo: `_dev/departamentos/desarrollador.md`
- Siguiente rol recomendado: Tester QA
- Debe leer:
  - `_dev/estado.md`
  - `_dev/departamentos/desarrollador.md`
- Tarea que recibe: validar `[check_product_purchased]` sin parámetros en single product y con `product_id` en página normal.

---

## En curso (cambios pendientes)

- ✗ `woo/woocommerce-product-checker.php` — shortcode ahora acepta `product_id` y `user_id` opcionales
- ✗ `multisite/db-options-cleaner-multisite.php` — mejoras seguridad (80+ líneas)
- ✗ `multisite/mu-db-native-cleaner.php` — ídem (80+ líneas)
- ✗ `multisite/tabla-cleaner-multisite.php` — 10 líneas
- ✗ `multisite/wp-cleaner-mu-plugin.php` — 24 líneas (hardening)
- ✗ `addons/change_pass_form.php` — 2 líneas
- ✗ `private/wooEmailStringEditor.php` — 2 líneas
- ✗ `_dev/release-notes.md` — ELIMINADO

---

## Bloqueado

1. **CRÍTICO:** readme.txt `Stable tag: 1.0.4` vs. `addon-manager.php Version: 1.0.6.1` → DESINCRONIZADO
2. **CRÍTICO:** Bug tipográfico en `addons/change_pass_form.php` línea 17: `add_action('muplugin   s_loaded',` (espacios en hook)
3. **ALTO:** 7 archivos modificados sin commit — cambios multisite incluyen mejoras seguridad pendientes

---

## Proximo paso recomendado

1. **Rol: Debugger** → Diagnóstico del bug `change_pass_form.php` línea 17 (hook con espacios)
2. **Rol: Seguridad** → Revisar diffs multisite (validar mejoras de seguridad)
3. **Rol: Jefe-Proyecto** → Commitear cambios, actualizar readme.txt versión, crear `_dev/contexto-activo.md`
4. **Rol: Release-Manager** → Validar versión, changelog, ZIP limpio

---

## Lectura inicial recomendada

- Leer primero `CHANGELOG.md` (versión 1.0.6.1 y cambios recientes)
- `readme.txt` sección "Features" para scope funcional
- `git diff` multisite para entender cambios pendientes

---

## No volver a investigar

- ✓ Estructura del plugin: confirmada, organizada, coherente
- ✓ Seguridad básica: nonces + sanitización presente (4 nonces, 35+ esc/sanitize)
- ✓ Sin dependencias de build (composer.json / package.json ausentes — OK)
- ✓ Git remoto: https://github.com/22MW/addon-manager.git válido
- ✓ Rama: main, sin rama dev explícita

---

## Riesgos confirmados

| Severidad | Riesgo | Evidencia |
|-----------|--------|-----------|
| 🔴 CRÍTICO | readme.txt versión desincronizada | `Stable tag: 1.0.4` vs. `Version: 1.0.6.1` |
| 🔴 CRÍTICO | Bug en hook `change_pass_form.php` L17 | `add_action('muplugin   s_loaded'` — espacios en nombre |
| 🟡 ALTO | Cambios multisite sin validar en producción | 148+ insertiones, 7 archivos modificados |
| 🟢 BAJO | `_dev/` vacío sin documentación | Sin estado.md, roadmap.md, decisiones.md |

---

## Supuestos

- Cambios multisite son del mismo autor (22MW)
- readme.txt se actualiza manualmente antes de release
- `private/` se excluye en ZIP de release
- Última acción: "harden user addon validation" según git log
