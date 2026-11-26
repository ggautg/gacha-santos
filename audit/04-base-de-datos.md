# 04 — Base de datos

## Índice

- [1. Resumen de la capa](#1-resumen-de-la-capa)
- [2. Esquema observado](#2-esquema-observado)
- [3. Hallazgos](#3-hallazgos)
  - [DB-001 — Columnas `depth` y `free_pull_at` sin uso](#db-001--columnas-depth-y-free_pull_at-sin-uso)
  - [DB-002 — Historial sin política de retención](#db-002--historial-sin-política-de-retención)
  - [DB-003 — Sesión, caché y colas sobre MySQL](#db-003--sesión-caché-y-colas-sobre-mysql)
  - [DB-004 — Índice insuficiente para historial por jugador y fecha](#db-004--índice-insuficiente-para-historial-por-jugador-y-fecha)
  - [DB-005 — Integridad referencial bien definida (informativo)](#db-005--integridad-referencial-bien-definida-informativo)
- [4. Limitaciones de la revisión de BD](#4-limitaciones-de-la-revisión-de-bd)

## 1. Resumen de la capa

| ID | Severidad | Estado |
| --- | --- | --- |
| `DB-001` | Media | Verificado estáticamente |
| `DB-002` | Media | Verificado estáticamente; volumen real `NO VERIFICADO` |
| `DB-003` | Media | Verificado estáticamente |
| `DB-004` | Baja | Verificado estáticamente; plan de ejecución `NO VERIFICADO` |
| `DB-005` | Informativa | Verificado estáticamente |

El esquema es coherente con el dominio: claves foráneas con `cascadeOnDelete`, únicos compuestos para evitar duplicados y tipos estrechos (`unsignedTinyInteger` para rareza y niveles). No se encontró SQL dinámico construido con entrada del usuario: todas las consultas pasan por Eloquent o por `DB::raw` con constantes.

## 2. Esquema observado

| Tabla | Columnas relevantes | Índices / únicos | Migración |
| --- | --- | --- | --- |
| `saints` | `slug`, `rarity`, `art` (JSON), atributos | `UNIQUE(slug)`, `INDEX(rarity, sort)` | `2026_10_27_000001_create_sanctorum_tables.php:11-37` |
| `players` | `token` UUID, `grace`, `pity`, `ten_pity`, `depth`, `free_pull_at`, `team` JSON | `UNIQUE(token)` | `2026_10_27_000001_create_sanctorum_tables.php:39-52` |
| `saint_copies` | `player_id`, `saint_id`, `copies`, `level`, `xp` | `UNIQUE(player_id, saint_id)`, FK con cascade | `2026_10_27_000001_create_sanctorum_tables.php:54-64` |
| `pulls` | `player_id`, `saint_id`, `rarity`, `is_new`, `batch` | `INDEX(player_id, created_at)`, FK con cascade | `2026_10_27_000001_create_sanctorum_tables.php:66-76` |
| `battles` | `player_id`, `stage`, `dungeon`, `enemy_key`, `log` JSON | `INDEX(player_id, dungeon)`, FK con cascade | `2026_10_27_000001_create_sanctorum_tables.php:78-88`; `2026_10_27_173510_add_dungeon_to_battles_table.php:11-15` |
| `dungeon_progress` | `player_id`, `dungeon`, `stage` | `UNIQUE(player_id, dungeon, stage)` | `2026_10_27_173509_create_dungeon_progress_table.php:11-19` |
| `dungeon_runs` | `player_id`, `dungeon`, `seed`, `floor`, `route`, `room`, `cleared`, `team`, `vitals`, `blessing` | `UNIQUE(player_id, dungeon)` | `2026_10_27_174808_create_dungeon_runs_table.php:11-20`; migraciones posteriores |
| `gospel_verses` | `translation`, `book`, `chapter`, `verse`, `text` | `UNIQUE(translation, book, chapter, verse)` | `2026_11_04_200459_create_gospel_verses_table.php:11-21` |
| `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Tablas estándar de Laravel | Índices propios del framework | `0001_01_01_0000*` |

## 3. Hallazgos

### DB-001 — Columnas `depth` y `free_pull_at` sin uso

| Campo | Detalle |
| --- | --- |
| **ID** | `DB-001` |
| **Severidad** | Media |
| **Capa** | Base de datos / Esquema |
| **Referencia** | CWE-1162 (Unnecessary Code Complexity) |
| **Ubicación** | `database/migrations/2026_10_27_000001_create_sanctorum_tables.php:46,49`; `app/Models/Player.php:18,27`; `app/Http/Middleware/EnsurePlayer.php:52`; `tests/Feature/GachaTest.php:88` |
| **Descripción** | `players.depth` solo se inicializa a 0 y se moldea como entero; nunca se lee. `players.free_pull_at` no aparece en ningún modelo, controlador ni servicio. Una prueba confirma explícitamente que ya no existe la tirada gratuita diaria. |
| **Impacto** | El esquema sugiere funcionalidad inexistente; nuevos desarrolladores pueden implementar lógica sobre columnas abandonadas. Ocupan espacio mínimo pero añaden ruido a `Player::$fillable`. |
| **Escenario** | Un desarrollador ve `free_pull_at` y habilita una tirada gratuita sin darse cuenta de que `GachaService` descuenta siempre el coste (`app/Services/GachaService.php:28-36`). |
| **Recomendación** | Añadir una migración que elimine ambas columnas, retirarlas de `$fillable`/`$casts` y actualizar `EnsurePlayer::createPlayer()` y `tests/TestCase.php:13-19` (que inicializa `depth`). |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (1 hora) |
| **Estado de verificación** | Verificado por búsqueda exhaustiva en `app/`, `database/` y `tests/`. |

**Evidencia**:

```php
// database/migrations/2026_10_27_000001_create_sanctorum_tables.php:46,49
$table->unsignedInteger('depth')->default(0);
$table->timestamp('free_pull_at')->nullable();
```

```php
// tests/Feature/GachaTest.php:88
public function test_there_is_no_free_daily_pull(): void
```

```text
Coincidencias de "depth" en app/: EnsurePlayer.php:52, Player.php:18, Player.php:27
Coincidencias de "free_pull_at" en app/: ninguna
```

**Ejemplo de corrección**:

```php
// database/migrations/2026_11_26_000000_drop_unused_player_columns.php
Schema::table('players', function (Blueprint $table): void {
    $table->dropColumn(['depth', 'free_pull_at']);
});
```

---

### DB-002 — Historial sin política de retención

| Campo | Detalle |
| --- | --- |
| **ID** | `DB-002` |
| **Severidad** | Media |
| **Capa** | Base de datos / Operaciones |
| **Referencia** | CWE-770 (Allocation of Resources Without Limits) |
| **Ubicación** | `database/migrations/2026_10_27_000001_create_sanctorum_tables.php:66-88`; `app/Services/GachaService.php:73-79`; `app/Services/BattleService.php:241-260`; `app/Http/Controllers/DungeonController.php:101-113` |
| **Descripción** | `pulls` y `battles` crean una fila por tirada y por combate sin borrado programado. `battles.log` guarda la crónica completa en JSON. La UI solo lee las últimas 8 batallas por mazmorra, pero el resto permanece indefinidamente. Tampoco hay comando de poda ni `schedule` en `routes/console.php`. |
| **Impacto** | Crecimiento monótono del almacenamiento y de los backups; las consultas de historial se vuelven más caras con el tiempo. En un juego sin autenticación (`SEC-001`), la creación de cuentas ilimitada amplifica el efecto (`SEC-008`). |
| **Escenario** | Tras un año de uso, una sola cuenta con 20 combates diarios acumula más de 7000 filas de `battles` con logs; el panel solo muestra 8 y nadie ha purgado el resto. |
| **Recomendación** | Definir retención (por ejemplo 500 batallas y 2000 tiradas por jugador) y programar un job semanal con `delete` por lotes. Alternativamente, agregar el historial y conservar solo un resumen. Añadir métricas de crecimiento. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Medio (1 día) |
| **Estado de verificación** | Patrón verificado estáticamente. **NO VERIFICADO**: volumen real y tamaño de filas (no se consultó MySQL). |

**Evidencia**:

```php
// app/Services/GachaService.php:73-79 — una fila por carta, sin borrado
Pull::create([
    'player_id' => $player->id,
    'saint_id' => $saint->id,
    'rarity' => $saint->rarity,
    'is_new' => $isNew,
    'batch' => $batch,
]);
```

```php
// app/Http/Controllers/DungeonController.php:101-105 — solo se leen 8
$battles = BattleRecord::where('player_id', $player->id)
    ->where('dungeon', $dungeon)
    ->latest('id')
    ->take(8)
    ->get();
```

**Ejemplo de corrección**:

```php
// app/Console/Commands/PruneBattleHistory.php (sketch)
$cutoff = now()->subDays(180);

Battle::where('created_at', '<', $cutoff)
    ->chunkById(500, fn ($rows) => $rows->each->delete());
```

---

### DB-003 — Sesión, caché y colas sobre MySQL

| Campo | Detalle |
| --- | --- |
| **ID** | `DB-003` |
| **Severidad** | Media |
| **Capa** | Base de datos / Infraestructura |
| **Referencia** | 12-Factor III (config) y VI (procesos); CWE-1049 |
| **Ubicación** | `.env.example:28`, `:36`, `:38`; `config/session.php:21`, `:89`; `config/queue.php:16`; `config/cache.php` |
| **Descripción** | `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database` y `CACHE_STORE=database` hacen que MySQL sostenga tres cargas distintas: sesiones, trabajos y caché. Es cómodo en local, pero en producción acopla disponibilidad y latencia de la web a la misma instancia. |
| **Impacto** | Picos de tráfico generan escrituras de sesión en cada petición (el middleware `EnsurePlayer` guarda la sesión al crear jugador) y compiten con las transacciones de economía. Si la BD se satura, se caen juntas la app, las colas y la caché. |
| **Escenario** | Un pico de visitas crea miles de sesiones en la tabla `sessions`; el `lockForUpdate()` de `GachaService` empieza a esperar y las respuestas de `/gacha` suben de latencia. |
| **Recomendación** | En producción usar Redis para caché, colas y sesiones, manteniendo MySQL como fuente de verdad del dominio. Ajustar `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis` y levantar un worker dedicado. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Medio (1 día de configuración + despliegue) |
| **Estado de verificación** | Configuración verificada estáticamente. **NO VERIFICADO**: comportamiento real bajo carga. |

**Evidencia**:

```dotenv
// .env.example:28,36,38
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

```php
// config/queue.php:16
'default' => env('QUEUE_CONNECTION', 'database'),
```

**Ejemplo de corrección**:

```dotenv
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
```

---

### DB-004 — Índice insuficiente para historial por jugador y fecha

| Campo | Detalle |
| --- | --- |
| **ID** | `DB-004` |
| **Severidad** | Baja |
| **Capa** | Base de datos / Índices |
| **Referencia** | CWE-1049 — patrón de acceso sin índice dedicado |
| **Ubicación** | `database/migrations/2026_10_27_000001_create_sanctorum_tables.php:78-88`; `2026_10_27_173510_add_dungeon_to_battles_table.php:14`; `app/Http/Controllers/DungeonController.php:101-105` |
| **Descripción** | `battles` tiene `INDEX(player_id, dungeon)`, pero la consulta de historial filtra por `player_id` + `dungeon` y ordena por `id` descendente. El índice existente no incluye `id`, por lo que MySQL debe ordenar el subconjunto. `pulls` sí tiene `INDEX(player_id, created_at)`. |
| **Impacto** | Con volúmenes altos, `latest('id')` sobre muchas batallas por jugador puede requerir un `filesort`. Hoy se mitigó con `take(8)` y con el índice parcial, por lo que el impacto es bajo. |
| **Escenario** | Un jugador con 50 000 batallas abre la mazmorra; la consulta lee todas las filas del índice `(player_id, dungeon)` y ordena antes de devolver 8. |
| **Recomendación** | Añadir `INDEX(player_id, dungeon, id)` (o `(player_id, id)` si se consulta sin mazmorra) y validar con `EXPLAIN`. Verificar en MySQL antes de aplicar en producción. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (1 hora + verificación) |
| **Estado de verificación** | Patrón verificado estáticamente. **NO VERIFICADO**: `EXPLAIN` y volumen real. |

**Evidencia**:

```php
// 2026_10_27_173510_add_dungeon_to_battles_table.php:12-15
Schema::table('battles', function (Blueprint $table) {
    $table->string('dungeon', 32)->nullable()->after('stage');
    $table->index(['player_id', 'dungeon']);
});
```

**Ejemplo de corrección**:

```php
$table->index(['player_id', 'dungeon', 'id'], 'battles_player_dungeon_id_idx');
```

---

### DB-005 — Integridad referencial bien definida (informativo)

| Campo | Detalle |
| --- | --- |
| **ID** | `DB-005` |
| **Severidad** | Informativa |
| **Capa** | Base de datos / Diseño |
| **Referencia** | Integridad referencial; convención del repositorio |
| **Ubicación** | `database/migrations/2026_10_27_000001_create_sanctorum_tables.php:41,56-63,68-75`; `2026_10_27_174808_create_dungeon_runs_table.php:13,19`; `2026_11_04_200459_create_gospel_verses_table.php:20` |
| **Descripción** | Todas las relaciones de dominio usan `foreignId()->constrained()->cascadeOnDelete()`. Los únicos compuestos evitan duplicados de copias, progreso, partida y versículos. El token es `uuid` con índice único. |
| **Impacto** | Ninguno negativo; reduce el riesgo de huérfanos y simplifica razonar sobre el estado. |
| **Escenario** | Al borrar una cuenta (`DevAccountController::reset`), se eliminan copias, tiradas y batallas en cascada sin código adicional. |
| **Recomendación** | Mantener el patrón en nuevas migraciones; documentar que `cascadeOnDelete` es intencional para el modo de cuenta local. |
| **Ejemplo** | Sin código nuevo. |
| **Esfuerzo** | No aplica |
| **Estado de verificación** | Verificado estáticamente en las migraciones. |

**Evidencia**:

```php
// database/migrations/2026_10_27_000001_create_sanctorum_tables.php:56-63
$table->foreignId('player_id')->constrained()->cascadeOnDelete();
$table->foreignId('saint_id')->constrained()->cascadeOnDelete();
...
$table->unique(['player_id', 'saint_id']);
```

## 4. Limitaciones de la revisión de BD

| Comprobación | Estado | Qué falta |
| --- | --- | --- |
| `php artisan migrate:status` | `NO VERIFICADO` | `vendor/` y `.env` |
| `SHOW INDEX` / `EXPLAIN` en MySQL | `NO VERIFICADO` | Conexión a la instancia real |
| Conteo de filas y tamaño de tablas | `NO VERIFICADO` | Acceso de solo lectura a la BD; no se realizó |
| Comprobación de que las migraciones son reversibles en MySQL | `NO VERIFICADO` | Entorno ejecutable |
| Revisión de datos sembrados (catálogo completo) | Parcial | Se leyó `SaintSeeder.php`; no se ejecutó el seeder |
