# Changelog

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
