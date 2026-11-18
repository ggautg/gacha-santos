# Arquitectura backend

## Flujo de una petición

1. `EnsurePlayer` resuelve o crea al jugador de la sesión y lo deja en `$request->attributes->get('player')`.
2. El controlador valida la entrada (cuando hace falta) y delega en un servicio o en `DungeonRules`.
3. El servicio devuelve un array de datos; el controlador responde JSON o renderiza una página Inertia.
4. `HandleInertiaRequests` comparte `player`, `flash` y `canResetAccount` con Vue.

No hay autenticación de usuarios: la identidad es el `Player` de la sesión.

## Capas

| Capa | Responsabilidad |
| --- | --- |
| `app/Http/Controllers` | Traducir HTTP a llamadas de dominio y elegir la respuesta |
| `app/Services/GachaService` | Probabilidades, garantía 5★, duplicados y experiencia |
| `app/Services/BattleService` | Orden de turnos, daño, curación, escudos y recompensas |
| `app/Support/DungeonRules` | Generación del mapa, resolución de salas y progreso |
| `app/Support/CardPresenter` | Formato de cartas y URLs de retratos |
| `app/Support/ValidationMessages` | Mensajes de validación en español |
| `app/Support/PixelArt` | Generación de retratos y sprites pixel-art |
| `app/Models` | Eloquent y relaciones |

## Integridad y economía

- Las resoluciones de cofre y combate son idempotentes: una sala ya resuelta no vuelve a entregar recompensas.
- `DungeonRules::run()` envuelve la generación y el avance en transacciones con `lockForUpdate` sobre el progreso.
- Los conflictos `Duplicate` al insertar salas se reintentan con un nuevo mapa.
- El orden de turnos se calcula una sola vez por unidad y ronda, con desempate aleatorio estable.
- `/batalla` no escribe `$player->team`: el equipo solo se edita en campamentos.

## Comandos propios

- `bible:import`: importa versículos desde un archivo origen.
- `sanctum`: comando oculto registrado en `bootstrap/app.php` e implementado en `app/Console/Commands/RevealSanctum.php`. Gracias al atributo `#[Hidden]` no aparece en `php artisan list`, aunque sí puede ejecutarse directamente. Guarda la oración en base64 dentro del código PHP y la decodifica solo al imprimirla en terminal; el texto no existe en `resources/`, por lo que la interfaz nunca lo muestra. `SanctumCommandTest` comprueba el contrato.

## Configuración

`config/sanctorum.php` concentra los costes y reglas ajustables. Los valores de entorno se leen solo desde archivos de configuración; el resto de la aplicación usa `config()`.

## Modelos documentados

Todos los modelos de `app/Models` llevan PHPDoc de entidad y de cada método propio. Esa documentación no altera el comportamiento: solo explica qué guarda cada tabla y para qué sirve cada relación o cálculo.

| Modelo | Representa | Métodos propios |
| --- | --- | --- |
| `Battle` | Batalla con resultado, recompensas y crónica | `player()` |
| `DungeonProgress` | Piso más profundo alcanzado por mazmorra (fila bloqueada al generar salas) | `player()` |
| `DungeonRun` | Partida activa: semilla, sala, equipo y vida | `player()` |
| `GospelVerse` | Versículo importado | `reference()` (p. ej. «Juan 3:16») |
| `Player` | Identidad de la sesión sin login | `booted()` asigna UUID, `copies()`, `pulls()`, `dungeonProgress()`, `dungeonRuns()`, `battles()`, `teamIds()` |
| `Pull` | Resultado de una oración | `saint()` |
| `Saint` | Catálogo, rarezas y atributos | `RARITIES`, `copies()`, `rarityLabel()`, `rarityColor()`, `basePower()`, `statsForLevel()` |
| `SaintCopy` | Ejemplar en la colección del jugador | `player()`, `saint()`, `xpForNextLevel()`, `stats()`, `power()` |
| `User` | Usuario autenticable reservado a tareas administrativas | `casts()`; `#[Hidden]` evita serializar `password` y `remember_token` |

## Controladores documentados

Cada controlador y cada una de sus acciones tienen PHPDoc; el texto explica la responsabilidad HTTP y deja la lógica de dominio en servicios y `Support`.

| Controlador | Acciones documentadas |
| --- | --- |
| `Controller` | Clase base común |
| `ArtController` | `saint()`, `battle()`, `foe()`, `mystery()` y `svg()` (caché inmutable y `nosniff`) |
| `CollectionController` | `index()` (cartas reveladas/veladas) y `levelUp()` (transacción con bloqueo de saldo) |
| `DevAccountController` | `reset()` (solo en local/testing) |
| `DungeonController` | `index()`, `show()`, `fight()`, `move()`, `step()`, `team()`, `restart()` y `validationError()` |
| `GachaController` | `index()` (costes, garantía y tasas) y `pull()` (invocación 1 o 10) |
| `SanctuaryController` | `index()` (resumen global del inicio) |
| `TeamController` | `index()` (plantilla y equipo) y `save()` (valida propiedad y unicidad) |

## Contrato de errores

Las acciones JSON devuelven `422` con `{ "message": string, "errors": object }` cuando falla una validación de dominio. `CollectionController::levelUp()` y `TeamController::save()` lanzan `ValidationException` con mensajes en español; `DungeonController` centraliza su conversión en `validationError()` y `GachaController` aplica el mismo formato en `pull()`. El frontend usa `ApiError.firstError()` y `reportError()` para mostrar siempre el mensaje útil, nunca un error técnico.

## Decisiones de esta revisión

- Los PHPDoc se añadieron sin modificar firmas ni cálculos, para que la documentación no pueda cambiar el resultado de los tests.
- La componentización Vue se limitó a código duplicado con un contrato claro (`BattleFighter`); la máquina de estados de `DungeonRun.vue` se conserva en la página porque dividirla exigiría prop drilling.
- No se añadieron dependencias de Composer ni de npm: las necesidades se cubrieron con Vue, Inertia, Three.js y la hoja de estilos propia ya instalados.
