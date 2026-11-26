# 08 — Plan de remediación

## Índice

- [1. Principios del plan](#1-principios-del-plan)
- [2. Fase 0 — Decisión y habilitación (días 0-3)](#2-fase-0--decisión-y-habilitación-días-0-3)
- [3. Fase 1 — Correcciones inmediatas (día 1-7)](#3-fase-1--correcciones-inmediatas-día-1-7)
- [4. Fase 2 — Endurecimiento y CI (semanas 2-4)](#4-fase-2--endurecimiento-y-ci-semanas-2-4)
- [5. Fase 3 — Deuda de diseño (meses 2-3)](#5-fase-3--deuda-de-diseño-meses-2-3)
- [6. Criterios de aceptación globales](#6-criterios-de-aceptación-globales)
- [7. Seguimiento](#7-seguimiento)
- [8. Resultado de la Fase 0 (2026-11-26)](#8-resultado-de-la-fase-0-2026-11-26)
- [9. Resultado de la acción 1.1 (2026-11-26)](#9-resultado-de-la-acción-11-2026-11-26)

## 1. Principios del plan

1. **Nada se da por arreglado sin evidencia.** Cada ítem termina con el comando o la prueba que lo demuestra.
2. **Sin cambios de comportamiento accidental.** Las 55 pruebas existentes son la red de seguridad, pero primero deben poder ejecutarse.
3. **Un arreglo, una verificación.** No se agrupan cambios de seguridad con refactorizaciones grandes.
4. **Registrar decisiones aceptadas.** Especialmente `SEC-001`.

## 2. Fase 0 — Decisión y habilitación (días 0-3)

| # | Acción | IDs relacionados | Responsable sugerido | Criterio de aceptación |
| ---: | --- | --- | --- | --- |
| 0.1 | Decidir si Sanctorum sigue siendo una cuenta local sin login o pasa a autenticación real | `SEC-001` | Producto + Arquitectura | ADR en `docs/arquitectura.md` con fecha y responsable |
| 0.2 | Instalar dependencias (`composer install`, `npm ci`) sin tocar el código | `DEVOPS-001`, `DEVOPS-004` | Desarrollo | `php artisan about --json` y `npm ls --depth=0` correctos |
| 0.3 | Ejecutar la línea base de verificación y guardar la salida | `DEVOPS-001` | Desarrollo | 55 pruebas pasan, Pint limpio, build correcto, `git diff --check` sin salida |
| 0.4 | Crear `.env` local con `APP_DEBUG=false` y usuario DB no root | `SEC-002` | Desarrollo | `php artisan about --only=environment` muestra `debug => false` |

Comandos de la línea base:

```bash
composer install --no-interaction --prefer-dist
npm ci
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

## 3. Fase 1 — Correcciones inmediatas (día 1-7)

| # | Acción | IDs | Esfuerzo | Criterio de aceptación |
| ---: | --- | --- | --- | --- |
| 1.1 | Actualizar `laravel/framework` y `symfony/*` a versiones corregidas | `SEC-003` | Medio | `composer audit --locked` sin advisories y suite en verde |
| 1.2 | Fijar plantilla de entorno segura (`APP_ENV`, `APP_DEBUG`, `LOG_LEVEL`, DB user) | `SEC-002` | Bajo | `git diff` muestra los cambios; despliegue con debug desactivado |
| 1.3 | Configurar `SESSION_SECURE_COOKIE` y `SESSION_SAME_SITE` en producción | `SEC-006` | Bajo | Cabecera `Set-Cookie` con `Secure; SameSite=Lax; HttpOnly` |
| 1.4 | Condicionar la ruta de desarrollo y añadir `throttle` | `SEC-004`, `SEC-008` | Bajo | `GET /` en producción no expone `canResetAccount`; 429 tras 11 peticiones a `/gacha` |
| 1.5 | Añadir middleware de cabeceras de seguridad en modo report-only para CSP | `SEC-005` | Medio | `curl -I /` muestra `X-Frame-Options`, `Referrer-Policy` y CSP report-only |
| 1.6 | Publicar `resetRoute` desde Inertia y usarlo en `AppLayout.vue` | `FE-001` | Bajo | Sin literales de ruta en `resources/js/`; botón funciona en local |
| 1.7 | Eliminar columnas `depth` y `free_pull_at` y sus referencias | `DB-001` | Bajo | Migración aplicada en local; búsqueda sin coincidencias en `app/` |

## 4. Fase 2 — Endurecimiento y CI (semanas 2-4)

| # | Acción | IDs | Esfuerzo | Criterio de aceptación |
| ---: | --- | --- | --- | --- |
| 2.1 | Crear workflow de CI con instalación, auditorías, Pint, tests y build | `DEVOPS-002`, `DEVOPS-005` | Medio | PR de prueba falla si se introduce un error de formato |
| 2.2 | Unificar verificación local en `composer verify` o `make verify` | `DEVOPS-005` | Bajo | Un solo comando ejecuta las cuatro compuertas |
| 2.3 | Quitar Tailwind de `package.json` y `vite.config.js`; regenerar lockfile | `DEVOPS-003` | Bajo | `npm run build` correcto y sin menciones a Tailwind en el build |
| 2.4 | Hacer `npm audit` después de `npm ci` en CI | `DEVOPS-004` | Bajo | Pipeline usa `npm ci`, nunca `npm install` |
| 2.5 | Aplicar la decisión de `SEC-001`: autenticación o endurecimiento documentado | `SEC-001` | Alto | Prueba que verifique propiedad de cuenta o ADR firmado + controles compensatorios |
| 2.6 | Corregir el ciclo de guardado de `Team.vue` | `FE-002` | Medio | Prueba manual: cambiar equipo y navegar inmediatamente conserva el cambio |
| 2.7 | Añadir teclado al viewport del mapa | `FE-005` | Bajo | `Tab` + flechas desplazan el mapa ampliado; foco visible |

## 5. Fase 3 — Deuda de diseño (meses 2-3)

| # | Acción | IDs | Esfuerzo | Criterio de aceptación |
| ---: | --- | --- | --- | --- |
| 3.1 | Extraer `CollectionService` y mover la transacción de nivel | `BE-001` | Medio | `CollectionController` solo valida y responde; `CollectionTest` en verde |
| 3.2 | Eliminar `inRandomOrder()` del gacha y elegir índice en PHP | `BE-002` | Medio | Misma distribución estadística verificada por prueba; sin `ORDER BY RAND()` |
| 3.3 | Dividir `DungeonRules` en generador, RNG, resolutor y fachada | `BE-003` | Alto | Sin archivo > 400 líneas en `app/Support`; 24 pruebas de mazmorra en verde |
| 3.4 | Unificar el rechazo de santos no poseídos en ambos endpoints | `BE-004` | Bajo | 422 con mensaje en español; `TeamTest` actualizado |
| 3.5 | Política de retención para `battles` y `pulls` + job programado | `DB-002` | Medio | Job ejecuta en lotes de 500; métrica de filas publicada |
| 3.6 | Migrar sesión/caché/colas a Redis y validar bajo carga moderada | `DB-003` | Medio | Tiempo de respuesta de `/gacha` estable en prueba de carga |
| 3.7 | Añadir índice `(player_id, dungeon, id)` y verificar con `EXPLAIN` | `DB-004` | Bajo | Plan sin `filesort` para la consulta de historial |
| 3.8 | Retirar ofuscación base64 y modelo `User` sin uso | `SEC-007`, `SEC-009` | Bajo | Búsqueda de `base64_decode` sin resultados; esquema sin tablas muertas |

## 6. Criterios de aceptación globales

Todo cambio se considera terminado cuando:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

y además:

- Los mensajes de error siguen en español con formato `{ message, errors }`.
- No se introducen `window.confirm`, `window.alert`, `v-html`, `{!! !!}` ni secretos en `.env.example`.
- Se documenta el cambio en `docs/arquitectura.md`, `docs/frontend.md`, `docs/mazmorras.md` o `docs/testing.md`, según corresponda.
- Los archivos nuevos de PHP llevan PHPDoc de clase y de métodos propios.

## 7. Seguimiento

| Métrica | Línea base | Objetivo |
| --- | --- | --- |
| Advisories de `composer audit --locked` | 0 (2026-11-26; línea base 15) | 0 |
| Hallazgos Críticos / Altos abiertos | 5 (ADR-001 registrada; `SEC-001` sigue abierta en código) | 0 (o ADR firmado para `SEC-001`) |
| Comandos de verificación ejecutados en CI | 0 | 4 |
| Comandos de verificación ejecutados en local | 4 (2026-11-26) | 4 |
| Archivos PHP > 1000 líneas | 1 (`DungeonRules`) | 0 |
| Columnas sin uso en `players` | 2 | 0 |
| Pruebas automatizadas en verde | 55/55 (2026-11-26) | 55 |

La matriz de la que sale este plan está en [07-matriz-de-riesgos.md](07-matriz-de-riesgos.md).

## 8. Resultado de la Fase 0 (2026-11-26)

| Acción | Estado | Evidencia |
| --- | --- | --- |
| 0.1 Decisión de `SEC-001` | Completada | `ADR-001` en `docs/arquitectura.md`: autenticación real, con implementación prevista en la acción 2.5. |
| 0.2 Instalar dependencias | Completada | `composer install` instaló 117 paquetes; `npm ci` instaló 78; `php artisan about --json` y `npm ls --depth=0` correctos. Queda una dependencia opcional no satisfecha (`@laravel/multiplex`), sin impacto en el build. |
| 0.3 Línea base de verificación | Completada | `php artisan test --compact`: 55/55 pruebas y 1252 aserciones; `vendor/bin/pint --dirty --format agent`: `passed`; `npm run build`: 608 módulos transformados; `git diff --check`: sin salida. |
| 0.4 Entorno local | Completada | `.env` con `APP_DEBUG=false` y usuario MySQL `sanctorum` (no root); `php artisan about --only=environment` muestra `Debug Mode .. OFF`; `php artisan db:show` conecta a MySQL 8.0.35. |

Notas de la ejecución:

- La línea base no podía arrancar sin `APP_KEY`; se generó una clave y se guardó únicamente en `.env`, que está ignorado por Git.
- `SEC-001` sigue abierta en el código: la ADR fija la decisión de producto, pero la autenticación real se implementará en la acción 2.5.
- `composer audit --locked` tenía 15 advisories en la línea base; la acción 1.1 los resolvió (véase la sección 9).
- No se ha creado CI; la acción 2.1 sigue pendiente.

## 9. Resultado de la acción 1.1 (2026-11-26)

| Paquete | Antes | Después |
| --- | --- | --- |
| `laravel/framework` | v13.39.0 | v13.44.1 |
| `symfony/http-foundation` | v7.4.21 | v7.4.24 |
| `symfony/http-kernel` | v7.4.21 | v7.4.24 |
| `symfony/mailer` | v7.4.19 | v7.4.24 |
| `symfony/mime` | v7.4.19 | v7.4.24 |
| `symfony/routing` | v7.4.21 | v7.4.24 |
| `symfony/yaml` | v7.4.21 | v7.4.24 |
| Advisories de `composer audit --locked` | 15 | 0 |

Comando ejecutado:

```bash
composer update laravel/framework:13.44.1 \
  symfony/http-foundation:7.4.24 symfony/http-kernel:7.4.24 \
  symfony/mailer:7.4.24 symfony/mime:7.4.24 \
  symfony/routing:7.4.24 symfony/yaml:7.4.24 \
  --with-all-dependencies --no-interaction
```

Se actualizaron 35 paquetes en total, sin altas ni bajas. `composer.json` no cambió: la restricción `^13.17` ya permitía la versión corregida, por lo que el cambio quedó registrado solo en `composer.lock`.

Verificación: `composer audit --locked --format=json` devolvió `{"advisories":[],"abandoned":[],"filter":[]}`; las 55 pruebas siguen en verde, Pint `passed`, `npm run build` correcto y `git diff --check` sin salida.
