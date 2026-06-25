# Release Notes Operativas

## Última actualización

2026-06-26

## Entrará en la próxima release

- `1.0.6.1`: hardening de subida/activación de addons de usuario.
- Validación contextual de acciones runtime peligrosas compatible con reglas WordPress.
- Feedback visible y mensajes humanos en bloqueos de seguridad.
- Guards `ABSPATH` en addons distribuibles.

## Queda fuera

- Tag/release estable.
- ZIP de publicación.
- Deploy.

## Validaciones pendientes

- QA funcional en entorno de prueba.
- Confirmar subida permitida de `wooEmailStringEditor.php`.
- Confirmar bloqueo de addon con salida antes de redirect.
- Revisión de exclusiones de ZIP/release.
- Confirmar `Stable tag` frente a versión real del plugin.

## Riesgos antes de publicar

- `readme.txt` indica `Stable tag: 1.0.4`, mientras `addon-manager.php` indica `1.0.6`.
- La carpeta `private/` necesita política clara antes de empaquetar o distribuir.
- `_dev/` debe quedar excluido de cualquier ZIP, release o deploy público.

## Limpieza post-release

- Actualizar estado, roadmap y visual después de publicar si se autoriza una release futura.
