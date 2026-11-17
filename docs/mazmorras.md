# Mazmorras

## Recorrido

1. Desde `/mazmorras` se elige un descenso abierto.
2. Cada visita genera un mapa procedural con salas conectadas.
3. El jugador elige una sala alcanzada desde la sala actual.
4. Al resolver una sala se desbloquean sus conexiones y avanza el piso.
5. El jefe del piso abre la puerta al siguiente.

## Tipos de sala

| Tipo | Efecto |
| --- | --- |
| `battle` | Combate con recompensa de Fe y XP |
| `camp` | Descanso; único lugar donde se puede editar el equipo |
| `altar` | Curar al equipo o recibir bendición de ataque |
| `trap` | Cruzar con daño/robo de Fe o sortear pagando Fe |
| `treasure` | Cofre de Fe, solo una vez por sala |
| `gospel` | Versículo, Fe y bendición |
| `boss` | Combate de jefe y avance de piso |

## Reglas importantes

- La niebla oculta salas no descubiertas; solo se pueden elegir salas conectadas y alcanzables.
- Una sala resuelta no puede volver a entregar recompensas, aunque se repita la petición.
- Los Impactos de Fe y las bendiciones se acumulan solo durante el descenso.
- El equipo queda fijado al salir del campamento; en el diario aparece como «Equipo fijado».
- Reiniciar el descenso descarta el mapa actual, pero conserva récords y recompensas ya obtenidas.

## Vistas

- **3D**: escena Three.js con cámara orbital o en primera persona. Controles: `W/↑` avanzar, `S/↓` retroceder, `A/D` o `←/→` girar.
- **Mapa**: vista 2D con zoom, arrastre y marcadores por tipo de sala.

## Endpoints JSON

| Ruta | Uso |
| --- | --- |
| `POST /mazmorras/{dungeon}/entrar` | Crea o recupera el descenso |
| `POST /mazmorras/{dungeon}/mover` | Elige una sala conectada |
| `POST /mazmorras/{dungeon}/accion` | Resuelve altar, trampa, cofre o fragmento |
| `POST /mazmorras/{dungeon}/batalla` | Resuelve un combate de forma idempotente |
| `POST /mazmorras/{dungeon}/equipo` | Guarda el equipo solo en campamento |
| `POST /mazmorras/{dungeon}/reiniciar` | Reinicia el mapa actual |

Todos los errores de validación se devuelven en español.
