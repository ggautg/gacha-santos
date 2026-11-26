# 07 — Matriz de riesgos

## Índice

- [1. Cómo se priorizó](#1-cómo-se-priorizó)
- [2. Matriz de calor](#2-matriz-de-calor)
- [3. Matriz consolidada de hallazgos](#3-matriz-consolidada-de-hallazgos)
- [4. Orden de ejecución recomendado](#4-orden-de-ejecución-recomendado)
- [5. Riesgos aceptables con decisión explícita](#5-riesgos-aceptables-con-decisión-explícita)

## 1. Cómo se priorizó

La severidad combinada impacto × probabilidad de exposición:

| Nivel | Criterio usado |
| --- | --- |
| Crítica | Afecta a la propiedad de la cuenta o permite control total sin autenticación |
| Alta | Exposición directa en producción o bloqueo de la verificación de calidad |
| Media | Riesgo real mitigado por otro control, o deuda que crece con el uso |
| Baja | Impacto limitado o explotación requiere condiciones poco probables |
| Informativa | Observación sin impacto negativo; se documenta para evitar regresión |

La probabilidad se estimó desde el código, no desde telemetría real: no hay métricas de producción en el repositorio (`NO VERIFICADO`).

## 2. Matriz de calor

| Impacto \ Probabilidad | Baja | Media | Alta |
| --- | --- | --- | --- |
| **Alto** | — | — | `SEC-001` |
| **Medio** | `BE-003`, `BE-004`, `FE-005`, `FE-003` | `SEC-004`, `SEC-005`, `SEC-006`, `SEC-008`, `BE-001`, `BE-002`, `DB-001`, `DB-002`, `DB-003`, `DB-004`, `FE-001`, `FE-002`, `DEVOPS-003`, `DEVOPS-004` | `SEC-002`, `SEC-003`, `DEVOPS-001`, `DEVOPS-002` |
| **Bajo** | `SEC-007`, `SEC-009`, `BE-005`, `DB-005`, `FE-004`, `DEVOPS-005` | — | — |

## 3. Matriz consolidada de hallazgos

| ID | Severidad | Capa | Referencia | Ubicación principal | Esfuerzo | Estado de verificación |
| --- | --- | --- | --- | --- | --- | --- |
| `SEC-001` | Crítica | Seguridad | CWE-306, CWE-639 · OWASP A01 · ASVS V2 | `app/Http/Middleware/EnsurePlayer.php:25-35` | Alto | Verificado estáticamente; explotación en caliente `NO VERIFICADO` |
| `SEC-002` | Alta | Configuración | CWE-489, CWE-250 · OWASP A05 | `.env.example:2-4,20-26` | Bajo | Verificado estáticamente; `.env` real `NO VERIFICADO` |
| `SEC-003` | Alta | Dependencias | CWE-1395 · OWASP A06 | `composer.lock` (`laravel/framework v13.39.0`) | Medio | Verificado con `composer audit --locked` |
| `SEC-004` | Media | Seguridad | CWE-489 · OWASP A01 | `routes/web.php:37-39` | Bajo | Verificado; test de producción cubierto |
| `SEC-005` | Media | Seguridad | CWE-1021, CWE-319 · OWASP A05 | `bootstrap/app.php:21-26` | Medio | Verificado por ausencia en middleware |
| `SEC-006` | Media | Seguridad / Sesión | CWE-614 · ASVS V3.4 | `config/session.php:172`; `.env.example:28-32` | Bajo | Config verificado; producción `NO VERIFICADO` |
| `SEC-007` | Baja | Seguridad | CWE-116 | `app/Console/Commands/RevealSanctum.php:18,25` | Bajo | Verificado estáticamente |
| `SEC-008` | Media | Seguridad / A04 | CWE-770 · OWASP A04 | `routes/web.php:12-39` | Bajo | Verificado por ausencia de `throttle` |
| `SEC-009` | Informativa | Seguridad | CWE-1162 | `app/Models/User.php:13-39` | Bajo | Verificado estáticamente |
| `BE-001` | Media | Backend | SRP · convención `AGENTS.md` | `app/Http/Controllers/CollectionController.php:78-125` | Medio | Verificado estáticamente |
| `BE-002` | Media | Backend | CWE-1049 (patrón) | `app/Services/GachaService.php:24-26,150-160` | Medio | Código verificado; `EXPLAIN` `NO VERIFICADO` |
| `BE-003` | Baja | Backend | SRP · CWE-1120 | `app/Support/DungeonRules.php:1-1460` | Alto | Verificado por recuento y lectura |
| `BE-004` | Baja | Backend | CWE-345 (matiz de contrato) | `app/Http/Controllers/TeamController.php:55-79` | Bajo | Verificado y cubierto por prueba |
| `BE-005` | Informativa | Backend | Convención de errores | `app/Support/ValidationMessages.php:13-29` | No aplica | Verificado estáticamente |
| `DB-001` | Media | Base de datos | CWE-1162 | `database/migrations/2026_10_27_000001_create_sanctorum_tables.php:46,49` | Bajo | Verificado por búsqueda exhaustiva |
| `DB-002` | Media | Base de datos | CWE-770 | `app/Services/GachaService.php:73-79`; `DungeonController.php:101-105` | Medio | Patrón verificado; volumen `NO VERIFICADO` |
| `DB-003` | Media | Base de datos / Infra | 12-Factor III, VI | `.env.example:28,36,38` | Medio | Config verificado; carga `NO VERIFICADO` |
| `DB-004` | Baja | Base de datos | CWE-1049 (patrón) | `2026_10_27_173510_add_dungeon_to_battles_table.php:14` | Bajo | Patrón verificado; `EXPLAIN` `NO VERIFICADO` |
| `DB-005` | Informativa | Base de datos | Integridad referencial | `2026_10_27_000001_create_sanctorum_tables.php:56-63` | No aplica | Verificado estáticamente |
| `FE-001` | Media | Frontend | Convención de rutas nombradas | `resources/js/Layouts/AppLayout.vue:79-83` | Bajo | Verificado estáticamente |
| `FE-002` | Media | Frontend | CWE-362 (cliente) | `resources/js/Pages/Team.vue:49-52,108-114` | Medio | Verificado; prueba en navegador `NO VERIFICADO` |
| `FE-003` | Informativa | Frontend | CWE-400 (mínimo) | `resources/js/Pages/DungeonRun.vue:484-492,811-812` | Bajo | Verificado estáticamente |
| `FE-004` | Informativa | Frontend | ASVS V3 | `resources/js/lib/theme.js:4-6`; `sfx.js:3-15` | No aplica | Verificado estáticamente |
| `FE-005` | Baja | Frontend | WCAG 2.1 SC 2.1.1 | `resources/js/Components/DungeonMap.vue:129-138` | Bajo | Verificado estáticamente |
| `DEVOPS-001` | Alta | DevOps | 12-Factor II · CWE-1104 | `vendor/` y `node_modules/` ausentes | Bajo | Verificado (sin instalación) |
| `DEVOPS-002` | Alta | DevOps | 12-Factor VI, XI, XII | `.github/workflows/` vacío | Medio | Verificado estáticamente |
| `DEVOPS-003` | Media | Dependencias | CWE-1104 | `package.json:10,14`; `vite.config.js:3,13` | Bajo | Verificado estáticamente |
| `DEVOPS-004` | Media | Dependencias | CWE-1395 · 12-Factor II | `package-lock.json`; `npm ls --depth=0` | Bajo | Verificado con ambas salidas |
| `DEVOPS-005` | Baja | DevOps | Convención `AGENTS.md` | `composer.json:36-52`; `package.json:5-8` | Bajo | Verificado estáticamente |

## 4. Orden de ejecución recomendado

| Orden | ID | Por qué ahora |
| ---: | --- | --- |
| 1 | `SEC-001` | Requiere decisión de producto; condiciona el diseño del resto de controles |
| 2 | `SEC-002` | Cambio de dos líneas con impacto inmediato en producción |
| 3 | `SEC-003` | Actualización mecánica; habilita un pipeline limpio |
| 4 | `DEVOPS-001` | Sin dependencias no se puede verificar nada más |
| 5 | `DEVOPS-002` | Fija compuertas para que los arreglos no se pierdan |
| 6 | `SEC-004`, `SEC-005`, `SEC-006`, `SEC-008` | Endurecimiento HTTP y de sesión |
| 7 | `FE-001`, `FE-002`, `DB-001`, `DEVOPS-003`, `DEVOPS-004`, `DEVOPS-005` | Correcciones pequeñas y verificables en la misma iteración |
| 8 | `BE-001`, `BE-002`, `DB-002`, `DB-003`, `DB-004` | Trabajo de fondo con pruebas ya existentes como red de seguridad |
| 9 | `BE-003`, `BE-004`, `FE-005`, `SEC-007`, `SEC-009` | Deuda de diseño y mejoras incrementales |
| 10 | `BE-005`, `DB-005`, `FE-003`, `FE-004` | Mantener; solo vigilancia |

## 5. Riesgos aceptables con decisión explícita

Los siguientes pueden permanecer sin corrección **siempre que se documente la decisión**:

| ID | Condición para aceptarlo |
| --- | --- |
| `SEC-001` | El juego es deliberadamente local y sin recuperación; se avisa al usuario y se limita el daemon a un entorno controlado |
| `DB-003` | El despliegue es de un solo servidor y bajo tráfico; se monitoriza MySQL |
| `DEVOPS-003` | Se planea migrar a Tailwind en el siguiente sprint y se registra la deuda |
| `BE-003` | No se prevé reutilizar las reglas fuera de la mazmorra y las pruebas cubren el comportamiento |

> Nota: `SEC-001` no debe aceptarse de forma implícita. Si no se corrige, debe aparecer en el registro de decisiones de arquitectura con fecha y responsable.
