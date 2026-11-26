# Arquitectura backend

## Flujo de una petición

1. `EnsurePlayer` resuelve o crea al jugador de la sesión y lo deja en `$request->attributes->get('player')`.
2. El controlador valida la entrada (cuando hace falta) y delega en un servicio o en `DungeonRules`.
3. El servicio devuelve un array de datos; el controlador responde JSON o renderiza una página Inertia.
4. `HandleInertiaRequests` comparte `player`, `flash` y `canResetAccount` con Vue.

Actualmente no hay autenticación de usuarios: la identidad es el `Player` de la sesión. El estado objetivo es autenticación real, según [ADR-001](#adr-001--identidad-y-autenticación-2026-11-26).

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
- Los conflictos de clave única al insertar salas se detectan por SQLSTATE y mensaje, de modo que funcionan igual en MySQL y en SQLite, y se reintentan con un nuevo mapa.
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
| `Pull` | Resultado de una oración | `saint()`, `player()` |
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
| `TeamController` | `index()` (plantilla y equipo) y `save()` (valida propiedad, IDs positivos y deduplica) |

## Contrato de errores

Las acciones JSON devuelven `422` con `{ "message": string, "errors": object }` cuando falla una validación de dominio. `CollectionController::levelUp()` y `TeamController::save()` lanzan `ValidationException` con mensajes en español; `DungeonController` centraliza su conversión en `validationError()` y `GachaController` aplica el mismo formato en `pull()`. El frontend usa `ApiError.firstError()` y `reportError()` para mostrar siempre el mensaje útil, nunca un error técnico.

## Decisiones de esta revisión

- Los PHPDoc se añadieron sin modificar firmas ni cálculos, para que la documentación no pueda cambiar el resultado de los tests.
- La componentización Vue se limitó a código duplicado con un contrato claro (`BattleFighter`); la máquina de estados de `DungeonRun.vue` se conserva en la página porque dividirla exigiría prop drilling.
- No se añadieron dependencias de Composer ni de npm: las necesidades se cubrieron con Vue, Inertia y la hoja de estilos propia ya instalados. En la revisión final se retiró `three` porque el descenso pasó a ser solo 2D.

## Revisión de noviembre de 2026

Cambios aplicados en esta pasada, además de la documentación previa:

- **Concurrencia real**: `restart()`, `setTeam()` y `moveTo()` bloquean la fila de `DungeonRun` con `lockForUpdate()` dentro de una transacción. Antes leían la partida, validaban y escribían fuera de ella; dos peticiones simultáneas podían decidir con datos obsoletos. `run()` mantiene su bloqueo sobre `DungeonProgress`.
- **Detección de duplicados independiente del motor**: `DungeonRules::isUniqueViolation()` sustituye la búsqueda literal de `Duplicate` en el mensaje de excepción. Comprueba SQLSTATE (`23000`, `23505`) y mensajes en inglés o español.
- **Equipo y IDs**: `TeamController` y las acciones JSON de `DungeonController` rechazan IDs menores que 1 con mensaje en español. La deduplicación sigue haciéndose en el controlador (no con `distinct`), porque la interfaz envía la selección completa y el comportamiento amable es deducir, no fallar.
- **Mensajes de validación**: los mensajes de tamaño de `ValidationMessages` están agrupados por tipo (`min => ['numeric' => …, 'array' => …]`). Con claves planas del tipo `min.numeric`, Laravel devolvía la clave de traducción literal en lugar del texto.
- **Ruta de desarrollo**: `/desarrollo/reiniciar-cuenta` está siempre registrada para que cachear rutas no cambie la forma de la tabla, pero `DevAccountController` rechaza la acción con `404` fuera de `local`/`testing`. `canResetAccount` evalúa el entorno en cada petición, no al registrar la ruta.
- **Estabilidad entre motores**: `BibleImporter` devuelve el número de versículos procesados, no el recuento de filas afectadas de `upsert()` (MySQL cuenta de forma distinta según inserte o actualice).
- **Relación y PHPDoc**: `Pull::player()` expone el autor de la oración; se documentaron `EnsurePlayer`, `HandleInertiaRequests`, `AppServiceProvider`, `BibleImporter`, `BibleImport`, `RevealSanctum` y los pobladores, además de los modelos y controladores ya cubiertos.
- **Instalación**: `composer setup` ejecuta `migrate --force --seed` y `post-create-project-cmd` ya no crea una base SQLite: la configuración de referencia es MySQL, con `APP_LOCALE=es` en `.env.example`.

## Deuda conocida y decidida

- El bucle que consulta progreso por mazmorra en `SanctuaryController` y `DungeonController` hace pocas consultas (una mazmorra visible por iteración); no se ha sustituido por agregados porque el número es pequeño y las reglas viven en `DungeonRules`.
- La clave `pity` de `config/sanctorum.php` y del contrato JSON se conserva: renombrarla rompería el cliente y la etiqueta visible ya dice «Garantía 5★».
- La lógica de `BattleService` que simulaba daño con datos leídos antes de los bloqueos se dejó como está para no alterar el equilibrio; el commit final sí se hace con el jugador bloqueado.

## ADR-001 — Identidad y autenticación (2026-11-26)

- **Estado**: Aceptada; implementación prevista en la Fase 2 del plan de remediación (`audit/08-plan-de-remediacion.md`, acción 2.5).
- **Responsable**: Producto + Arquitectura; decisión registrada por el usuario el 2026-11-26.
- **Contexto**: el hallazgo `SEC-001` describe que la identidad depende hoy de una cookie de sesión sin autenticación. Cualquier persona que obtenga o reutilice esa cookie controla el `Player`, y el progreso no puede reclamarse desde otro dispositivo.
- **Decisión**: migrar a autenticación real (registro e inicio de sesión) y vincular cada `Player` a una cuenta de usuario. Hasta que exista, la sesión actual se conserva como mecanismo transitorio con controles compensatorios: cookie `HttpOnly`, `SameSite=Lax` y `Secure` en producción; ninguna acción privilegiada dependerá solo de la cookie; aviso visible de que limpiar cookies pierde el progreso.
- **Consecuencias**: harán falta migraciones, middleware de autenticación, cambios en `EnsurePlayer`, `HandleInertiaRequests` y en la interfaz de acceso, además de pruebas de propiedad de cuenta. La migración de partidas anónimas existentes se diseñará antes de borrar el mecanismo actual.
- **Alternativa rechazada**: mantener la cuenta local sin login. Reduce el trabajo inmediato, pero deja el riesgo `SEC-001` abierto y contradice la decisión de producto registrada aquí.
