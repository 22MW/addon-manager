=== Addon Manager ===
Contributors: 22mw
Tags: addon manager, tools, woocommerce, multisite, admin
Requires at least: 6.0
Tested up to: 6.9.4
Requires PHP: 8.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Panel central para activar/desactivar mini-addons (WordPress, WooCommerce y Multisite) desde una unica interfaz.

== Description ==

Addon Manager permite cargar modulos pequenos de forma selectiva desde:
- `addons/` (WordPress general)
- `woo/` (WooCommerce)
- `multisite/` (Network / multisite)

Cada modulo se activa con switch y se carga en runtime segun la opcion `active_addons`.

Nota:
- La carpeta `private/` contiene modulos internos y no entra en el selector del panel actual.
- Solo se listan en la UI los archivos con cabecera valida `Plugin Name`.

Cabeceras recomendadas por addon (metadata de tarjeta en Addon Manager):
- `Plugin Name`: nombre visible del addon.
- `Description`: descripcion tecnica base.
- `Marketing Description`: texto mostrado en bloque "Descripcion" de la tarjeta.
- `Parameters`: texto mostrado en bloque "Parametros" de la tarjeta (shortcodes, rutas, flags, etc.).
- `Long Description`: fallback legacy para parametros si `Parameters` no existe.

== Features ==

- Activacion/desactivacion individual de modulos.
- Separacion por pestañas: WordPress, WooCommerce, Multisite.
- Carga ligera: solo se incluyen archivos activos.
- Extensible: basta con anadir archivo PHP con cabecera valida en carpeta soportada.
- Metadata por addon: cada archivo define su propia "Descripcion" y "Parametros" sin editar el core del manager.
- Actualizaciones por GitHub Release con paquete `addon-manager.zip`.

== Installation ==

1. Copiar `addon-manager` dentro de `wp-content/plugins/`.
2. Activar el plugin desde Plugins.
3. Ir a `Addons` en el menu de admin.
4. Activar los modulos necesarios con switch.

== Instrucciones de uso ==

1. Entra a `Addons` y activa/desactiva cada modulo con el switch.
2. Si el addon activo registra pagina de ajustes, aparecera el boton `Configurar` en su tarjeta.
3. Usa el bloque `Descripcion` para contexto funcional rapido del addon.
4. Usa el bloque `Parametros` para ver shortcodes, atributos o rutas de configuracion.
5. Para crear un addon nuevo, anade un archivo `.php` en `addons/`, `woo/` o `multisite/` con esta cabecera minima:

```
/**
 * Plugin Name: Mi Addon
 * Description: Que hace el addon
 * Marketing Description: Resumen comercial para la tarjeta
 * Parameters: Shortcode [mi_addon foo="bar"] o "Sin parametros"
 * Version: 1.0.0
 */
```

No es necesario modificar `addon-manager.php` para que se muestre descripcion/parametros del nuevo addon.

== Catalogo de modulos ==

=== WordPress (`addons/`) ===

- `change_pass_form.php`
  Formulario frontal para usuarios logueados que permite cambiar contrasena con validacion y shortcode `[change_pass_form]`.

- `disable_AdminNotice.php`
  Oculta avisos del admin y permite mostrarlos/ocultarlos desde un boton en la barra superior.

- `disable_textdomain_notice.php`
  Reduce avisos de textdomain en ejecucion/admin para limpiar logs y notificaciones.

- `email_Redirect_Manager.php`
  Redirige correos salientes de WordPress a destinatarios de prueba con prefijo configurable.
  Ruta: `Tools > Email Redirect`.

- `patch-elementor-related-products-int.php`
  Addon de depuracion que registra en `error_log` argumentos de productos relacionados enviados por Elementor Pro.

- `posts_metadata_viewer.php`
  Metabox de auditoria para ver postmeta y datos `WP_Post` en cualquier post type.

- `public_urls_by_cpt_language.php`
  Shortcode para listar URLs publicadas por idioma y CPT, con filtros y exportacion a Markdown.
  Shortcode: `[public_urls_by_cpt_language]`
  Parametros:
  - `post_types="page,product"` filtra CPT.
  - `show_empty="0|1"` muestra/oculta grupos vacios.
  - `show_titles="0|1"` muestra/oculta titulo junto a URL.
  - `languages="es,en"` incluye solo esos idiomas.
  - `exclude_languages="fr,de"` excluye idiomas.
  - `current_language_only="0|1"` solo idioma actual.

=== WooCommerce (`woo/`) ===

- `limpiar-transients-wc.php`
  Desactiva cache de filtros y limpia transients de WooCommerce de forma periodica.

