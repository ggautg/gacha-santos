# 01 — Arquitectura

## Índice

- [1. Visión general](#1-visión-general)
- [2. Stack confirmado](#2-stack-confirmado)
- [3. Flujo de una petición](#3-flujo-de-una-petición)
- [4. Inventario de rutas](#4-inventario-de-rutas)
- [5. Límites de confianza](#5-límites-de-confianza)
- [6. Modelo de dominio](#6-modelo-de-dominio)
- [7. Decisiones de diseño y su coste](#7-decisiones-de-diseño-y-su-coste)
- [8. Alineación con 12-Factor y SOLID](#8-alineación-con-12-factor-y-solid)
- [9. Hallazgos de arquitectura](#9-hallazgos-de-arquitectura)

## 1. Visión general

Sanctorum es un **monolito Inertia**: Laravel sirve HTML en la primera visita y JSON en las navegaciones y mutaciones; Vue 3 monta las páginas sobre el mismo árbol de rutas. No hay SPA separada ni API REST versionada.

```
Browser
  │  GET /, /orar, /coleccion, ...        (Inertia → Vue)
  │  POST /gacha, /equipo, /mazmorras/*  (fetch + CSRF → JSON)
  ▼
routes/web.php  ── middleware web ──▶ EnsurePlayer ──▶ HandleInertiaRequests
                                            │                │
                                            ▼                ▼
                                     Controllers ──▶ Services / Support ──▶ MySQL
                                                                   │
                                                                   └──▶ Bible API (helloao.org)
```

## 2. Stack confirmado

| Capa | Tecnología | Evidencia |
| --- | --- | --- |
| Lenguaje | PHP 8.3.36 | Salida de `php -v` (ver anexos) |
| Backend | Laravel `v13.39.0` bloqueado; `composer.json` exige `^13.17` | `composer.json:9-12`, `composer.lock` |
| Frontend | Vue `^3.5.44`, Inertia Vue `^3.9.1` | `package.json:20-23` |
| Build | Vite `^8.0.0`, plugin Vue `^6.0.10` | `package.json:9-15`, `vite.config.js:1-14` |
| Estilos | CSS propio en una sola hoja; Tailwind 4 instalado pero **no usado** | `resources/css/app.css:1-4`, `vite.config.js:3,13` |
| Datos | MySQL 8.0.35 (`MySQL80`, servicio activo) | Salida de `Get-Service MySQL80` |
| Pruebas | PHPUnit `^12.5.12`, SQLite en memoria | `composer.json:22`, `phpunit.xml` |
| Sesión / caché / colas | `database` | `.env.example:28,36,38` |

## 3. Flujo de una petición

1. `bootstrap/app.php:21-26` añade `EnsurePlayer` y `HandleInertiaRequests` al grupo `web`.
2. `EnsurePlayer::handle` lee `player_token` de la sesión; si no existe o no resuelve, crea un `Player` con sus copias iniciales y equipo de partida dentro de una transacción (`app/Http/Middleware/EnsurePlayer.php:25-35`, `:46-74`).
3. El jugador se expone en `$request->attributes` y en `View::share` (`:37-38`).
4. `HandleInertiaRequests::share` publica en Vue solo `name`, `grace`, `pity`, `wins` y `losses` (`app/Http/Middleware/HandleInertiaRequests.php:34-42`); el token **no** se serializa.
5. El controlador valida y delega en un servicio; las mutaciones devuelven JSON con `{ message, errors }` cuando fallan (`bootstrap/app.php:32-41`).

Ejemplo de delegación correcta:

```php
// app/Http/Controllers/GachaController.php:47-65
$data = $request->validate([
    'count' => ['nullable', 'integer', 'in:1,10'],
], ValidationMessages::rules(), ValidationMessages::attributes());

$result = $gacha->pull($player, (int) ($data['count'] ?? 1));

return response()->json($result);
```

## 4. Inventario de rutas

| Método | Ruta | Nombre | Middleware |
| --- | --- | --- | --- |
| GET | `/` | `sanctuary` | `web` + `EnsurePlayer` |
| GET | `/orar` | `gacha` | `web` + `EnsurePlayer` |
| GET | `/retratos/velado` | `art.mystery` | `web` + `EnsurePlayer` |
| GET | `/retratos/{saint:slug}` | `art.saint` | `web` + `EnsurePlayer` |
| GET | `/batalla/{saint:slug}` | `art.battle` | `web` + `EnsurePlayer` |
| GET | `/enemigos/{key}` | `art.foe` | `web` + `EnsurePlayer` |
| POST | `/gacha` | `gacha.pull` | `web` + CSRF |
| GET | `/coleccion` | `collection` | `web` |
| POST | `/santos/{saint:slug}/iluminar` | `saints.level` | `web` + CSRF |
| GET | `/equipo` | `team` | `web` |
| POST | `/equipo` | `team.save` | `web` + CSRF |
| GET | `/mazmorras` | `dungeons` | `web` |
| GET | `/mazmorras/{dungeon}` | `dungeons.show` | `web` |
| POST | `/mazmorras/{dungeon}/batalla` | `dungeons.fight` | `web` + CSRF |
| POST | `/mazmorras/{dungeon}/mover` | `dungeons.move` | `web` + CSRF |
| POST | `/mazmorras/{dungeon}/paso` | `dungeons.step` | `web` + CSRF |
| POST | `/mazmorras/{dungeon}/equipo` | `dungeons.team` | `web` + CSRF |
| POST | `/mazmorras/{dungeon}/reiniciar` | `dungeons.restart` | `web` + CSRF |
| POST | `/desarrollo/reiniciar-cuenta` | `dev.account.reset` | `web` + CSRF + gate por entorno |

Fuente: `routes/web.php:12-39`. No hay rutas `api/*`, routes de autenticación ni `auth` middleware.

## 5. Límites de confianza

| Frontera | Control existente | Control ausente |
| --- | --- | --- |
| Visitante → cuenta | UUID en sesión, `uuid` único en BD | Autenticación, recuperación, rotación de token (`SEC-001`) |
| Cliente → servidor | CSRF, `Accept: application/json`, `X-Requested-With` | `throttle` en rutas mutadoras (`SEC-008`) |
| Servidor → BD | Consultas Eloquent, `DB::transaction`, `lockForUpdate` | Verificación de plan de consultas en caliente (`NO VERIFICADO`) |
| Servidor → API bíblica | `timeout(20)`, `retry(2, 500)`, verificación TLS | Circuit breaker y caché de versículos |
| Producción → modo desarrollo | `abort_unless(app()->environment(...), 404)` | Registro condicional de la ruta (`SEC-004`) |

## 6. Modelo de dominio

| Modelo | Responsabilidad | Relaciones / notas |
| --- | --- | --- |
| `Player` | Identidad, economía, pity, récord y equipo | `token` UUID generado en `booted()` (`app/Models/Player.php:32-38`) |
| `Saint` | Catálogo con rareza, atributos y habilidad | `RARITIES` como constante (`app/Models/Saint.php:16-23`) |
| `SaintCopy` | Ejemplar por jugador; duplicados y nivel | Pertenecen a `Player` y `Saint` (`app/Models/SaintCopy.php:25-35`) |
| `Pull` | Historial de oraciones por lote | `batch` UUID, `is_new` booleano |
| `Battle` | Historial de combates y crónica | `log` serializado como array |
| `DungeonProgress` | Récord por mazmorra | Único `(player_id, dungeon, stage)` |
| `DungeonRun` | Partida activa por mazmorra | Único `(player_id, dungeon)`; guarda `seed`, `route`, `room`, `vitals` |
| `GospelVerse` | Versículos importados | Único `(translation, book, chapter, verse)` |
| `User` | `Authenticatable` de Laravel, sin uso en el juego | Atributos sensibles ocultos con `#[Hidden]` |

## 7. Decisiones de diseño y su coste

| Decisión | Beneficio | Coste / riesgo |
| --- | --- | --- |
| Identidad sin login | Cero fricción de entrada | No hay propiedad verificable ni recuperación (`SEC-001`) |
| Inertia en vez de API separada | Una sola autenticación CSRF y menos superficie | No hay contrato de API reutilizable ni versionado |
| Reglas en `app/Support/DungeonRules.php` (1460 líneas) | Lógica de juego centralizada | Un único objeto concentra generación, RNG, mapa y recompensas (`BE-003`) |
| Economía en configuración | Balance ajustable sin tocar código | Los valores viven en `config/sanctorum.php`, fuera de BD; cambiarlos no es auditable por jugador |
| CSS propio | No hay dependencia de framework de estilos | Tailwind sigue instalado en el build (`DEVOPS-003`) |
| Sesión en MySQL | Simplicidad en local | Todo el estado efímero comparte el cuello de botella de la BD (`DB-003`) |

## 8. Alineación con 12-Factor y SOLID

| Principio | Estado | Evidencia |
| --- | --- | --- |
| III. Configuración en entorno | Parcial | `config/sanctorum.php` no usa `env()`; es configuración de juego, aceptable. `.env.example` con valores inseguros (`SEC-002`) |
| VI. Procesos | Parcial | Sin `Procfile`, `Dockerfile` ni `docker-compose` (`DEVOPS-002`) |
| XI. Logs | Correcto en local | `LOG_CHANNEL=stack`, `LOG_LEVEL=debug` (`.env.example:15-18`); sin agregación en producción |
| XII. Admin/procesos one-off | Correcto | `bible:import` y `sanctum` como comandos Artisan |
| SRP | Parcial | `DungeonRules` y `BattleService` concentran varias responsabilidades (`BE-003`) |
| DIP | Parcial | Los servicios dependen de modelos concretos, no de interfaces |

## 9. Hallazgos de arquitectura

Los hallazgos de arquitectura se documentan en sus capas correspondientes para evitar duplicidad: `SEC-001`, `SEC-004`, `BE-003`, `DB-003` y `DEVOPS-002`. La matriz completa está en [07-matriz-de-riesgos.md](07-matriz-de-riesgos.md).
