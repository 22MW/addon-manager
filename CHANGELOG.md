# Changelog

## 1.0.6 - 2026-04-17
- Refactor estructural interno: bootstrap más limpio, clase principal movida a `includes/Core/`, corrección de carga duplicada y estabilidad en activación/notificaciones.

## 1.0.5 - 2026-04-17
- Simplified admin notices: removed global notice layer and restored local notices in Addon Manager screen for reliable feedback on activate/deactivate/upload/delete.

## 1.0.4 - 2026-04-17
- Nueva pestaña "Addons de usuario" con subida de `.php` a `wp-content/uploads/addon-manager/user-addons/`.
- Validación de subida reforzada: permisos, nonce, extensión/tamaño, cabecera mínima, lint y patrones bloqueados.
- Activación segura unificada para addons core + usuario con healthcheck, cuarentena y rollback.
- Modo estricto en runtime: si un addon activo cambia o falla al cargar, se desactiva automáticamente.
- Nuevos avisos de seguridad y mejoras de UX en subida/eliminación de addons de usuario.

## 1.0.3 - 2026-04-16
- Movido `woo-booking-descount.php` a `private/` para excluirlo del selector público de addons.
- Actualizado `woocommerce-cupones-admin.php` a la versión `2.23` con ajuste de nombre y formato.

## 1.0.2 - 2026-04-16
- Añadido workflow de GitHub Actions para construir y publicar `addon-manager.zip` en tags `v*`.
- Excluidos `private/` y `RELEASE_UPDATES_GUIDE.md` del paquete de release.
- Endurecido el updater para aceptar solo el asset `addon-manager.zip` (sin fallback al ZIP source).

## 1.0.1 - 2026-04-16
- Actualizada la cabecera del plugin:
  - `Version: 1.0.1`
  - `Plugin URI: https://22mw.online/`
  - `Author URI: https://22mw.online/`
  - `Update URI: https://github.com/22MW/addon-manager`
- Integrado updater de GitHub Releases (igual enfoque que `woocommerce-cart-recovery`) adaptado al slug/carpeta `addon-manager`.
- Añadida documentación de metadata por addon (`Marketing Description` y `Parameters`) en la interfaz y en `readme.txt`.
- Añadidas cabeceras `Marketing Description` y `Parameters` en los addons existentes.

## 1.0.0 - 2026-04-16
- Base inicial de Addon Manager con activación/desactivación de addons por carpetas `addons/`, `woo/` y `multisite/`.
