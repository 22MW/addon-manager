# Addon Manager - Release Flow (Auto ZIP, no `private/`)

## Objetivo

- Mantener `private/` en GitHub.
- Excluir `private/` del ZIP de actualización de WordPress.
- Publicar releases automáticos por tag (`v*`), sin crear ZIP manual.

## Cómo queda implementado

- Workflow: `.github/workflows/release.yml`
- Trigger: push de tag `v*`
- Build: crea `addon-manager.zip` con raíz `addon-manager/`
- Exclusiones: `.git`, `.github`, `dist`, `node_modules`, `vendor`, `private`, logs y `.DS_Store`
- Release: crea GitHub Release y adjunta `addon-manager.zip`

## Regla técnica importante

- El updater del plugin ahora acepta solo el asset `addon-manager.zip`.
- Si el release no trae ese asset, no ofrece actualización.
- Así evitamos que WordPress use el ZIP de source code (que podría incluir `private/`).

## Política de versionado y documentación

- Incrementar el último número de versión salvo indicación distinta.
- `CHANGELOG.md`: entradas cortas, precisas, en inglés.
- Actualizar `readme.txt` solo si hay funcionalidad nueva que lo requiera.
- Commit y push solo cuando el usuario lo pida explícitamente.

## Flujo de publicación

1. Bump de versión en cabecera del plugin.
2. Entrada en `CHANGELOG.md`.
3. Actualizar `readme.txt` si aplica.
4. `php -l` + `git diff --check`.
5. Commit + push a rama de desarrollo (`dev` o la rama equivalente del repo).
6. Merge de rama de desarrollo a `main` + push `main`.
7. Tag `vX.Y.Z` + push tag.
8. GitHub Action crea Release + `addon-manager.zip` automáticamente.

## Prompt recomendado (copy/paste)

```text
En /Users/22mw/Local Sites/verifacwoo/app/public/wp-content/plugins/addon-manager aplica este flujo:
1) Incrementa la versión patch (salvo que te diga otra estrategia) en la cabecera del plugin.
2) Añade entrada corta y precisa en inglés en CHANGELOG.md para la nueva versión.
3) Actualiza DOCUMENTATION.md, README.md y readme.txt solo si hay funcionalidad nueva.
4) Ejecuta php -l en PHP tocados y git diff --check.
5) Si te lo confirmo explícitamente: commit + push a la rama de desarrollo.
6) Si te lo confirmo explícitamente: merge desarrollo -> main y push main.
7) Si te lo confirmo explícitamente: crea tag vX.Y.Z y push del tag.
8) Verifica que el workflow de GitHub Release publicó addon-manager.zip y que no contiene private/.
No hagas cambios extra fuera de este flujo.
```

## Prompts fijos (sin editar)

### Prompt 1 - Release completo automático

```text
En /Users/22mw/Local Sites/verifacwoo/app/public/wp-content/plugins/addon-manager ejecuta release completo automático:
1) Incrementa versión PATCH en la cabecera del plugin.
2) Añade entrada corta y precisa en CHANGELOG.md.
3) Actualiza   readme.txt solo si hay funcionalidad nueva.
4) Ejecuta php -l en todos los PHP tocados y git diff --check.
5) Commit y push a mishaDev.
6) Merge mishaDev -> main y push main.
7) Crea tag con formato vX.Y.Z usando la nueva versión y haz push del tag.
8) Verifica que GitHub Action creó el Release con addon-manager.zip y que el ZIP no incluye private/.
No hagas cambios fuera de este flujo.
```

### Prompt 2 - Solo preparar, sin publicar

```text
En /Users/22mw/Local Sites/verifacwoo/app/public/wp-content/plugins/addon-manager prepara release local sin publicar:
1) Incrementa versión PATCH en la cabecera del plugin.
2) Añade entrada corta y precisa en inglés en CHANGELOG.md.
3) Actualiza  readme.txt solo si hay funcionalidad nueva.
4) Ejecuta php -l en todos los PHP tocados y git diff --check.
5) Muestra resumen final de cambios y comandos exactos pendientes para push, merge y tag.
No hagas commit, no hagas push y no crees tag.
```
