# 03 — Backend

## Índice

- [1. Resumen de la capa](#1-resumen-de-la-capa)
- [2. Hallazgos](#2-hallazgos)
  - [BE-001 — Economía de colección dentro del controlador](#be-001--economía-de-colección-dentro-del-controlador)
  - [BE-002 — `ORDER BY RAND()` dentro de una transacción](#be-002--order-by-rand-dentro-de-una-transacción)
  - [BE-003 — Objetos de dominio sobredimensionados](#be-003--objetos-de-dominio-sobredimensionados)
  - [BE-004 — IDs no poseídos se descartan en silencio](#be-004--ids-no-poseídos-se-descartan-en-silencio)
  - [BE-005 — Mensajes de validación centralizados (informativo)](#be-005--mensajes-de-validación-centralizados-informativo)
- [3. Verificación de comportamiento observable](#3-verificación-de-comportamiento-observable)

## 1. Resumen de la capa

| ID | Severidad | Principio / marco | Estado |
| --- | --- | --- | --- |
| `BE-001` | Media | SRP, controladores delgados (convención del repositorio) | Verificado estáticamente |
| `BE-002` | Media | Rendimiento bajo concurrencia | Verificado estáticamente |
| `BE-003` | Baja | SRP, SOLID | Verificado estáticamente |
| `BE-004` | Baja | Predictibilidad de la API | Verificado estáticamente y por prueba |
| `BE-005` | Informativa | Convención de errores en español | Verificado estáticamente |

La capa HTTP hace bien lo esencial: valida antes de delegar, usa transacciones con bloqueo de fila para el dinero del juego y devuelve `{ message, errors }` en español. La deuda está en la distribución de responsabilidades y en dos consultas que no escalan.

## 2. Hallazgos

### BE-001 — Economía de colección dentro del controlador

| Campo | Detalle |
| --- | --- |
| **ID** | `BE-001` |
| **Severidad** | Media |
| **Capa** | Backend / HTTP |
| **Referencia** | SRP (SOLID); `AGENTS.md`: "Controladores delgados: validan y delegan en `app/Services`" |
| **Ubicación** | `app/Http/Controllers/CollectionController.php:78-125` |
| **Descripción** | `levelUp()` contiene la regla de negocio completa: comprobar propiedad, tope de nivel, calcular `cost = base * level`, verificar saldo, descontar y guardar. Otros flujos sí delegaron a servicios (`GachaService`, `BattleService`, `DungeonRules`), por lo que la inconsistencia es local. |
| **Impacto** | La economía tiene dos dueños: `config/sanctorum.php` y el controlador. Un cambio de regla (por ejemplo, coste progresivo no lineal) obliga a tocar HTTP y hace imposible reutilizar la operación desde un comando Artisan o un job. |
| **Escenario** | Se añade un endpoint administrativo para subir santos y copias el cálculo de coste; ambos divergen y uno regala niveles. |
| **Recomendación** | Crear `app/Services/CollectionService.php` con `levelUp(Player $player, Saint $saint): array`, mover la transacción y las reglas allí, y dejar el controlador en validación + llamada + respuesta. Cubrir con el mismo `CollectionTest` existente. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Medio (2-3 horas) |
| **Estado de verificación** | Verificado por lectura del controlador y comparación con `app/Services/GachaService.php`. |

**Evidencia** (`app/Http/Controllers/CollectionController.php:89-114`):

```php
return DB::transaction(function () use ($player, $saint) {
    $player = Player::whereKey($player->id)->lockForUpdate()->firstOrFail();

    $copy = SaintCopy::where('player_id', $player->id)
        ->where('saint_id', $saint->id)
        ->first();

    if (! $copy) {
        throw ValidationException::withMessages([
            'level' => 'Todavía no se te ha revelado este santo.',
        ]);
    }

    $cost = (int) config('sanctorum.level_cost_base') * $copy->level;
```

**Ejemplo de corrección**:

```php
// app/Http/Controllers/CollectionController.php
public function levelUp(Request $request, Saint $saint, CollectionService $service): JsonResponse
{
    /** @var Player $player */
    $player = $request->attributes->get('player');

    return response()->json($service->levelUp($player, $saint));
}
```

---

### BE-002 — `ORDER BY RAND()` dentro de una transacción

| Campo | Detalle |
| --- | --- |
| **ID** | `BE-002` |
| **Severidad** | Media |
| **Capa** | Backend / Rendimiento |
| **Referencia** | CWE-1049 (Excessive Data Query Operations) — patrón, no explotación directa |
| **Ubicación** | `app/Services/GachaService.php:24-26`, `:150-160` |
| **Descripción** | Cada tirada ejecuta `Saint::where('rarity', $attempt)->inRandomOrder()->first()`, que se traduce en `ORDER BY RAND() LIMIT 1`. La consulta ocurre **dentro** de `DB::transaction()` con `lockForUpdate()` sobre `players`, de modo que el bloqueo se mantiene mientras MySQL ordena aleatoriamente. |
| **Impacto** | Hoy hay un catálogo pequeño, pero el coste crece con el número de santos y con la concurrencia: diez tiradas por petición pueden ejecutar hasta diez `ORDER BY RAND()` con la fila del jugador bloqueada. |
| **Escenario** | Con 10 000 santos y 50 jugadores orando a la vez, las transacciones se serializan y aumenta la latencia de todas las mutaciones que bloquean `players`. |
| **Recomendación** | Cargar los IDs del catálogo por rareza una vez por petición (o cachearlos) y elegir el índice en PHP con `random_int()`. Alternativamente, usar `WHERE id >= RAND() * (SELECT MAX(id) ...)` con índice. Revisar con `EXPLAIN` en MySQL (pendiente de verificación en caliente). |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Medio (2-4 horas) |
| **Estado de verificación** | Verificado estáticamente. **NO VERIFICADO**: coste real y plan de ejecución (sin conexión a MySQL). |

**Evidencia**:

```php
// app/Services/GachaService.php:24-26
return DB::transaction(function () use ($player, $count, $config) {
    $player = Player::whereKey($player->id)->lockForUpdate()->firstOrFail();

// app/Services/GachaService.php:150-159
private function randomSaint(int $rarity): Saint
{
    for ($attempt = $rarity; $attempt >= 1; $attempt--) {
        $saint = Saint::where('rarity', $attempt)->inRandomOrder()->first();
```

**Ejemplo de corrección**:

```php
// Resolve the pool before opening the transaction.
$pool = Saint::query()
    ->select('id', 'rarity')
    ->get()
    ->groupBy('rarity');

$id = $pool->get($rarity)?->random()?->id
    ?? $pool->flatten()->random()->id;

$saint = Saint::findOrFail($id);
```

---

### BE-003 — Objetos de dominio sobredimensionados

| Campo | Detalle |
| --- | --- |
| **ID** | `BE-003` |
| **Severidad** | Baja |
| **Capa** | Backend / Diseño |
| **Referencia** | SRP (SOLID), CWE-1120 (Excessive Code Complexity) |
| **Ubicación** | `app/Support/DungeonRules.php:1-1460`; `app/Services/BattleService.php:1-669` |
| **Descripción** | `DungeonRules` reúne config de mazmorras, RNG determinista, generación de laberinto, estado de partida, validación de equipo, resolución de salas y recompensas. `BattleService` reúne simulación de combate, habilidades, recompensas, XP y persistencia. |
| **Impacto** | Cambiar el algoritmo del mapa obliga a abrir un archivo de 1460 líneas con transacciones y reglas económicas mezcladas; aumenta el riesgo de regresiones y dificulta las pruebas unitarias aisladas. |
| **Escenario** | Se quiere reutilizar el generador de laberintos en una mazmorra especial; no existe una clase `MapGenerator` independiente y hay que copiar código o acoplarse a toda la clase. |
| **Recomendación** | Extraer progresivamente `DungeonMapGenerator`, `DungeonRng`, `RoomResolver` y `DungeonRewardPolicy`, dejando `DungeonRules` como fachada. Hacer lo propio con `BattleSimulator` y `BattleRewardPolicy` en `BattleService`. Sin cambios de comportamiento: las pruebas actuales son la red de seguridad. |
| **Ejemplo** | Sin fragmento nuevo: mover `roomNode()`/`layoutFor()` a `DungeonMapGenerator` y mantener `DungeonRules::roomNode()` delegando. |
| **Esfuerzo** | Alto (2-4 días) |
| **Estado de verificación** | Verificado por recuento de líneas (`1460` y `669`) y por lectura de métodos en `DungeonRules.php:268,337,351,660`. |

**Evidencia** (recuento y métodos):

```text
app/Support/DungeonRules.php    Count=1460 Line=1251
app/Services/BattleService.php  Count=669  Line=575
```

```php
// app/Support/DungeonRules.php — responsabilidades distintas en la misma clase
public static function setTeam(Player $player, string $key, array $ids): DungeonRun   // :268
public static function currentNode(Player $player, string $key, ?DungeonRun $run = null): array // :337
public static function resolveStep(Player $player, string $key, string $action, ?array $team = null): array // :351
public static function moveTo(Player $player, string $key, int $room): DungeonRun    // :660
```

---

### BE-004 — IDs no poseídos se descartan en silencio

| Campo | Detalle |
| --- | --- |
| **ID** | `BE-004` |
| **Severidad** | Baja |
| **Capa** | Backend / Contrato de API |
| **Referencia** | CWE-345 (Insufficient Verification of Data Authenticity) — matiz de contrato, no de seguridad |
| **Ubicación** | `app/Http/Controllers/TeamController.php:55-79`; `tests/Feature/TeamTest.php:23-40` |
| **Descripción** | `save()` filtra los IDs que no pertenecen al jugador (`->filter(fn ($id) => in_array($id, $owned, true))`) y guarda el resto. Es seguro —no permite equipar santos ajenos—, pero el cliente recibe un equipo distinto al enviado sin indicación de error. En cambio, `DungeonRules::setTeam()` sí lanza `ValidationException` cuando falta un santo (`app/Support/DungeonRules.php:307-311`). |
| **Impacto** | Comportamiento inconsistente entre `/equipo` y `/mazmorras/{dungeon}/equipo`; un bug del frontend puede pasar desapercibido porque la respuesta es `200 OK`. |
| **Escenario** | El cliente envía `[1, 999999]` por un error de sincronización; el servidor guarda `[1]` y muestra "Equipo guardado", ocultando el dato inválido. |
| **Recomendación** | Decidir una política y aplicarla en ambos endpoints: rechazar con 422 y mensaje "Ese santo no forma parte de tu santoral." es más coherente con el resto del juego. Actualizar `TeamTest` si cambia el contrato. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (1-2 horas) |
| **Estado de verificación** | Verificado estáticamente y por `tests/Feature/TeamTest.php:23-40`, que confirma el filtrado actual. |

**Evidencia**:

```php
// app/Http/Controllers/TeamController.php:64-70
$team = collect($data['team'] ?? [])
    ->map(fn ($id) => (int) $id)
    ->filter(fn ($id) => in_array($id, $owned, true))
    ->unique()
    ->take(3)
    ->values()
    ->all();
```

**Ejemplo de corrección**:

```php
$ids = array_values(array_unique(array_map('intval', $data['team'] ?? [])));

if (array_diff($ids, $owned) !== []) {
    throw ValidationException::withMessages([
        'team' => 'Ese santo no forma parte de tu santoral.',
    ]);
}
```

---

### BE-005 — Mensajes de validación centralizados (informativo)

| Campo | Detalle |
| --- | --- |
| **ID** | `BE-005` |
| **Severidad** | Informativa |
| **Capa** | Backend / Contrato de API |
| **Referencia** | Convención del repositorio: errores de dominio → 422 con `{ message, errors }` en español |
| **Ubicación** | `app/Support/ValidationMessages.php:13-45`; `bootstrap/app.php:32-41`; `app/Http/Controllers/DungeonController.php:290-297` |
| **Descripción** | Se encontró un único mecanismo de mensajes para todos los controladores y un renderizador global de `ValidationException`. No se detectaron mensajes en inglés ni respuestas de error inconsistentes en los controladores revisados. |
| **Impacto** | Ninguno negativo; se documenta como referencia de buen diseño y para evitar duplicarlo al extraer servicios. |
| **Escenario** | Un nuevo endpoint reutiliza `ValidationMessages::rules()` y hereda automáticamente el formato. |
| **Recomendación** | Mantener el patrón al mover reglas a servicios: lanzar `ValidationException::withMessages()` con texto en español y dejar que el renderizador global produzca el 422. |
| **Ejemplo** | Sin código nuevo. |
| **Esfuerzo** | No aplica |
| **Estado de verificación** | Verificado estáticamente y por `tests/Feature/GachaTest.php:23-31`. |

**Evidencia**:

```php
// app/Support/ValidationMessages.php:15-28
return [
    'required' => 'Este campo es obligatorio.',
    'string' => 'Este campo debe ser texto.',
    'integer' => 'Este campo debe ser un número entero.',
    'array' => 'Este campo debe ser una lista.',
    'in' => 'El valor seleccionado no es válido.',
```

## 3. Verificación de comportamiento observable

La suite existente cubre 55 pruebas en 10 archivos, con `RefreshDatabase`. Las áreas con cobertura directa sobre la capa backend son:

| Área | Pruebas | Evidencia |
| --- | --- | --- |
| Gacha y economía | 8 | `tests/Feature/GachaTest.php:23-123` (coste, pity, duplicados, sin tirada gratuita) |
| Mazmorras | 24 | `tests/Feature/DungeonTest.php:28-600` (mapa conectado, salas, trampas, altares, cambio de equipo en campamento y récord) |
| Colección | 5 | `tests/Feature/CollectionTest.php:23-67` |
| Equipo | 4 | `tests/Feature/TeamTest.php:23-68` |
| Páginas y SVG | 5 | `tests/Feature/SanctuaryPageTest.php:23-111` |
| Gate de desarrollo | 3 | `tests/Feature/DevAccountTest.php:23-48` |
| Comando oculto | 2 | `tests/Feature/SanctumCommandTest.php:12-27` |
| Importación bíblica | 2 | `tests/Feature/BibleImportTest.php:16-41` |
| Unitaria de ejemplo | 1 | `tests/Unit/ExampleTest.php:12` |

**Importante:** la cobertura está escrita pero **`NO VERIFICADA` en ejecución** porque falta `vendor/`. No se puede afirmar que las 55 pruebas pasan.
