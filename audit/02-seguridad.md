# 02 — Seguridad

## Índice

- [1. Resumen de la capa](#1-resumen-de-la-capa)
- [2. Hallazgos](#2-hallazgos)
  - [SEC-001 — Identidad sin autenticación real](#sec-001--identidad-sin-autenticación-real)
  - [SEC-002 — Plantilla de entorno insegura](#sec-002--plantilla-de-entorno-insegura)
  - [SEC-003 — Avisos de seguridad en dependencias PHP](#sec-003--avisos-de-seguridad-en-dependencias-php)
  - [SEC-004 — Ruta de desarrollo siempre registrada](#sec-004--ruta-de-desarrollo-siempre-registrada)
  - [SEC-005 — Sin cabeceras de seguridad globales](#sec-005--sin-cabeceras-de-seguridad-globales)
  - [SEC-006 — Cookie de sesión sin `secure` forzado](#sec-006--cookie-de-sesión-sin-secure-forzado)
  - [SEC-007 — Ofuscación base64 innecesaria](#sec-007--ofuscación-base64-innecesaria)
  - [SEC-008 — Sin límite de frecuencia en rutas mutadoras](#sec-008--sin-límite-de-frecuencia-en-rutas-mutadoras)
  - [SEC-009 — Modelo `User` sin uso](#sec-009--modelo-user-sin-uso)
- [3. Controles que funcionan](#3-controles-que-funcionan)

## 1. Resumen de la capa

| ID | Severidad | CWE / marco | Estado |
| --- | --- | --- | --- |
| `SEC-001` | Crítica | CWE-306, CWE-639 · OWASP A01 · ASVS V2 | Verificado estáticamente |
| `SEC-002` | Alta | CWE-489, CWE-250 · OWASP A05 | Verificado estáticamente |
| `SEC-003` | Alta | CWE-1395 · OWASP A06 | Verificado con `composer audit --locked` |
| `SEC-004` | Media | CWE-489 · OWASP A01 | Verificado estáticamente |
| `SEC-005` | Media | CWE-1021, CWE-319 · OWASP A05 | Verificado estáticamente |
| `SEC-006` | Media | CWE-614 · ASVS V3.4 | Parcial (`NO VERIFICADO` en producción) |
| `SEC-007` | Baja | CWE-116 | Verificado estáticamente |
| `SEC-008` | Media | CWE-770 · OWASP A04 | Verificado estáticamente |
| `SEC-009` | Informativa | CWE-1162 | Verificado estáticamente |

No se encontraron inyecciones SQL, XSS, subida de archivos, ejecución de comandos ni secretos en props de Inertia. Esa ausencia se documenta como control positivo en la sección 3.

## 2. Hallazgos

### SEC-001 — Identidad sin autenticación real

| Campo | Detalle |
| --- | --- |
| **ID** | `SEC-001` |
| **Severidad** | Crítica |
| **Capa** | Seguridad / Control de acceso |
| **Referencia** | CWE-306 (Missing Authentication), CWE-639 (Authorization Bypass Through User-Controlled Key), OWASP A01:2021, ASVS V2.1 |
| **Ubicación** | `app/Http/Middleware/EnsurePlayer.php:25-35`; `routes/web.php:12-39`; `app/Models/Player.php:32-38` |
| **Descripción** | Cualquier visita sin credenciales obtiene un `Player` nuevo. La cookie `player_token` es la única credencial; no hay login, recuperación, rotación ni comprobación de propiedad más allá del middleware global. |
| **Impacto** | Pérdida total de la cuenta si se roba o comparte la cookie; imposibilidad de recuperar progreso al cambiar de dispositivo; creación ilimitada de cuentas; sin auditoría de quién ejecuta cada acción. |
| **Escenario** | Un atacante con la cookie de la víctima envía `POST /gacha` y `POST /equipo` con el token CSRF de la página: gasta los Puntos de Fe y cambia el equipo. El servidor no tiene forma de distinguir al legítimo propietario. |
| **Recomendación** | Si el juego debe seguir sin login, documentar el riesgo como aceptado y añadir: (a) token firmado/HMAC con rotación, (b) `SameSite=Strict`, (c) `Secure` en producción, (d) throttle por IP y (e) aviso claro de "cuenta local, no recuperable". Si se requiere propiedad real, integrar autenticación de Laravel (Breeze o Sanctum) y migrar `Player` a `user_id`. |
| **Ejemplo** | Ver bloque de corrección más abajo. |
| **Esfuerzo** | Alto (3-5 días si se añade autenticación; 1 día si solo se formaliza el riesgo aceptado) |
| **Estado de verificación** | Verificado por lectura estática. No se realizó robo de sesión en caliente porque no hay entorno ejecutable (`vendor/` ausente). |

**Evidencia** (`app/Http/Middleware/EnsurePlayer.php:25-35`):

```php
$token = $request->session()->get('player_token');

$player = $token
    ? Player::where('token', $token)->first()
    : null;

if (! $player) {
    $player = $this->createPlayer();
    $request->session()->put('player_token', $player->token);
    $request->session()->save();
}
```

**Ejemplo de corrección** (endurecimiento mínimo sin introducir login):

```php
// app/Http/Middleware/EnsurePlayer.php
$token = $request->session()->get('player_token');

$player = $token
    ? Player::where('token', $token)->first()
    : null;

if (! $player) {
    $player = $this->createPlayer();
    $request->session()->put('player_token', $player->token);
    $request->session()->save();
}

// New: bind the session identity to the browser fingerprint hash.
$fingerprint = hash('sha256', $request->userAgent().'|'.$request->ip());
$request->session()->put('player_fingerprint', $fingerprint);
```

> Nota: el *binding* por IP es solo disuasorio (cambia con redes móviles). La corrección completa pasa por autenticación.

---

### SEC-002 — Plantilla de entorno insegura

| Campo | Detalle |
| --- | --- |
| **ID** | `SEC-002` |
| **Severidad** | Alta |
| **Capa** | Configuración |
| **Referencia** | CWE-489 (Active Debug Code), CWE-250 (Execution with Unnecessary Privileges), OWASP A05:2021 |
| **Ubicación** | `.env.example:2-4`, `:20-26`; `config/app.php:42` |
| **Descripción** | La plantilla que se copia a `.env` activa `APP_DEBUG=true`, `APP_ENV=local`, `LOG_LEVEL=debug` y conecta MySQL como `root` sin contraseña. Si se usa tal cual en producción, Laravel muestra trazas y el proceso web tiene privilegios administrativos de BD. |
| **Impacto** | Fuga de rutas, variables de entorno y consultas en páginas de error; posibilidad de `DROP` accidental o malicioso desde la aplicación. |
| **Escenario** | Despliegue mediante `cp .env.example .env` y `composer run setup`; una excepción no controlada devuelve el stack trace con credenciales y la BD acepta cualquier sentencia como root. |
| **Recomendación** | Cambiar la plantilla a `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error` y un usuario de BD con permisos mínimos. Añadir una comprobación de despliegue que falle si `APP_DEBUG=true` con `APP_ENV=production`. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (1-2 horas) |
| **Estado de verificación** | Verificado estáticamente. No se comprobó el `.env` real porque no está en el checkout (sí está excluido en `.gitignore`). |

**Evidencia** (`.env.example:1-26`):

```dotenv
APP_NAME=Sanctorum
APP_ENV=local
APP_KEY=
APP_DEBUG=true
...
DB_USERNAME=root
# Escribe aquí la contraseña de MySQL de tu entorno local.
DB_PASSWORD=
```

```php
// config/app.php:42
'debug' => (bool) env('APP_DEBUG', false),
```

**Ejemplo de corrección**:

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
DB_USERNAME=sanctorum_app
DB_PASSWORD=
```

```bash
php artisan about --only=environment
```

---

### SEC-003 — Avisos de seguridad en dependencias PHP

| Campo | Detalle |
| --- | --- |
| **ID** | `SEC-003` |
| **Severidad** | Alta |
| **Capa** | Dependencias |
| **Referencia** | CWE-1395 (Dependency on Vulnerable Third-Party Component), OWASP A06:2021 |
| **Ubicación** | `composer.lock` (`laravel/framework` `v13.39.0`, `symfony/http-foundation` `v7.4.21`, `symfony/http-kernel` `v7.4.21`, `symfony/mailer` `v7.4.19`, `symfony/mime` `v7.4.19`, `symfony/routing` `v7.4.21`, `symfony/yaml` `v7.4.21`) |
| **Descripción** | `composer audit --locked` devuelve **15 avisos**. Uno es `low` (`CVE-2026-128083`, Laravel Task Output Injection), uno `medium` (`CVE-2026-45070`, inyección de cabeceras en `symfony/mime`) y los trece restantes no incluyen campo `severity` en la salida; todos afectan a las versiones bloqueadas. |
| **Impacto** | Superficie de ataque conocida en los componentes que gestionan peticiones, URL generadas, correo y parseo YAML. El riesgo real depende de exposición: la mayoría de las rutas afectadas no se usan en este juego, pero el framework sí procesa cada petición. |
| **Escenario** | Un proxy inverso que reenvíe `X-Forwarded-*: private_ranges` o un host controlado por el cliente puede aprovechar los avisos de `symfony/http-foundation` y `symfony/routing` para alterar URLs generadas o eludir reglas de acceso. |
| **Recomendación** | `composer update` para subir a versiones corregidas (`laravel/framework` ≥ 13.40.0; `symfony/*` ≥ 7.4.24 dentro de la rama 7.4) y añadir `composer audit --locked` como compuerta de CI. Revisar cambios de versión con `php artisan test`. |
| **Ejemplo** | Ver bloque inferior. |
| **Esfuerzo** | Medio (2-4 horas incluyendo regresión) |
| **Estado de verificación** | **Verificado** con `composer audit --locked --format=json`; no requirió `vendor/`. `NO VERIFICADO`: que las versiones corregidas sean compatibles con la aplicación (no se pudo ejecutar la suite). |

**Evidencia** (salida resumida de `composer audit --locked --format=json`):

```text
laravel/framework       low     CVE-2026-128083   >=13.0.0,<13.40.0|<12.71.0
symfony/http-foundation         CVE-2026-121619   >=7.4.0,<7.4.23|>=8.1.0,<8.1.11
symfony/http-kernel             CVE-2026-133743   >=7.4.0,<7.4.24|>=8.1.0,<8.1.12
symfony/mailer                  CVE-2026-133751   >=7.4.0,<7.4.24|>=8.1.0,<8.1.12
symfony/mime            medium  CVE-2026-45070    >=7.4.0,<7.4.22|>=8.1.0,<8.1.10
symfony/routing                 CVE-2026-133746   >=7.4.0,<7.4.24|>=8.1.0,<8.1.12
symfony/yaml                    CVE-2026-123792   >=7.4.0,<7.4.23|>=8.1.0,<8.1.11
```

**Ejemplo de corrección**:

```bash
composer update laravel/framework symfony/http-foundation symfony/http-kernel \
  symfony/mailer symfony/mime symfony/routing symfony/yaml --with-all-dependencies
composer audit --locked --format=json
php artisan test --compact
```

---

### SEC-004 — Ruta de desarrollo siempre registrada

| Campo | Detalle |
| --- | --- |
| **ID** | `SEC-004` |
| **Severidad** | Media |
| **Capa** | Seguridad / Configuración |
| **Referencia** | CWE-489 · OWASP A01:2021 |
| **Ubicación** | `routes/web.php:37-39`; `app/Http/Controllers/DevAccountController.php:21-33`; `app/Http/Middleware/HandleInertiaRequests.php:43` |
| **Descripción** | `POST /desarrollo/reiniciar-cuenta` existe en todos los entornos. El controlador la rechaza con 404 fuera de `local`/`testing`, que es una defensa correcta, pero la superficie sigue publicada en la tabla de rutas y depende de que `APP_ENV` sea correcto. |
| **Impacto** | Si `APP_ENV` se configura mal en producción, cualquier visitante con CSRF válido borra su cuenta; además, la ruta aparece en `route:list` y en scanners. |
| **Escenario** | Un despliegue con `APP_ENV=local` heredado de la plantilla (`SEC-002`) deja el botón visible y la acción activa en internet. |
| **Recomendación** | Registrar la ruta solo fuera de producción, además del `abort_unless` actual, y comprobar `app()->isProduction()` en el middleware que publica `canResetAccount`. Defensa en profundidad. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (1 hora) |
| **Estado de verificación** | Verificado estáticamente; `tests/Feature/DevAccountTest.php:34-46` confirma el 404 en producción. |

**Evidencia**:

```php
// routes/web.php:37-39
// La ruta existe siempre para que las rutas cacheadas no cambien de forma,
// pero el controlador la rechaza fuera de local/testing.
Route::post('/desarrollo/reiniciar-cuenta', [DevAccountController::class, 'reset'])->name('dev.account.reset');

// app/Http/Controllers/DevAccountController.php:23
abort_unless(app()->environment(['local', 'testing']), 404);
```

**Ejemplo de corrección**:

```php
// routes/web.php
if (! app()->isProduction()) {
    Route::post('/desarrollo/reiniciar-cuenta', [DevAccountController::class, 'reset'])
        ->name('dev.account.reset')
        ->middleware('throttle:5,1');
}
```

---

### SEC-005 — Sin cabeceras de seguridad globales

| Campo | Detalle |
| --- | --- |
| **ID** | `SEC-005` |
| **Severidad** | Media |
| **Capa** | Seguridad / HTTP |
| **Referencia** | CWE-1021 (Improper Restriction of Rendered UI Layers), CWE-319 · OWASP A05:2021 |
| **Ubicación** | `bootstrap/app.php:21-26` (sin middleware de cabeceras); `app/Http/Middleware/` (sin `AddSecurityHeaders`); `app/Http/Controllers/ArtController.php:43-49` (única excepción) |
| **Descripción** | No hay `Content-Security-Policy`, `Strict-Transport-Security`, `X-Frame-Options`, `Referrer-Policy` ni `Permissions-Policy` en las respuestas HTML. Solo los SVG añaden `X-Content-Type-Options: nosniff`. |
| **Impacto** | La aplicación puede incrustarse en un iframe (clickjacking), no fuerza HTTPS y cualquier script inyectado tendría menos barreras. El riesgo actual es mitigado porque no se renderiza HTML del usuario, pero es una defensa en profundidad ausente. |
| **Escenario** | Un sitio malicioso incrusta Sanctorum en un iframe invisible y captura clics en `POST /gacha` o en el botón de reinicio. |
| **Recomendación** | Añadir un middleware global que emita las cabeceras. Empezar con CSP en modo `Content-Security-Policy-Report-Only` para no romper Vite/Inertia, revisar reportes y después aplicar. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Medio (1 día incluyendo ajuste de CSP) |
| **Estado de verificación** | Verificado por búsqueda en `app/Http/Middleware` y `bootstrap/app.php`. |

**Evidencia**:

```php
// app/Http/Controllers/ArtController.php:43-49 — único lugar con cabeceras de seguridad
return response($svg, 200, [
    'Content-Type' => 'image/svg+xml; charset=utf-8',
    'Cache-Control' => 'public, max-age=31536000, immutable',
    'X-Content-Type-Options' => 'nosniff',
]);
```

**Ejemplo de corrección**:

```php
// app/Http/Middleware/AddSecurityHeaders.php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);

    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=()');
    $response->headers->set('X-Content-Type-Options', 'nosniff');

    if ($request->secure()) {
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    return $response;
}
```

---

### SEC-006 — Cookie de sesión sin `secure` forzado

| Campo | Detalle |
| --- | --- |
| **ID** | `SEC-006` |
| **Severidad** | Media |
| **Capa** | Seguridad / Sesión |
| **Referencia** | CWE-614 (Sensitive Cookie Without 'Secure' Attribute), ASVS V3.4 |
| **Ubicación** | `.env.example:28-32`; `config/session.php:50`, `:172`, `:185` |
| **Descripción** | `SESSION_SECURE_COOKIE` no aparece en la plantilla y `config/session.php:172` lo deja a `env(...)`, por lo que queda `null`. Tampoco se define `SESSION_SAME_SITE` ni se cifra la sesión (`SESSION_ENCRYPT=false`). |
| **Impacto** | Si el despliegue sirve HTTPS con un balanceador que termina TLS, la cookie puede enviarse por HTTP. `HttpOnly` sí está activo por defecto, lo que limita el robo por XSS. |
| **Escenario** | Un usuario accede por `http://` a una URL todavía válida del dominio; la cookie de sesión viaja en claro y un observador de red obtiene la identidad del jugador (`SEC-001`). |
| **Recomendación** | Definir `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax` (o `strict`) y `SESSION_HTTP_ONLY=true` en la configuración de producción; confiar en `X-Forwarded-Proto` solo desde proxies de confianza. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (1 hora) |
| **Estado de verificación** | Configuración verificada estáticamente. **NO VERIFICADO**: valor real en el entorno de producción (no hay `.env`). |

**Evidencia**:

```php
// config/session.php:172,185
'secure' => env('SESSION_SECURE_COOKIE'),
'http_only' => env('SESSION_HTTP_ONLY', true),
```

```dotenv
; .env.example:28-32 — no incluye SESSION_SECURE_COOKIE ni SESSION_SAME_SITE
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
```

**Ejemplo de corrección**:

```dotenv
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_HTTP_ONLY=true
SESSION_ENCRYPT=false
```

---

### SEC-007 — Ofuscación base64 innecesaria

| Campo | Detalle |
| --- | --- |
| **ID** | `SEC-007` |
| **Severidad** | Baja |
| **Capa** | Seguridad / Código oculto |
| **Referencia** | CWE-116 (Improper Encoding or Escaping of Output) — uso inapropiado, no explotable aquí |
| **Ubicación** | `app/Console/Commands/RevealSanctum.php:10-25` |
| **Descripción** | El comando oculto `sanctum` guarda el Padre Nuestro en base64 y lo decodifica al imprimirlo. Base64 no es confidencialidad: el contenido es recuperable con una lectura trivial. El atributo `#[Hidden]` oculta el comando del listado, no su contenido. |
| **Impacto** | Falsa sensación de secreto; si algún día se guarda contenido realmente sensible con el mismo patrón, quedaría expuesto en el repositorio. |
| **Escenario** | Un revisor ejecuta `base64 -d` sobre la constante y obtiene el texto sin ningún control de acceso. |
| **Recomendación** | Si el texto es un detalle de producto (no un secreto), almacenarlo en claro con un comentario; si debe ser sensible, moverlo a una variable de entorno o a un archivo fuera del control de versiones. Mantener `#[Hidden]` solo como UX. |
| **Ejemplo** | Sin código nuevo: sustituir la constante por texto plano o por `config('sanctum.prayer')`. |
| **Esfuerzo** | Bajo (15 minutos) |
| **Estado de verificación** | Verificado estáticamente y por `tests/Feature/SanctumCommandTest.php:12-24`. |

**Evidencia**:

```php
// app/Console/Commands/RevealSanctum.php:18,25
private const PRAYER = 'UGFkcmUgbnVlc3RybywgcXVlIGVzdMOhcyBlbiBlbCBjaWVsbywg...';
$this->line((string) base64_decode(self::PRAYER, true));
```

---

### SEC-008 — Sin límite de frecuencia en rutas mutadoras

| Campo | Detalle |
| --- | --- |
| **ID** | `SEC-008` |
| **Severidad** | Media |
| **Capa** | Seguridad / Disponibilidad |
| **Referencia** | CWE-770 (Allocation of Resources Without Limits), OWASP A04:2021 |
| **Ubicación** | `routes/web.php:12-39`; `bootstrap/app.php:21-26` (sin `throttle`); `app/Http/Middleware/EnsurePlayer.php:31-35` |
| **Descripción** | Ninguna ruta aplica `throttle`. Cada petición sin cookie válida crea un `Player`, tres `SaintCopy` y una sesión; cada `POST /gacha` ejecuta hasta 10 tiradas con escrituras. |
| **Impacto** | Un script puede inflar las tablas `players`, `saint_copies`, `pulls`, `battles` y `sessions`, llenar el disco de logs y degradar MySQL. |
| **Escenario** | Un bucle `for` que envíe `GET /` con cookies nuevas 1000 veces crea 1000 cuentas y 3000 copias en minutos. |
| **Recomendación** | Aplicar `throttle:60,1` al grupo `web` y un límite más estricto (`throttle:10,1`) a `/gacha`, `/mazmorras/*/batalla` y `/desarrollo/*`. Monitorizar creación de jugadores por IP. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (2-3 horas) |
| **Estado de verificación** | Verificado estáticamente (no hay `throttle` en rutas ni en el arranque). |

**Evidencia**:

```php
// routes/web.php:21 — sin middleware de frequency limit
Route::post('/gacha', [GachaController::class, 'pull'])->name('gacha.pull');
```

**Ejemplo de corrección**:

```php
Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('/gacha', [GachaController::class, 'pull'])->name('gacha.pull');
    Route::post('/mazmorras/{dungeon}/batalla', [DungeonController::class, 'fight'])->name('dungeons.fight');
});
```

---

### SEC-009 — Modelo `User` sin uso

| Campo | Detalle |
| --- | --- |
| **ID** | `SEC-009` |
| **Severidad** | Informativa |
| **Capa** | Seguridad / Superficie |
| **Referencia** | CWE-1162 (Unnecessary Code Complexity) |
| **Ubicación** | `app/Models/User.php:13-39`; `database/migrations/0001_01_01_000000_create_users_table.php:14-49` |
| **Descripción** | Existe un modelo `Authenticatable` con `password` y `remember_token`, además de tablas `users`, `password_reset_tokens` y `sessions`, pero ninguna ruta o controlador lo usa. |
| **Impacto** | Superficie muerta que puede confundir futuras revisiones de seguridad y añade tablas al esquema sin beneficio. |
| **Escenario** | Un auditor asume que hay login porque existe `User` y dedica tiempo a buscar middleware de autenticación inexistente. |
| **Recomendación** | Eliminar modelo, factory y migración de `users`/`password_reset_tokens` si no se prevé administración, o documentar explícitamente que se reservan para tareas administrativas. |
| **Ejemplo** | Sin código: documentar en `docs/arquitectura.md` o eliminar archivos con una migración reversa. |
| **Esfuerzo** | Bajo (1 hora) |
| **Estado de verificación** | Verificado estáticamente (búsqueda de `Auth::`, `user()` y rutas `auth` sin resultados en el código de la aplicación). |

## 3. Controles que funcionan

| Control | Evidencia |
| --- | --- |
| CSRF en todas las mutaciones | `resources/views/app.blade.php:6` publica el token; `resources/js/lib/api.js:19-35` lo envía como `X-CSRF-TOKEN` |
| Token de jugador nunca se serializa en Inertia | `app/Http/Middleware/HandleInertiaRequests.php:36-42` |
| Errores de validación uniformes y en español | `bootstrap/app.php:32-41`, `app/Support/ValidationMessages.php:13-29` |
| Gate de desarrollo evaluado por petición | `app/Http/Controllers/DevAccountController.php:23`, cubierto por `tests/Feature/DevAccountTest.php:34-46` |
| Verificación TLS y timeout en API externa | `app/Services/BibleImporter.php:40-43` |
| Sin peligros conocidos en búsquedas estáticas | No hay `eval`, `exec`, `shell_exec`, `unserialize`, `v-html`, `{!! !!}`, `$request->all()`, `dd()` ni subida de archivos |
| Secretos fuera del repositorio | `.env` no trackeado; `git log -S"password"` solo devuelve el commit inicial |
| SVG con `nosniff` | `app/Http/Controllers/ArtController.php:43-49` |
