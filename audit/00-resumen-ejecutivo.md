# Auditoría integral de Sanctorum — Resumen ejecutivo

| Campo | Valor |
| --- | --- |
| Proyecto | `gacha-santos` (Sanctorum, lanzador de santos) |
| Fecha de la auditoría | 2026-11-26 |
| Commit analizado | `9f1253d` — `Refina mazmorras, accesibilidad e instalación` (2026-11-23) |
| Estado del árbol de trabajo | Limpio (`git status --short` sin salida) |
| Alcance | Backend Laravel 13, MySQL 8.0, frontend Vue 3 + Inertia, dependencias y despliegue |
| Metodología | Revisión estática de código y configuración; comandos de solo lectura |
| Modo | **Solo lectura**: no se modificó código, migraciones ni datos; solo se crea esta documentación |

## Índice

- [1. Alcance y método](#1-alcance-y-método)
- [2. Resultado en cifras](#2-resultado-en-cifras)
- [3. Hallazgos que requieren decisión inmediata](#3-hallazgos-que-requieren-decisión-inmediata)
- [4. Riesgo dominante](#4-riesgo-dominante)
- [5. prácticas sólidas confirmadas](#5-prácticas-sólidas-confirmadas)
- [6. Limitaciones de verificación](#6-limitaciones-de-verificación)
- [7. Hoja de ruta recomendada](#7-hoja-de-ruta-recomendada)
- [8. Mapa del entregable](#8-mapa-del-entregable)

## 1. Alcance y método

Se revisaron las capas configuración, HTTP, dominio, persistencia, frontend, dependencias, pruebas y operaciones. Cada hallazgo se documenta con `archivo:línea` y un fragmento verificable. Cuando una comprobación no fue posible se marca **`NO VERIFICADO`** y se explica qué falta, sin inferir un resultado.

La auditoría es estática porque el checkout no contiene `vendor/` ni `node_modules/`. Por ello **no se ejecutaron** `php artisan test`, `vendor/bin/pint`, `npm run build` ni consultas directas a MySQL. Sí se ejecutaron `composer audit --locked --format=json` y `npm audit --json`, que operan sobre los archivos de bloqueo.

## 2. Resultado en cifras

| Severidad | Cantidad | IDs |
| --- | ---: | --- |
| Crítica | 1 | `SEC-001` |
| Alta | 4 | `SEC-002`, `SEC-003`, `DEVOPS-001`, `DEVOPS-002` |
| Media | 14 | `SEC-004`, `SEC-005`, `SEC-006`, `SEC-008`, `BE-001`, `BE-002`, `DB-001`, `DB-002`, `DB-003`, `DB-004`, `FE-001`, `FE-002`, `DEVOPS-003`, `DEVOPS-004` |
| Baja | 5 | `SEC-007`, `BE-003`, `BE-004`, `FE-005`, `DEVOPS-005` |
| Informativa | 5 | `SEC-009`, `BE-005`, `DB-005`, `FE-003`, `FE-004` |
| **Total** | **29** | — |

Distribución por capa: Seguridad 9, Backend 5, Base de datos 5, Frontend 5, Dependencias/DevOps 5.

## 3. Hallazgos que requieren decisión inmediata

1. **`SEC-001` (Crítica).** No existe autenticación: la identidad es un `Player` creado automáticamente y ligado a la cookie de sesión. Quien controle la cookie controla toda la cuenta. Es una decisión de producto, no un defecto de implementación, pero debe aceptarse por escrito o corregirse.
2. **`SEC-003` (Alta).** `composer.lock` contiene 15 avisos de seguridad, incluidos `laravel/framework` `v13.39.0` (afectado por `CVE-2026-128083`) y `symfony/*` `v7.4.21`. La actualización es mecánica y tiene bajo riesgo de regresión.
3. **`SEC-002` (Alta).** `.env.example` propone `APP_DEBUG=true` y `DB_USERNAME=root`. Copiar la plantilla a producción expone trazas de error y privilegios de administrador de base de datos.
4. **`DEVOPS-001` / `DEVOPS-002` (Alta).** No hay dependencias instaladas ni pipeline de CI: hoy nadie ejecuta automáticamente Pint, PHPUnit ni el build antes de publicar.

## 4. Riesgo dominante

El riesgo dominante no es una inyección SQL ni un XSS —no se encontraron— sino la **ausencia de un límite de confianza**. `EnsurePlayer` acepta cualquier visita, emite una identidad persistente y todas las rutas mutadoras operan sobre esa identidad sin autorización adicional (`app/Http/Middleware/EnsurePlayer.php:25-35`, `routes/web.php:12-39`). La buena noticia es que el resto del sistema compensa parcialmente: validación estricta en servidor, transacciones con `lockForUpdate()`, CSRF en todas las peticiones `POST` y mensajes de error en español.

El segundo eje de riesgo es **operativo**: el proyecto funciona en local, pero no hay evidencia de que pase compuertas automáticas antes de desplegarse.

## 5. prácticas sólidas confirmadas

- Transacciones con bloqueo de fila en economía, equipos y mazmorras: `app/Services/GachaService.php:24-26`, `app/Http/Controllers/CollectionController.php:89-91`, `app/Support/DungeonRules.php:353-357`.
- Validación en servidor con mensajes centralizados en español: `app/Support/ValidationMessages.php:13-29`.
- CSRF explícito en el cliente: `resources/js/lib/api.js:19-35` y `resources/views/app.blade.php:6`.
- Accesibilidad real: foco visible, `role="alertdialog"`, `aria-live` y `prefers-reduced-motion` (`resources/css/app.css:202-205`, `resources/js/Components/ConfirmDialog.vue:18-33`).
- Cobertura funcional amplia: 55 métodos de prueba en 10 archivos, con `RefreshDatabase` y factories.
- SVG servidos con `X-Content-Type-Options: nosniff` (`app/Http/Controllers/ArtController.php:43-49`).

La lista completa está en [09-buenas-practicas-encontradas.md](09-buenas-practicas-encontradas.md).

## 6. Limitaciones de verificación

| Comprobación | Estado | Motivo |
| --- | --- | --- |
| `php artisan test --compact` | `NO VERIFICADO` | Falta `vendor/autoload.php` |
| `vendor/bin/pint --dirty` | `NO VERIFICADO` | Pint no instalado |
| `npm run build` | `NO VERIFICADO` | `node_modules/` ausente |
| Consultas a MySQL (índices reales, planes, volumen) | `NO VERIFICADO` | Auditoría de solo lectura; no se conectó a la BD |
| `composer audit --locked` | Verificado | Funciona sobre `composer.lock` |
| `npm audit --json` | Verificado | 0 vulnerabilidades sobre `package-lock.json` (163 dependencias declaradas) |
| Flag `secure` de la cookie en producción | `NO VERIFICADO` | No hay `.env` real en el checkout |

## 7. Hoja de ruta recomendada

| Horizonte | Acciones | IDs |
| --- | --- | --- |
| 0-7 días | Actualizar dependencias PHP; fijar `APP_DEBUG=false` y usuario DB no root; decidir política de identidad | `SEC-003`, `SEC-002`, `SEC-001` |
| 1-4 semanas | Middleware de headers de seguridad; `throttle` en rutas mutadoras; condicionar ruta de desarrollo; CI con Pint + tests + build | `SEC-005`, `SEC-008`, `SEC-004`, `DEVOPS-001`, `DEVOPS-002` |
| 1-3 meses | Extraer servicios de dominio; limpiar columnas huérfanas; política de retención; quitar Tailwind del build | `BE-001`, `BE-003`, `DB-001`, `DB-002`, `DEVOPS-003` |

Detalle en [08-plan-de-remediacion.md](08-plan-de-remediacion.md).

## 8. Mapa del entregable

| Archivo | Contenido |
| --- | --- |
| [01-arquitectura.md](01-arquitectura.md) | Estructura, flujo de petición, límites de confianza |
| [02-seguridad.md](02-seguridad.md) | 9 hallazgos de seguridad con OWASP/ASVS/CWE |
| [03-backend.md](03-backend.md) | Controladores, servicios, validación y deuda de diseño |
| [04-base-de-datos.md](04-base-de-datos.md) | Esquema, índices, integridad y crecimiento |
| [05-frontend.md](05-frontend.md) | Vue, Inertia, accesibilidad y ciclo de vida |
| [06-dependencias-devops.md](06-dependencias-devops.md) | Lockfiles, auditorías, build y despliegue |
| [07-matriz-de-riesgos.md](07-matriz-de-riesgos.md) | Matriz consolidada y priorización |
| [08-plan-de-remediacion.md](08-plan-de-remediacion.md) | Plan por fases con criterios de aceptación |
| [09-buenas-practicas-encontradas.md](09-buenas-practicas-encontradas.md) | Controles correctos ya presentes |
| [10-anexos.md](10-anexos.md) | Comandos, salidas, inventario y alcance excluido |