- `wc-booking-auto-select.php`
  Auto-selecciona franja horaria en productos Bookings cuando solo existe una opcion (WPML compatible).

- `woo-booking-descount.php`
  Aplica descuentos automaticos en WooCommerce Bookings segun productos, dias y fechas configuradas.

- `woo-bookinng-cleaner.php`
  Limpia reservas WooCommerce Bookings canceladas o pendientes segun reglas configurables.

- `woo-cart-cleaner.php`
  Limpia carritos abandonados/caducados en intervalos configurables.

- `woo-cupon-free-cat-date.php`
  Aplica envio gratis automatico por categorias, cantidad minima y fechas, mostrando cupon informativo.

- `woo-order-metadata-viewer.php`
  Metabox de auditoria para ver metadatos/datos de pedido (Woo clasico y HPOS).

- `wooEmailStringEditor.php`
  Editor de textos de emails WooCommerce desde admin sin tocar plantillas core.

- `woocommerce-cupones-admin.php`
  Muestra cupones usados en pedidos WooCommerce (admin y emails de administracion).

- `woocommerce-product-checker.php`
  Shortcodes para comprobar compras por usuario/producto y listar productos comprados:
  - `[check_product_purchased]`
  - `[user_purchased_products]`

=== Multisite (`multisite/`) ===

- `MultisiteOrphanTableScanner.php`
  Escanea la base de datos de la red para detectar tablas huerfanas o no vinculadas a sitios activos.

- `ase-sync-multisite.php`
  Sincroniza configuracion de ASE en toda la red multisite y registra incidencias en log.

- `disable-gutenberg-comments.php`
  Desactiva editor de bloques y comentarios en todos los sitios de la red.

- `elementor-safe-mode.php`
  Safe Mode oficial de Elementor para aislar incidencias en editor.

- `global-variables-multisite.php`
  Gestiona variables globales desde Network Admin sobre un sitio objetivo configurable.

- `miltisite-info.php`
  Panel en red con informacion de sitios y plugins en formato grid.

- `multisite-BunnyCDN-Manager.php`
  Gestion centralizada de BunnyCDN en multisite con ajustes de red y activacion por sitio.

- `multisite-newsletter-popup.php`
  Gestiona popup de newsletter para la red con HTML configurable y control por cookies.

- `multisite-seo-indexing.php`
  Gestiona estado de indexacion SEO (index/noindex) en todos los sitios de la red.

- `sync-elementor-msite.php`
  Sincroniza plantillas Elementor desde un sitio maestro hacia sitios destino.

- `wp-cleaner-mu-plugin.php`
  Limpieza controlada de base de datos para red multisite con panel en Network Admin.

- `mu-db-native-cleaner.php`
  Escanea y limpia rastros de plugins en tablas nativas WP por prefijos (multisite).

- `db-options-cleaner-multisite.php`
  Variante de limpieza por prefijos orientada a tablas nativas/options en multisite.

- `tabla-cleaner-multisite.php`
  Lista y agrupa tablas no-core en multisite para limpieza controlada por grupos.

- `jig-scaner.php`
  Utilidad interna para localizar uso del shortcode `[justified_image_grid]` en la red.
  Actualmente con menu comentado (no expuesto en UI).

=== Privados (`private/`) ===

- `faq-interactive.php`
  FAQ interactiva responsive con AJAX, filtros por categorias y personalizacion visual por shortcode `[faq_interactive]`.

- `verifacwoo-lifetime-network.php`
  Automatiza gestion de socios Lifetime, partners y subagencias sobre flujo WooCommerce.

== FAQ ==

= Se cargan todos los archivos de carpetas automaticamente? =
No. Solo se incluyen los archivos marcados como activos en `active_addons`.

= Que carpeta usa el selector del panel? =
`addons/`, `woo/` y `multisite/`.

= Puedo anadir un modulo nuevo? =
Si. Crea un `.php` dentro de `addons/`, `woo/` o `multisite/` con `Plugin Name` y `Description`.
Para tarjetas completas en UI, anade tambien `Marketing Description` y `Parameters`.
No necesitas tocar `addon-manager.php`.

== Changelog ==

= 1.0.2 =
- Added GitHub Actions release flow for `v*` tags and strict update package (`addon-manager.zip`).
- Release ZIP now excludes `private/` and `RELEASE_UPDATES_GUIDE.md`.

= 3.2.2 =
- Version actual del gestor.
- Documentacion `readme.txt` anadida con catalogo completo de modulos y descripciones.

= 3.2.3 =
- Readme actualizado: documentadas cabeceras `Marketing Description` y `Parameters`.
- Instrucciones de uso ampliadas para alta de addons sin cambios en el core.
