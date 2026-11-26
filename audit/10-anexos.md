# 10 — Anexos

## Índice

- [1. Alcance de la auditoría](#1-alcance-de-la-auditoría)
- [2. Comandos ejecutados y salidas](#2-comandos-ejecutados-y-salidas)
- [3. Detalle de `composer audit --locked`](#3-detalle-de-composer-audit---locked)
- [4. Inventario de archivos revisados](#4-inventario-de-archivos-revisados)
- [5. Búsquedas estáticas y resultados negativos](#5-búsquedas-estáticas-y-resultados-negativos)
- [6. Formato de hallazgo](#6-formato-de-hallazgo)
- [7. Limitaciones y trabajo no realizado](#7-limitaciones-y-trabajo-no-realizado)
- [8. Glosario](#8-glosario)
- [9. Actualización tras la Fase 0 (2026-11-26)](#9-actualización-tras-la-fase-0-2026-11-26)
- [10. Actualización tras la acción 1.1 (2026-11-26)](#10-actualización-tras-la-acción-11-2026-11-26)

## 1. Alcance de la auditoría

**Incluido:**

- Configuración: `.env.example`, `config/*.php`, `bootstrap/app.php`, `phpunit.xml`, `composer.json`, `package.json`, `vite.config.js`.
- HTTP: `routes/web.php`, controladores, middleware y excepciones.
- Dominio: modelos Eloquent, servicios, `app/Support`, comandos Artisan.
- Persistencia: todas las migraciones, seeders y factories relevantes.
- Frontend: `resources/js/**`, `resources/views/app.blade.php`, `resources/css/app.css`.
- Pruebas: `tests/**` (10 archivos, 55 métodos `test_*`).
- Operaciones: lockfiles, auditorías de dependencias, presencia de CI y manifiestos de contenedor.

**Excluido o no verificado:**

- Ejecución de la aplicación, migraciones en MySQL real, seeders y suite de pruebas.
- Tráfico de red en caliente a `https://bible.helloao.org/api`.
- Revisión de una instancia de producción, sus logs, variables de entorno reales o proxies inversos.
- Pruebas de penetración, fuzzing, pruebas de carga y revisión de navegador.
- Dependencias transitivas instaladas (no hay `vendor/` ni `node_modules/`).

La auditoría fue de **solo lectura**: no se modificó código, migraciones ni datos; solo se crearon los 11 archivos de `audit/`.

## 2. Comandos ejecutados y salidas

| Comando | Resultado | Uso en el informe |
| --- | --- | --- |
| `php -v` | PHP 8.3.36 | Stack (`01`, `06`) |
| `node --version` | v26.13.0 | Stack (`06`) |
| `npm --version` | 11.20.0 | Stack (`06`) |
| `composer --version` | Composer 2.10.2 | Stack (`06`) |
| `Get-Service MySQL80` | `Running`, MySQL 8.0.35 | Stack (`01`, `06`) |
| `git status --short` | Sin salida (árbol limpio) | Encabezado (`00`) |
| `git log -1 --format='%h %ci %s'` | `9f1253d 2026-11-23 10:53:37 -0300 Refina mazmorras, accesibilidad e instalación` | Encabezado (`00`) |
| `git log --all --oneline -S"password" -S"secret" -S"api_key" -- . ':!.env'` | Solo `cd7dea5 Initial commit` | Control positivo (`09`) |
| `composer audit --format=json` | Error: `No installed packages found. Please run "composer install" before running "audit" or pass "--locked"` | `DEVOPS-001` |
| `composer audit --locked --format=json` | 15 advisories | `SEC-003`, `DEVOPS` |
| `npm audit --json` | `{"info":0,"low":0,"moderate":0,"high":0,"critical":0,"total":0}` | `DEVOPS-004` |
| `npm audit --json` (metadata) | `prod 34, dev 91, optional 89, total 163` | `DEVOPS-004` |
| `npm ls --depth=0` | `ELSPROBLEMS`, ocho `UNMET DEPENDENCY` | `DEVOPS-004` |
| `Test-Path vendor` | `False` | `DEVOPS-001` |
| `Test-Path node_modules` | `False` | `DEVOPS-001` |
| `php artisan about --json --only=environment` | `Failed to open stream ... vendor/autoload.php ... artisan on line 10` | `DEVOPS-001` |
| `php artisan route:list --json --except-vendor` | Misma causa; se sustituyó por lectura de `routes/web.php` | `DEVOPS-001`, `01` |
| `Get-ChildItem -File .github/workflows` | Sin salida | `DEVOPS-002` |
| Búsqueda de `Dockerfile`, `docker-compose`, `Procfile` | Sin coincidencias | `DEVOPS-002` |
| `(Get-Content app/Support/DungeonRules.php).Count` | `1460` | `BE-003` |
| `(Get-Content app/Services/BattleService.php).Count` | `669` | `BE-003` |
| Recuento de `public function test_` en `tests/**` | `55` en 10 archivos | `03`, `09` |

Salidas textuales relevantes:

```text
php artisan about --json --only=environment
Failed to open stream: No such file or directory ... vendor/autoload.php ... artisan on line 10
```

```text
composer audit --format=json
No installed packages found. Please run "composer install" before running "audit" or pass "--locked" to audit the lock file.
```

```text
npm ls --depth=0
+-- UNMET DEPENDENCY @inertiajs/vue3@^3.9.1
+-- UNMET OPTIONAL DEPENDENCY @laravel/multiplex@^0.4.1
+-- UNMET DEPENDENCY @tailwindcss/vite@^4.0.0
+-- UNMET DEPENDENCY @vitejs/plugin-vue@^6.0.10
+-- UNMET DEPENDENCY concurrently@^10.0.3
+-- UNMET DEPENDENCY laravel-vite-plugin@^3.1
+-- UNMET DEPENDENCY tailwindcss@^4.0.0
+-- UNMET DEPENDENCY vite@^8.0.0
`-- UNMET DEPENDENCY vue@^3.5.44
npm error code ELSPROBLEMS
```

## 3. Detalle de `composer audit --locked`

Estado a 2026-11-26: los 15 avisos se resolvieron con la acción 1.1 (véase la [sección 10](#10-actualización-tras-la-acción-11-2026-11-26)). El detalle se conserva como línea base de la auditoría.

Versiones bloqueadas en la línea base: `laravel/framework v13.39.0`, `inertiajs/inertia-laravel v3.5.2`, `symfony/http-foundation v7.4.21`, `symfony/http-kernel v7.4.21`, `symfony/mailer v7.4.19`, `symfony/mime v7.4.19`, `symfony/routing v7.4.21`, `symfony/yaml v7.4.21`.

| # | Paquete | CVE | Severidad declarada | Título del aviso |
| ---: | --- | --- | --- | --- |
| 1 | `laravel/framework` | `CVE-2026-128083` | low | Laravel: Task Output Injection - Concurrency Process |
| 2 | `symfony/http-foundation` | `CVE-2026-121619` | — | `private_ranges` Trusted Proxies trusts IPv4-mapped/6to4/Teredo/NAT64 addresses |
| 3 | `symfony/http-foundation` | `CVE-2026-133748` | — | `NoPrivateNetworkHttpClient` with custom subnets lets IPv4-mapped addresses through |
| 4 | `symfony/http-foundation` | `CVE-2026-135802` | — | Generated URLs can point to a host chosen by the client |
| 5 | `symfony/http-foundation` | `CVE-2026-135801` | — | Path rules enforced in front of PHP can be bypassed by appending the path to the script name |
| 6 | `symfony/http-foundation` | `CVE-2026-118514` | — | HEAD requests bypass `methods` option of access-control rules (incomplete fix for `CVE-2026-45075`) |
| 7 | `symfony/http-kernel` | `CVE-2026-133743` | — | Inline fragments let untrusted forwarded headers change `asset()`/`absolute_url()` (incomplete fix for `CVE-2026-123339`) |
| 8 | `symfony/http-kernel` | `CVE-2026-123339` | — | Inline fragments let untrusted forwarded headers set host, scheme and port |
| 9 | `symfony/mailer` | `CVE-2026-133751` | — | Mailer `require_tls` can be bypassed by a man in the middle |
| 10 | `symfony/mailer` | `CVE-2026-133755` | — | Mailer trusts replies injected right after STARTTLS |
| 11 | `symfony/mime` | `CVE-2026-45070` | medium | Email header injection via non-token characters in MIME parameter names |
| 12 | `symfony/routing` | `CVE-2026-133746` | — | `UrlGenerator` can still generate URLs to another host (incomplete fix for `CVE-2026-45065`) |
| 13 | `symfony/routing` | `CVE-2026-133754` | — | `UrlGenerator` lets host placeholder values send generated URLs to another host |
| 14 | `symfony/routing` | `CVE-2026-118516` | — | Host placeholders can match the start of the path in compiled matchers |
| 15 | `symfony/yaml` | `CVE-2026-123792` | — | Exponential/quadratic parse times let a single document hold a process |

**Total: 15 avisos.** Trece no incluyen el campo `severity` en la salida de `composer audit`; se muestra `—` en lugar de inventar una severidad.

Rangos afectados citados con más frecuencia (rama 7.4): `< 7.4.22`, `< 7.4.23` y `< 7.4.24` según el aviso. La recomendación general de actualización es `laravel/framework >= 13.40.0` y `symfony/* >= 7.4.24` dentro de la rama 7.4, sujeto a verificación de regresión.

## 4. Inventario de archivos revisados

| Área | Archivos | Recuento / detalle |
| --- | --- | --- |
| Configuración raíz | `.env.example`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vite.config.js`, `phpunit.xml`, `AGENTS.md`, `README.md` | 9 |
| Bootstrap y rutas | `bootstrap/app.php`, `routes/web.php`, `routes/console.php` | 3 |
| Middleware | `EnsurePlayer.php`, `HandleInertiaRequests.php` | 2 |
| Controladores | `SanctuaryController`, `GachaController`, `CollectionController`, `TeamController`, `DungeonController`, `ArtController`, `DevAccountController` | 7 |
| Servicios y soporte | `GachaService`, `BattleService`, `BibleImporter`, `DungeonRules`, `PixelArt`, `ValidationMessages`, `CardPresenter` | 7 |
| Modelos | `Player`, `Saint`, `SaintCopy`, `Pull`, `Battle`, `DungeonRun`, `DungeonProgress`, `GospelVerse`, `User` | 9 |
| Comandos | `BibleImport`, `RevealSanctum` | 2 |
| Config `config/` | `app`, `sanctorum`, `bible`, `database`, `session`, `cache`, `queue`, `logging`, `filesystems` | 9 (las más relevantes leídas íntegramente o en secciones) |
| Migraciones | 13 archivos | Listadas en `04-base-de-datos.md` |
| Seeders / factories | `SaintSeeder`, factories de `User` y `GospelVerse` | 3 |
| Frontend | `resources/js/**` (24 archivos), `resources/views/app.blade.php`, `resources/css/app.css` | 26 |
| Pruebas | 10 archivos PHP | 55 métodos `test_*` |
| Documentación existente | `docs/*.md` | Revisada para coherencia; no modificada |

## 5. Búsquedas estáticas y resultados negativos

| Patrón | Ámbito | Resultado |
| --- | --- | --- |
| `eval`, `exec`, `shell_exec`, `unserialize` | `app/`, `resources/` | Sin coincidencias |
| `v-html`, `{!! !!}`, `innerHTML` | `resources/js/`, `resources/views/` | Sin coincidencias |
| `$request->all()`, `dd(`, `dump(`, `var_dump(` | `app/` | Sin coincidencias |
| `window.confirm`, `window.alert` | `resources/js/` | Sin coincidencias |
| `TODO`, `FIXME` | `app/`, `resources/` | Sin coincidencias |
| `localStorage` | `resources/js/` | Solo `theme.js` y `sfx.js` (preferencias) |
| `Auth::`, `auth:`, rutas `auth` | `app/`, `routes/` | Sin uso en el juego |
| `throttle` | `routes/`, `bootstrap/` | Sin coincidencias |
| `base64_decode` | `app/` | Solo `RevealSanctum.php:25` |
| `selectRaw`/`DB::raw` | `app/` | Solo dos consultas con SQL constante |
| `free_pull_at` | `app/`, `database/`, `tests/` | Solo la migración |
| `depth` | `app/` | `EnsurePlayer.php:52`, `Player.php:18,27` (nunca se lee) |
| `canResetAccount` | `resources/` | Solo `AppLayout.vue:173` |
| `@tailwindcss` / `@import "tailwindcss"` | `resources/css/app.css` | Sin coincidencias |

Las búsquedas negativas se usan como evidencia de **ausencia**; donde una ausencia no puede demostrarse de forma definitiva se acompaña de la limitación correspondiente.

## 6. Formato de hallazgo

Todos los hallazgos de las capas `02` a `06` siguen este contrato:

| Campo | Contenido esperado |
| --- | --- |
| `ID` | Código `AAA-NNN` estable (`SEC-001`, `BE-002`, …) |
| `Severidad` | Crítica / Alta / Media / Baja / Informativa |
| `Capa` | Capa técnica y subárea |
| `Referencia` | OWASP / ASVS / CWE / principios SOLID / 12-Factor |
| `Ubicación` | `archivo:línea` o rango |
| `Descripción` | Qué ocurre, en español |
| `Evidencia` | Fragmento real de código o salida, sin traducir |
| `Impacto` | Qué puede pasar |
| `Escenario` | Cómo se desencadena |
| `Recomendación` | Corrección concreta |
| `Ejemplo` | Código o comando de corrección, en inglés |
| `Esfuerzo` | Bajo / Medio / Alto con estimación horaria |
| `Estado de verificación` | Verificado / Parcial / `NO VERIFICADO` con explicación |

Los fragmentos de código y los comandos se reproducen en su idioma original (inglés); las explicaciones están en español.

## 7. Limitaciones y trabajo no realizado

Las filas siguientes describen el estado en el momento de la auditoría. Tras la Fase 0 varias quedaron resueltas localmente; véase la [sección 9](#9-actualización-tras-la-fase-0-2026-11-26).

| Limitación | Consecuencia en el informe |
| --- | --- |
| Sin `vendor/` | Artisan, Pint y PHPUnit no ejecutables; todos los resultados de tests se marcan `NO VERIFICADO` |
| Sin `node_modules/` | `npm run build` no ejecutable; `npm audit` solo evalúa el lockfile |
| Sin `.env` real | No se puede afirmar `APP_DEBUG`, `SESSION_SECURE_COOKIE` ni credenciales de producción |
| Sin conexión a MySQL | No se verificaron planes de consulta, índices reales, volumen ni migraciones aplicadas |
| Sin acceso a producción | No se revisaron proxies, TLS, logs, cabeceras reales ni monitorización |
| Sin pruebas de penetración | Las hipótesis de explotación son razonamientos desde el código, no exploits ejecutados |
| Sin telemetría | La probabilidad de cada riesgo es estimada, no medida |

## 8. Glosario

| Término | Significado |
| --- | --- |
| ADR | Architecture Decision Record; registro de una decisión de arquitectura |
| CSRF | Cross-Site Request Forgery; se mitiga con el token meta y `X-CSRF-TOKEN` |
| Inertia | Capa que devuelve props JSON a componentes Vue sin API REST separada |
| `lockForUpdate()` | Bloqueo de fila `SELECT ... FOR UPDATE` dentro de una transacción |
| Pity | Garantía de obtención de un objeto de rareza alta tras N tiradas |
| Puntos de Fe | Moneda del juego (`grace` en el modelo) |
| SRP | Single Responsibility Principle; una clase, una responsabilidad |
| `NO VERIFICADO` | Marcador usado cuando la evidencia disponible no permite afirmar el resultado |

## 9. Actualización tras la Fase 0 (2026-11-26)

La auditoría original fue de solo lectura. La Fase 0 del plan de remediación añadió evidencia nueva sobre el mismo árbol de trabajo, sin modificar código de producción.

| Comando | Salida relevante | Cambio respecto a la auditoría |
| --- | --- | --- |
| `composer install --no-interaction --prefer-dist` | 117 instalaciones, 0 actualizaciones, 0 eliminaciones | `vendor/` disponible; Artisan, Pint y PHPUnit ejecutables |
| `npm ci` | 78 paquetes añadidos; 0 vulnerabilidades | `node_modules/` disponible; build ejecutable |
| `php artisan --version` | `Laravel Framework 13.39.0` | Antes `NO VERIFICADO` |
| `php artisan about --json` | `environment: local`, `debug_mode: false`, `database: mysql` | Antes fallo por `vendor/autoload.php` |
| `php artisan about --only=environment` | `Debug Mode .. OFF` | Criterio 0.4 cubierto |
| `php artisan db:show` | MySQL 8.0.35, base `gacha_santos`, 17 tablas, usuario `sanctorum` | Antes sin conexión a MySQL |
| `npm ls --depth=0` | Una `UNMET OPTIONAL DEPENDENCY`: `@laravel/multiplex@^0.4.1` | Antes ocho dependencias no satisfechas |
| `php artisan test --compact` (sin clave) | 55 pruebas, 9 pasan, 46 errores: `No application encryption key has been specified.` | Limitación detectada al ejecutar la línea base |
| `php artisan test --compact` (con `.env`) | 55/55 pruebas, 1252 aserciones | Antes `NO VERIFICADO` |
| `vendor/bin/pint --dirty --format agent` | `passed` | Antes no ejecutable |
| `npm run build` | 608 módulos; `app-CLY8xQyB.css` 34.31 kB; `app-C6Lc6fNj.js` 269.62 kB | Antes no ejecutable |
| `composer audit --locked --format=json` | 15 advisories (1 low, 1 medium, 13 sin `severity`) | Resueltos después por la acción 1.1; véase la sección 10 |
| `npm audit --json` | 0 vulnerabilidades; 163 dependencias declaradas | Sin cambios |
| `git diff --check` | Sin salida | Sin cambios |

Cambios de archivo registrados en esta fase:

- `.env` creado con `APP_DEBUG=false`, clave de aplicación local y usuario MySQL `sanctorum`, distinto de `root`. Está ignorado por Git; no se reproducen credenciales en la documentación.
- `docs/arquitectura.md`: `ADR-001` registra la decisión de migrar a autenticación real para `SEC-001`, con fecha y responsable. La implementación sigue pendiente en la acción 2.5.
- `audit/08-plan-de-remediacion.md`: se actualizó el seguimiento y se añadió el resultado de la Fase 0.

Sigue sin verificarse: producción, proxies y TLS reales, pruebas de penetración, fuzzing, carga, `EXPLAIN` sobre datos reales y la existencia de workflows de CI. Los advisories de Composer ya están resueltos; véase la sección 10.

## 10. Actualización tras la acción 1.1 (2026-11-26)

| Comando | Salida relevante |
| --- | --- |
| `composer update laravel/framework:13.44.1 symfony/...:7.4.24 --with-all-dependencies --no-interaction` | 0 instalaciones, 35 actualizaciones, 0 eliminaciones; `No security vulnerability advisories found.` |
| `composer audit --locked --format=json` | `{"advisories":[],"abandoned":[],"filter":[]}` |
| `php artisan --version` | `Laravel Framework 13.44.1` |
| `php artisan test --compact` | 55/55 pruebas, 1252 aserciones |
| `vendor/bin/pint --dirty --format agent` | `passed` |
| `npm run build` | 608 módulos transformados; build correcto |
| `git diff --check` | Sin salida |

Versiones corregidas: `laravel/framework v13.44.1`; `symfony/http-foundation`, `http-kernel`, `mailer`, `mime`, `routing` e `yaml` en `v7.4.24`. No se añadieron ni quitaron paquetes y `composer.json` permaneció intacto; solo cambió `composer.lock`.
