# Pruebas

## Ejecución

```bash
php artisan test --compact
php artisan test --filter=DungeonTest
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

La base de datos de pruebas debe estar disponible antes de ejecutar la suite. El proyecto usa PHPUnit y la base de datos configurada en `.env.testing` o, en su defecto, la principal con el prefijo de pruebas correspondiente.

## Cobertura

| Archivo | Qué comprueba |
| --- | --- |
| `DungeonTest.php` | Generación, movimiento, niebla, cofres, combates, idempotencia, equipo en campamento y respuestas de jefe |
| `GachaTest.php` | Costes, garantía 5★, duplicados, experiencia y mensajes de validación en español |
| `CollectionTest.php` | Visibilidad de velados, filtros y subida de nivel |
| `TeamTest.php` | Guardado y validación del equipo |
| `SanctuaryPageTest.php` | Props de la página de inicio y progreso mostrado |
| `BibleImportTest.php` | Importación de versículos |
| `DevAccountTest.php` | Reinicio de cuenta de desarrollo |
| `SanctumCommandTest.php` | Comando oculto: salida y ausencia del texto en `resources/` |

Las pruebas se centran en comportamiento observable: respuestas HTTP, estado persistido y efectos económicos. No prueban detalles de implementación ni textos literales sin valor contractual.

La suite es exclusivamente PHP (PHPUnit): no hay runner de Vue. Los cambios de frontend se comprueban con `npm run build` y con una revisión manual en el navegador (consola sin errores, foco de diálogos, temas y movimiento reducido). Los PHPDoc añadidos a modelos y controladores son documentación pura, por lo que no requieren pruebas nuevas: la última suite verde registró 53 pruebas y 1237 aserciones.

## Añadir una prueba

1. Usa `php artisan make:test --phpunit NombreTest` para pruebas de feature.
2. Sigue el estilo de los archivos existentes: `RefreshDatabase`, factory del jugador y assertions de respuesta.
3. Ejecuta primero el archivo nuevo con `--filter` y después la suite completa.
4. Formatea con Pint antes de terminar.
