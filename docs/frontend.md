# Frontend y componentes

## Entrada

`resources/js/app.js` monta Inertia, resuelve las páginas con `import.meta.glob` y aplica el tema antes de montar Vue para evitar el parpadeo de color.

`resources/views/app.blade.php` fija `lang="es"`, `data-theme="gloria"` y `data-font="clasica"` como valores iniciales.

## Componentes

| Componente | Uso |
| --- | --- |
| `AppLayout.vue` | Barra superior, navegación, perfil y avisos |
| `BattleFighter.vue` | Combatiente de la arena: barra de vida, escudo, aviso de golpe y estados de animación |
| `ConfirmDialog.vue` | Confirmaciones destructivas con el mismo foco y teclado que los modales |
| `PageHeader.vue` | Cabecera reutilizable de Inicio y Colección |
| `ProgressMeter.vue` | Barra de progreso con `role="progressbar"` |
| `ThemePicker.vue` | Selector de paleta y tipografía |
| `SaintCard.vue` | Carta de santo, con estados velado/bloqueado/seleccionado |
| `SaintDetail.vue` | Ficha modal con foco atrapado y cierre con Escape |
| `PullReveal.vue` | Revelación de oraciones con foco atrapado |
| `ToastStack.vue` | Avisos con cierre manual y `aria-live` |
| `DungeonScene.vue` | Escena Three.js del laberinto y controles en primera persona |

## Utilidades (`resources/js/lib`)

- `api.js`: `post()` con CSRF, `ApiError` con `firstError()` y `reportError()` para sonido y aviso consistentes.
- `player.js`: estado reactivo del jugador, sincronización y formato de números en `es-ES`.
- `theme.js`: 8 paletas, 4 tipografías, persistencia en `localStorage` y `useTemporaryTheme()` para el tema de mazmorra.
- `dialog.js`: `useDialogFocus()` comparte el foco de los modales y `useDismissable()` el cierre de menús emergentes.
- `dungeonNodes.js`: nombres, iconos y colores de los tipos de nodo compartidos por el mapa, la leyenda y la escena.
- `motion.js`: detecta `prefers-reduced-motion` para desactivar bamboleos y acelerar la crónica de combate.
- `sfx.js`: efectos de sonido y silencio global.
- `toast.js`: cola de avisos con límite de 4 elementos.

## Temas

Cada paleta define cuatro colores (`--accent`, `--accent-2`, `--surface`, `--bg`) y el resto se deriva con `color-mix()`. Mazmorras y Orar heredan la paleta activa; además, cada mazmorra aporta su propio acento mediante `--mz-accent`, usado en tarjetas, títulos y barras de progreso. Al entrar en un descenso se aplica el tema de la mazmorra; al salir se restaura la preferencia elegida por el jugador, aunque la haya cambiado mientras descansaba.

## Accesibilidad

- Foco visible en todos los controles interactivos.
- Modales con foco inicial, atrapamiento de `Tab`, cierre con `Escape` y restauración del foco.
- Slots de equipo activables con `Enter` y `Espacio`.
- Avisos con `aria-live="polite"` y `assertive` para errores.
- `prefers-reduced-motion` desactiva animaciones y transiciones CSS, los bamboleos de la escena 3D y las pausas de la crónica de combate.
- El mapa plano deja desplazarse la página con la rueda; el zoom con la rueda requiere `Ctrl` o `⌘`. La escena 3D solo captura las flechas cuando tiene el foco.

## Estilos

`resources/css/app.css` es una hoja única sin Tailwind: reset, temas, primitivas y estilos de las tres áreas (santuario, Orar y mazmorras). No hay archivos CSS adicionales. La clase `.sr-only` es propia y se usa para los textos accesibles. `@media (prefers-reduced-motion: reduce)` anula animaciones y transiciones en todo el documento.

## Contrato de las utilidades

| Función | Qué hace |
| --- | --- |
| `post(url, data)` | `fetch` con CSRF y JSON; devuelve el payload o lanza `ApiError`. |
| `ApiError.firstError()` | Devuelve el primer mensaje de validación del servidor, o el mensaje general. |
| `reportError(error)` | Reproduce el sonido de error y muestra el aviso con el mensaje en español. |
| `syncPlayer(player)` / `patchPlayer(patch)` | Actualizan el estado reactivo del jugador solo con las claves conocidas. |
| `usePlayer()` / `formatNumber(value)` | Exponen el estado compartido y formatean cifras con `es-ES`. |
| `applyTheme(key)` / `applyFont(key)` | Cambian paleta o tipografía y sincronizan `theme-color`; guardan la preferencia salvo que se indique `persist: false`. |
| `useTemporaryTheme(key)` | Aplica el tema de una mazmorra y devuelve una función que restaura la preferencia real del jugador, aunque haya cambiado mientras tanto. |
| `initTheme()` | Aplica tema y tipografía guardados antes de montar Vue para evitar el parpadeo. |
| `useDialogFocus(dialog, closeButton, onClose)` | Foco inicial, atrapamiento de `Tab`, `Escape` y restauración del foco al cerrar. |
| `useDismissable(isOpen, container)` | Cierra menús emergentes con clic fuera o `Escape`, devolviendo el foco al botón. |
| `nodeKinds()`, `nodeMeta()`, `nodeLabel()`, `nodeGlyph()`, `nodeStatus()`, `nodeHint()` | Metadatos únicos de los tipos de sala para mapa, leyenda, ficha y escena 3D. |
| `prefersReducedMotion()` | Consulta la preferencia del sistema al cargar la vista. |
| `play(name)` / `toggleMuted()` | Efectos de sonido y silencio global. |
| `pushToast()` / `toast.success()` / `toast.error()` / `toast.info()` | Cola de avisos con límite de 4; los errores duran más que el resto. |
| `BattleFighter` | Recibe el combatiente, su vida/escudo y el estado de animación; calcula porcentajes y clases de la barra. |

## Cambios de esta revisión y por qué

- **Coherencia visual**: `PageHeader` unifica las cabeceras, `.hero` tiene estilos propios y los botones de decisión usan `ConfirmDialog` en lugar de `window.confirm`.
- **Movimiento cómodo**: `prefers-reduced-motion` se respeta en CSS, en la escena WebGL y en la crónica; la rueda del mapa ya no bloquea el scroll (solo hace zoom con `Ctrl`/`⌘`) y las flechas 3D solo actúan con el foco en la escena.
- **Español**: `Piso N`, `Récord N`, plurales, `Sortear`, `Puntos de Fe`, `Poder`, `Milagroso` y concordancias revisadas en textos, ayudas y mensajes de validación.
- **Temas**: ocho paletas con `color-mix()` para los tonos derivados, acento por mazmorra en `--mz-accent` y restauración de la preferencia al salir.
- **Formularios y cargas**: estados ocupados deshabilitan los botones, los errores de API se muestran con `reportError()`, `Suspense` muestra «Preparando la escena…» y las narraciones no encadenan diálogos nativos.
- **Componentización**: utilidades compartidas (`reportError`, `useDismissable`, `nodeKinds`, `motion`), `ConfirmDialog`, `PageHeader` y `BattleFighter`. La lógica que queda en `DungeonRun.vue` es la máquina de estados de la partida: extraerla exigiría prop drilling y haría el código menos legible, así que se conserva en la página.
- **Sin dependencias nuevas**: no se instalaron paquetes de npm ni de Composer; se reutilizan Vue, Inertia y Three.js ya presentes.
