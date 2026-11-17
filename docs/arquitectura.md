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
