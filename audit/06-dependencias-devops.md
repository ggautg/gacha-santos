# 06 — Dependencias y DevOps

## Índice

- [1. Resumen de la capa](#1-resumen-de-la-capa)
- [2. Versiones y lockfiles](#2-versiones-y-lockfiles)
- [3. Hallazgos](#3-hallazgos)
  - [DEVOPS-001 — Dependencias no instaladas: verificaciones bloqueadas](#devops-001--dependencias-no-instaladas-verificaciones-bloqueadas)
  - [DEVOPS-002 — Sin CI/CD ni definición de entorno](#devops-002--sin-cicd-ni-definición-de-entorno)
  - [DEVOPS-003 — Tailwind 4 instalado pero no utilizado](#devops-003--tailwind-4-instalado-pero-no-utilizado)
  - [DEVOPS-004 — `npm audit` evaluó el lockfile, no el árbol instalado](#devops-004--npm-audit-evaluó-el-lockfile-no-el-árbol-instalado)
  - [DEVOPS-005 — Sin compuerta única de verificación local](#devops-005--sin-compuerta-única-de-verificación-local)
- [4. Estado de los comandos de verificación](#4-estado-de-los-comandos-de-verificación)

## 1. Resumen de la capa

| ID | Severidad | Estado |
| --- | --- | --- |
| `DEVOPS-001` | Alta | Verificado (ausencia de carpetas) |
| `DEVOPS-002` | Alta | Verificado estáticamente |
| `DEVOPS-003` | Media | Verificado estáticamente |
| `DEVOPS-004` | Media | Verificado con `npm audit --json` y `npm ls --depth=0` |
| `DEVOPS-005` | Baja | Verificado estáticamente |

## 2. Versiones y lockfiles

| Herramienta | Versión detectada | Evidencia |
| --- | --- | --- |
| PHP | 8.3.36 | `php -v` |
| Composer | 2.10.2 | `composer --version` |
| Node.js | v26.13.0 | `node --version` |
| npm | 11.20.0 | `npm --version` |
| MySQL | 8.0.35, servicio `MySQL80` en ejecución | `Get-Service MySQL80` |
| PHPUnit | `^12.5.12` declarado | `composer.json:22` |
| Pint | `^1.27` declarado | `composer.json:19` |
| Vite | `^8.0.0` declarado | `package.json:15` |

Lockfiles presentes: `composer.lock` y `package-lock.json`. No hay `pnpm-lock.yaml` ni `yarn.lock`.

Auditorías ejecutadas:

```text
composer audit --locked --format=json  -> 15 advisories (detalle en 02-seguridad.md / SEC-003)
npm audit --json                      -> {"info":0,"low":0,"moderate":0,"high":0,"critical":0,"total":0}
npm audit metadata.dependencies       -> prod 34, dev 91, optional 89, total 163
```

> Actualización 2026-11-26: la acción 1.1 del plan llevó `composer audit --locked` a **0 advisories**. El valor de 15 se conserva aquí como línea base de la auditoría.

## 3. Hallazgos

### DEVOPS-001 — Dependencias no instaladas: verificaciones bloqueadas

| Campo | Detalle |
| --- | --- |
| **ID** | `DEVOPS-001` |
| **Severidad** | Alta |
| **Capa** | DevOps / Verificación |
| **Referencia** | 12-Factor II (dependencias declaradas y aisladas); CWE-1104 |
| **Ubicación** | `vendor/` (ausente), `node_modules/` (ausente), `artisan:10` |
| **Descripción** | El checkout contiene los lockfiles pero no las dependencias instaladas. En consecuencia, Artisan, Pint, PHPUnit y Vite no pueden ejecutarse: `php artisan about --json` falla al abrir `vendor/autoload.php`. |
| **Impacto** | No hay evidencia de que el código pase tests, formato ni build. Cualquier afirmación de "todo funciona" sería especulativa; por eso esos controles están marcados `NO VERIFICADO` en todo el informe. |
| **Escenario** | Un revisor ejecuta `php artisan test --compact` en este checkout y obtiene un error de autoload; interpreta que la aplicación está rota cuando el problema es solo la instalación. |
| **Recomendación** | En el entorno de auditoría/CI ejecutar `composer install --no-interaction --prefer-dist` y `npm ci`, y después las cuatro compuertas obligatorias: `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, `npm run build` y `git diff --check`. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (10-20 minutos) |
| **Estado de verificación** | Verificado: `Test-Path vendor` → `False`; `Test-Path node_modules` → `False`. No se instalaron dependencias para respetar el modo solo lectura. |

**Evidencia**:

```text
Test-Path node_modules -> False
Test-Path vendor       -> False

php artisan about --json --only=environment
Failed to open stream: No such file or directory ... vendor/autoload.php ... artisan on line 10
```

**Ejemplo de corrección**:

```bash
composer install --no-interaction --prefer-dist
npm ci
php artisan key:generate
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

---

### DEVOPS-002 — Sin CI/CD ni definición de entorno

| Campo | Detalle |
| --- | --- |
| **ID** | `DEVOPS-002` |
| **Severidad** | Alta |
| **Capa** | DevOps / Despliegue |
| **Referencia** | 12-Factor VI (procesos), XI (logs) y XII (tareas administrativas) |
| **Ubicación** | `.github/workflows/` (sin archivos), `Dockerfile` / `docker-compose.yml` / `Procfile` (ausentes) |
| **Descripción** | No hay pipeline automático ni manifiesto de contenedor. `README.md` y `AGENTS.md` describen el arranque manual con `composer run dev`. No hay constancia de compuertas automáticas antes de publicar. |
| **Impacto** | Los hallazgos `SEC-003` (dependencias vulnerables) y `DEVOPS-001` (sin ejecución de tests) pueden llegar a producción sin nadie que los detecte. Tampoco hay una receta reproducible para un entorno nuevo. |
| **Escenario** | Un desarrollador hace `push` con un error de sintaxis en Vue; nadie ejecuta `npm run build` y el despliegue publica assets rotos. |
| **Recomendación** | Crear un workflow de GitHub Actions con `composer install`, `npm ci`, `composer audit --locked`, `npm audit`, Pint, PHPUnit y build. Añadir `Dockerfile`/`docker-compose.yml` o, si se usa Laravel Cloud, un `cloud.yml` con los procesos web/worker y las tareas programadas. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Medio (1 día) |
| **Estado de verificación** | Verificado con listado de `.github/workflows` y búsqueda de manifiestos en la raíz. |

**Evidencia**:

```text
Get-ChildItem -File .github/workflows -> (sin salida)
Get-ChildItem -File | Where-Object { $_.Name -match 'Dockerfile|docker-compose|Procfile' } -> (sin salida)
```

**Ejemplo de corrección** (`.github/workflows/ci.yml`, fragmento):

```yaml
name: ci

on:
  push:
  pull_request:

jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-interaction --prefer-dist
      - run: composer audit --locked
      - run: vendor/bin/pint --test
      - run: php artisan test --compact
      - run: npm ci
      - run: npm audit
      - run: npm run build
```

---

### DEVOPS-003 — Tailwind 4 instalado pero no utilizado

| Campo | Detalle |
| --- | --- |
| **ID** | `DEVOPS-003` |
| **Severidad** | Media |
| **Capa** | Dependencias / Build |
| **Referencia** | CWE-1104 (Use of Unmaintained/Unnecessary Third-Party Components) — matiz de superficie |
| **Ubicación** | `package.json:10,14`; `vite.config.js:3,13`; `resources/css/app.css:1-4` |
| **Descripción** | El proyecto instala `@tailwindcss/vite` y `tailwindcss` y registra el plugin en Vite, pero la hoja CSS declara explícitamente "sin Tailwind" y no contiene directivas `@import "tailwindcss"` ni clases de utilidad. `AGENTS.md` confirma que no se usan clases Tailwind. |
| **Impacto** | Aumenta el árbol de dependencias, el tiempo de build y la superficie de futuros avisos de seguridad sin aportar funcionalidad. Además, contradice la documentación del proyecto. |
| **Escenario** | Un futuro `npm audit` reporta una vulnerabilidad en Tailwind y el equipo pierde tiempo evaluando una dependencia que no se usa. |
| **Recomendación** | Eliminar `tailwindcss` y `@tailwindcss/vite` de `package.json`, quitar el import y el plugin de `vite.config.js`, y regenerar `package-lock.json` con `npm install`. Si se decide usar Tailwind más adelante, documentar el cambio en `docs/frontend.md`. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (30 minutos) |
| **Estado de verificación** | Verificado por lectura de `package.json`, `vite.config.js` y búsqueda negativa de directivas Tailwind en `app.css`. |

**Evidencia**:

```js
// vite.config.js:3,13
import tailwindcss from '@tailwindcss/vite';
...
tailwindcss(),
```

```css
/* resources/css/app.css:1-4 */
/* Sanctorum — CSS mínimo (sin Tailwind, sin estilos heredados)
   Una sola hoja: reset, temas, primitivas y las tres áreas de la app. */
```

**Ejemplo de corrección**:

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        vue(),
    ],
});
```

---

### DEVOPS-004 — `npm audit` evaluó el lockfile, no el árbol instalado

| Campo | Detalle |
| --- | --- |
| **ID** | `DEVOPS-004` |
| **Severidad** | Media |
| **Capa** | Dependencias / Verificación |
| **Referencia** | CWE-1395; 12-Factor II |
| **Ubicación** | `package-lock.json`; `node_modules/` (ausente); salida de `npm ls --depth=0` |
| **Descripción** | `npm audit --json` devuelve 0 vulnerabilidades sobre 163 dependencias declaradas, pero `npm ls --depth=0` informa de `UNMET DEPENDENCY` para las ocho dependencias directas porque no hay `node_modules`. El resultado es válido como revisión del lockfile, no como verificación del software que se ejecutaría. |
| **Impacto** | Puede generar una falsa sensación de seguridad: una instalación futura con `npm install` (en vez de `npm ci`) podría resolver versiones distintas y el audit ya no sería el mismo. |
| **Escenario** | El pipeline dice "0 vulnerabilidades" porque auditó el lockfile; en producción se instala con `npm install --force` y se incluye una versión transitiva diferente. |
| **Recomendación** | Usar siempre `npm ci` en CI (respeta el lockfile) y ejecutar `npm audit` **después** de la instalación. Añadir `--ignore-scripts` solo si se confía en los scripts de instalación; documentar la decisión. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (30 minutos) |
| **Estado de verificación** | Verificado con ambas salidas. |

**Evidencia**:

```text
npm ls --depth=0
+-- UNMET DEPENDENCY @inertiajs/vue3@^3.9.1
+-- UNMET DEPENDENCY @tailwindcss/vite@^4.0.0
...
npm error code ELSPROBLEMS
```

```text
npm audit --json
{"info":0,"low":0,"moderate":0,"high":0,"critical":0,"total":0}
dependencies: prod 34, dev 91, optional 89, total 163
```

**Ejemplo de corrección**:

```bash
npm ci --ignore-scripts
npm audit --audit-level=high
```

---

### DEVOPS-005 — Sin compuerta única de verificación local

| Campo | Detalle |
| --- | --- |
| **ID** | `DEVOPS-005` |
| **Severidad** | Baja |
| **Capa** | DevOps / Herramientas |
| **Referencia** | `AGENTS.md`: "Comandos de verificación obligatorios antes de terminar" |
| **Ubicación** | `composer.json:36-52`; `package.json:5-8` |
| **Descripción** | `AGENTS.md` exige cuatro comandos, pero `composer test` solo ejecuta `config:clear` + `artisan test`, y `package.json` solo define `build` y `dev`. No existe un script que ejecute Pint, tests, build y `git diff --check` de una vez. |
| **Impacto** | La comprobación depende de que cada persona recuerde los cuatro comandos; es probable que se omita Pint o el diff check. |
| **Escenario** | Se mergea un cambio con un archivo sin formatear; `composer test` pasa y nadie ejecuta Pint hasta que CI (inexistente, `DEVOPS-002`) lo detecte… si existiera. |
| **Recomendación** | Añadir un script `composer verify` que encadene los cuatro comandos, o un `Makefile` con `make verify`. Usarlo también en CI para que local y remoto sean idénticos. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (30 minutos) |
| **Estado de verificación** | Verificado estáticamente en `composer.json` y `package.json`. |

**Evidencia**:

```json
// composer.json:49-52
"test": [
    "@php artisan config:clear --ansi @no_additional_args",
    "@php artisan test"
],
```

**Ejemplo de corrección**:

```json
"verify": [
    "@test",
    "@php vendor/bin/pint --dirty --format agent",
    "@php -r \"exit(shell_exec('npm run build') === false ? 1 : 0);\"",
    "@php -r \"exit(shell_exec('git diff --check') ? 1 : 0);\""
]
```

> Alternativa preferible: un `Makefile` con recetas explícitas, más legible que anidar `shell_exec()` en Composer.

## 4. Estado de los comandos de verificación

| Comando | Estado | SALIDA / motivo |
| --- | --- | --- |
| `php artisan about --json --only=environment` | Falló | `Failed to open stream ... vendor/autoload.php ... artisan on line 10` |
| `php artisan route:list --json --except-vendor` | Falló | Misma causa; el inventario de rutas se obtuvo leyendo `routes/web.php` |
| `composer audit --format=json` | Falló | `No installed packages found. Please run "composer install" ... or pass "--locked"` |
| `composer audit --locked --format=json` | Ejecutado | 15 advisories en la auditoría; 0 tras la acción 1.1 (2026-11-26) |
| `npm audit --json` | Ejecutado | 0 vulnerabilidades sobre el lockfile |
| `npm ls --depth=0` | Falló | `ELSPROBLEMS`, dependencias directas sin instalar |
| `php artisan test --compact` | `NO VERIFICADO` | Sin `vendor/` |
| `vendor/bin/pint --dirty --format agent` | `NO VERIFICADO` | Sin `vendor/` |
| `npm run build` | `NO VERIFICADO` | Sin `node_modules/` |
| `git diff --check` | No ejecutado | No hay cambios en código; solo se creó `audit/` |
