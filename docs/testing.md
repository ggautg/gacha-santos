# Pruebas

## Ejecución

```bash
php artisan test --compact
php artisan test --filter=DungeonTest
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

`phpunit.xml` fija `APP_ENV=testing`, `DB_CONNECTION=sqlite` y `DB_DATABASE=:memory:`. Cada prueba con `RefreshDatabase` crea una base SQLite en memoria: hace falta la extensión `pdo_sqlite`, pero no arrancar MySQL ni crear `.env.testing`. La base MySQL (`gacha_santos` en `.env`) se usa solo al ejecutar la aplicación.

## Cobertura

| Archivo | Qué comprueba |
| --- | --- |
| `DungeonTest.php` | Generación, movimiento, niebla, cofres, combates, idempotencia, equipo en campamento y respuestas de jefe |
| `GachaTest.php` | Costes, garantía 5★, duplicados, experiencia y mensajes de validación en español |
| `CollectionTest.php` | Visibilidad de velados, filtros y subida de nivel |
| `TeamTest.php` | Guardado y validación del equipo (propiedad, IDs positivos y mensajes en español) |
| `SanctuaryPageTest.php` | Props de la página de inicio y progreso mostrado |
| `BibleImportTest.php` | Importación de versículos |
| `DevAccountTest.php` | Reinicio de cuenta de desarrollo y rechazo con `404` en producción |
| `SanctumCommandTest.php` | Comando oculto: salida y ausencia del texto en `resources/` |

Las pruebas se centran en comportamiento observable: respuestas HTTP, estado persistido y efectos económicos. No prueban detalles de implementación; las cadenas que se comprueban son mensajes que ve el usuario y forman parte del contrato.

La suite es exclusivamente PHP (PHPUnit): no hay runner de Vue. Los cambios de frontend se comprueban con `npm run build` y con una revisión manual en el navegador (consola sin errores, foco de diálogos, temas y movimiento reducido). Los PHPDoc añadidos a modelos y controladores son documentación pura, por lo que no requieren pruebas nuevas. La última suite verde registró 55 pruebas y 1252 aserciones. Las comprobaciones añadidas en esta revisión cubren el rechazo de IDs no positivos y el bloqueo de la ruta de desarrollo fuera de `local`/`testing` (la petición incluye token CSRF porque el middleware se ejecuta antes que la restricción de entorno).

## Añadir una prueba

1. Usa `php artisan make:test --phpunit --no-interaction NombreTest` para pruebas de feature.
2. Sigue el estilo de los archivos existentes: `RefreshDatabase`, factory del jugador y assertions de respuesta.
3. Ejecuta primero el archivo nuevo con `--filter` y después la suite completa.
4. Formatea con Pint antes de terminar.
