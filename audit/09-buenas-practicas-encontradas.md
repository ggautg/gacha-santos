# 09 — Buenas prácticas encontradas

## Índice

- [1. Propósito](#1-propósito)
- [2. Seguridad y configuración](#2-seguridad-y-configuración)
- [3. Backend y dominio](#3-backend-y-dominio)
- [4. Base de datos](#4-base-de-datos)
- [5. Frontend y accesibilidad](#5-frontend-y-accesibilidad)
- [6. Pruebas](#6-pruebas)
- [7. Documentación y convenciones](#7-documentación-y-convenciones)

## 1. Propósito

Una auditoría que solo lista defectos da una imagen incompleta. Esta capa recoge los controles que **ya funcionan** y que conviene preservar al aplicar el plan de remediación. Cada entrada cita evidencia verificable.

## 2. Seguridad y configuración

| Práctica | Por qué es valiosa | Evidencia |
| --- | --- | --- |
| CSRF en todas las mutaciones | El token se publica en el `<head>` y el cliente lo envía en cada `POST` | `resources/views/app.blade.php:6`; `resources/js/lib/api.js:19-35` |
| Token de jugador fuera de las props de Inertia | Reduce el riesgo de filtrado por serialización o logs de frontend | `app/Http/Middleware/HandleInertiaRequests.php:36-42` |
| Gate de desarrollo evaluado en cada petición | Funciona aunque las rutas estén cacheadas; además tiene prueba en producción | `app/Http/Controllers/DevAccountController.php:23`; `tests/Feature/DevAccountTest.php:34-46` |
| Errores de validación homogéneos | Un único renderizador devuelve 422 con `{ message, errors }` en español | `bootstrap/app.php:32-41` |
| Comando oculto y fuera de la interfaz | La oración no aparece en la UI ni en el listado de comandos | `app/Console/Commands/RevealSanctum.php:10-12`; `tests/Feature/SanctumCommandTest.php:27-32` (la ofuscación en sí se documenta como `SEC-007`) |
| Secretos fuera del control de versiones | `.env` está excluido y el historial no contiene credenciales | `.gitignore`; `git log --all -S"password" -S"secret" -S"api_key"` → solo commit inicial |
| Cliente HTTP externo con timeout, reintentos y TLS | Evita que una API lenta bloquee el comando de importación | `app/Services/BibleImporter.php:40-43` |
| SVG servidos con `nosniff` y caché inmutable | Evita interpretación de tipo y reduce transferencias | `app/Http/Controllers/ArtController.php:43-49` |

## 3. Backend y dominio

| Práctica | Por qué es valiosa | Evidencia |
| --- | --- | --- |
| Transacciones con `lockForUpdate()` en toda operación de economía | Evita doble gasto por peticiones concurrentes | `app/Services/GachaService.php:24-26`; `app/Http/Controllers/CollectionController.php:89-91`; `app/Services/BattleService.php:241-246`; `app/Support/DungeonRules.php:353-357` |
| Validación en servidor antes de delegar | El cliente nunca es fuente de verdad | `app/Http/Controllers/GachaController.php:52-54`; `DungeonController.php:177-179,218-222,249-252`; `TeamController.php:55-58` |
| Comprobación de propiedad de santos en equipo | Un ID manipulado no permite usar santos ajenos | `app/Http/Controllers/TeamController.php:60-70`; `app/Support/DungeonRules.php:301-311` |
| Imposibilidad de repetir recompensas de sala | La sala resuelta se marca como `cleared` dentro de la transacción | `app/Support/DungeonRules.php:361-368`; `tests/Feature/DungeonTest.php:209-232` |
| Mensajes de validación centralizados | Un solo lugar para mantener el español | `app/Support/ValidationMessages.php:13-45` |
| Reglas de juego en configuración | Balance ajustable sin desplegar código de dominio | `config/sanctorum.php:10-48`, `:61-446` |
| Inyección de dependencias en controladores | `GachaController::pull` recibe `GachaService`, `DungeonController::fight` recibe `BattleService` | `app/Http/Controllers/GachaController.php:47`; `DungeonController.php:147` |
| Importación idempotente de versículos | `upsert()` con clave única evita duplicados al repetir el comando | `app/Services/BibleImporter.php:72`; `tests/Feature/BibleImportTest.php:16-40` |

## 4. Base de datos

| Práctica | Por qué es valiosa | Evidencia |
| --- | --- | --- |
| Foreign keys con `cascadeOnDelete` | Al borrar una cuenta se limpia todo su historial sin código adicional | `2026_10_27_000001_create_sanctorum_tables.php:56-57,68-69,80` |
| Únicos compuestos en relaciones de dominio | Impiden copias duplicadas, progreso repetido y partidas dobles | `...000001...:63`; `...173509...:18`; `...174808...:19`; `...200459...:20` |
| `token` como UUID con índice único | Identidad opaca y no secuencial en la tabla `players` | `...000001...:41`; `app/Models/Player.php:32-38` |
| Tipos de columna estrechos | `unsignedTinyInteger` para rareza, pity, nivel y blessing reduce almacenamiento y expresa dominio | `...000001...:16,44-45,59`; `...110440...:15` |
| Índice para historial de tiradas | Consultas por jugador y fecha tienen soporte | `...000001...:75` |
| Migraciones incrementales con `down()` | Cada cambio de esquema es reversible | `2026_11_03_120440_add_route_to_dungeon_runs_table.php:12-27`; `2026_11_05_100000_add_room_map_to_dungeon_runs_table.php:9-22` |

## 5. Frontend y accesibilidad

| Práctica | Por qué es valiosa | Evidencia |
| --- | --- | --- |
| Un único cliente `fetch` con manejo de errores | Evita lógica de red duplicada y mensajes inconsistentes | `resources/js/lib/api.js:23-63` |
| Foco visible global | Cumple WCAG sin depender de que cada componente lo recuerde | `resources/css/app.css:202-205` |
| `role="alertdialog"`, `aria-modal` y foco atrapado | Las confirmaciones son utilizables con teclado y lector de pantalla | `resources/js/Components/ConfirmDialog.vue:18-33`; `resources/js/lib/dialog.js:35-89` |
| `aria-live` en avisos y estado de guardado | Anuncia resultados sin obligar a mirar otra zona | `resources/js/Components/ToastStack.vue:10-16`; `resources/js/Pages/Team.vue:227` |
| Respeto a `prefers-reduced-motion` en CSS y en JS | La animación de combate se salta automáticamente cuando el sistema lo pide | `resources/css/app.css:233-242`; `resources/js/lib/motion.js:1-4`; `resources/js/Pages/DungeonRun.vue:234` |
| Limpieza de temporizadores y tema temporal al desmontar | No quedan callbacks sobre componentes muertos ni paletas prestadas | `resources/js/Pages/DungeonRun.vue:844-849`; `resources/js/Pages/Team.vue:108-114`; `resources/js/lib/theme.js:126-143` |
| Salas del mapa como botones reales | Tab focus, `aria-label` y deshabilitado coherente con el estado | `resources/js/Components/DungeonMap.vue:152-174` |
| Sin HTML dinámico sin escapar | No hay `v-html`, `{!! !!}` ni `innerHTML` en el código revisado | Búsqueda negativa en `resources/js/` y `resources/views/` |
| `localStorage` con `try/catch` y solo preferencias | No rompe la app en modo privado y no guarda datos de cuenta | `resources/js/lib/sfx.js:7-15`; `resources/js/lib/theme.js:4-6` |
| Textos en español y formato numérico localizado | `Piso N`, `Récord N`, `Sortear`, `Intl.NumberFormat('es-ES')` | `resources/js/lib/player.js:31-33`; páginas y componentes revisados |

## 6. Pruebas

| Práctica | Por qué es valiosa | Evidencia |
| --- | --- | --- |
| `RefreshDatabase` en todas las pruebas de feature | Aislamiento entre casos y sin estado residual | `tests/Feature/GachaTest.php:14`; `DungeonTest.php:19`; `TeamTest.php:14`; `CollectionTest.php:14` |
| Factories y seeders reales | Los datos de prueba se parecen al catálogo real | `tests/TestCase.php:13-24`; `database/seeders/SaintSeeder.php` |
| Pruebas de comportamiento observable | Se comprueba economía, HTTP y estado, no detalles internos | `tests/Feature/GachaTest.php:33-121`; `tests/Feature/DungeonTest.php:157-600` |
| Casos negativos explícitos | Saldo insuficiente, santo no poseído, sala repetida, ruta en producción | `GachaTest.php:79-95`; `CollectionTest.php:46-65`; `DungeonTest.php:209-232`; `DevAccountTest.php:34-46` |
| Pruebas de accesibilidad del producto | Se verifica que la oración no se filtre en la interfaz | `tests/Feature/SanctumCommandTest.php:27-32` |
| Configuración de pruebas separada | SQLite en memoria, `APP_ENV=testing`, sesión en array | `phpunit.xml` |

> Recordatorio: la suite está **escrita**, pero su ejecución quedó `NO VERIFICADA` por la ausencia de `vendor/`.

## 7. Documentación y convenciones

| Práctica | Por qué es valiosa | Evidencia |
| --- | --- | --- |
| Guía de convenciones vigente y corta | Reduce debate en revisiones | `AGENTS.md` |
| Documentación del proyecto en español | Coherencia con la interfaz y los mensajes | `README.md`, `docs/*.md` |
| Métodos con PHPDoc que explican responsabilidad | Facilita mantener servicios y modelos | `app/Models/Player.php:9-14,32`; `app/Services/GachaService.php:16-18` |
| Enums y constantes con nombres claros | `Saint::RARITIES`, metodologías en `TitleCase` | `app/Models/Saint.php:16-23` |
| Un único stylesheet con variables CSS | Permite temas sin duplicar reglas | `resources/css/app.css:26-102`; `vite.config.js:9-10` |
| Catálogo de rutas nombradas | Facilita `route()` en servidor y evita literales | `routes/web.php:12-39` |

Estos controles deben conservarse al ejecutar [08-plan-de-remediacion.md](08-plan-de-remediacion.md).
