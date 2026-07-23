# Desarrollador

## Última actualización

2026-07-22 — Tarea: shortcode compra producto

## Relevo breve

- Se actualizó `woo/woocommerce-product-checker.php`.
- `[check_product_purchased]` acepta `product_id` y `user_id` opcionales.
- Si no hay `product_id`, mantiene detección del producto actual.
- Si no hay `user_id`, usa usuario actual.
- `product_id`, `user_id` y GET fallback se saneaban con `absint()`.
- Validación reportada por Desarrollador: `php -l` ok y `git diff --check` ok.

## Hecho en esta tarea

- Compatibilidad mantenida para `[check_product_purchased]` sin parámetros.

## Pendiente / riesgos

- Pendiente QA manual corto en single product y página normal con `product_id`.

## No volver a investigar

- El retorno debe seguir siendo `1` o `0`.

## Relevo para

→ Siguiente rol recomendado: Tester QA

Debe leer:
- `_dev/estado.md`
- `_dev/departamentos/desarrollador.md`

Tarea que recibe:
- Validar shortcode sin parámetros en single product y con `product_id` en una página normal.
