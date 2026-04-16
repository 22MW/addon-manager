# Changelog

## 1.0.2 - 2026-04-16
- Added GitHub Actions release workflow to build and publish `addon-manager.zip` on `v*` tags.
- Excluded `private/` and `RELEASE_UPDATES_GUIDE.md` from the release package.
- Hardened updater to accept only the `addon-manager.zip` asset (no source ZIP fallback).

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
