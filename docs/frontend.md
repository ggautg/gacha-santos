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
| `DungeonMap.vue` | Mapa 2D del laberinto: salas, pasillos, equipo, zoom y arrastre |

## Utilidades (`resources/js/lib`)

- `api.js`: `post()` con CSRF, `ApiError` con `firstError()` y `reportError()` para sonido y aviso consistentes.
- `player.js`: estado reactivo del jugador, sincronización y formato de números en `es-ES`.
- `theme.js`: 8 paletas, 4 tipografías, persistencia en `localStorage` y `useTemporaryTheme()` para el tema de mazmorra.
- `dialog.js`: `useDialogFocus()` comparte el foco de los modales y `useDismissable()` el cierre de menús emergentes.
- `dungeonNodes.js`: nombres y glifos de los tipos de nodo compartidos por el mapa y la leyenda; los colores viven en `--node-*`.
- `motion.js`: detecta `prefers-reduced-motion` para desactivar el caminar del equipo y acelerar la crónica de combate.
- `sfx.js`: efectos de sonido y silencio global.
- `toast.js`: cola de avisos con límite de 4 elementos.

## Temas

Cada paleta define cuatro colores (`--accent`, `--accent-2`, `--surface`, `--bg`) y el resto se deriva con `color-mix()`. Mazmorras y Orar heredan la paleta activa; además, cada mazmorra aporta su propio acento mediante `--mz-accent`, usado en tarjetas, títulos y barras de progreso. Al entrar en un descenso se aplica el tema de la mazmorra; al salir se restaura la preferencia elegida por el jugador, aunque la haya cambiado mientras descansaba.

## Accesibilidad

- Foco visible en todos los controles interactivos.
- Modales con foco inicial, atrapamiento de `Tab`, cierre con `Escape` y restauración del foco.
- Slots de equipo activables con `Enter` y `Espacio`.
- Avisos con `aria-live="polite"` y `assertive` para errores.
- `prefers-reduced-motion` desactiva animaciones y transiciones CSS, el caminar del equipo en el mapa y las pausas de la crónica de combate.
- El mapa deja desplazarse la página con la rueda; el zoom con la rueda requiere `Ctrl` o `⌘`.

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
| `nodeKinds()`, `nodeMeta()`, `nodeLabel()`, `nodeGlyph()`, `nodeStatus()`, `nodeHint()` | Metadatos únicos de los tipos de sala para mapa, leyenda y ficha. |
| `prefersReducedMotion()` | Consulta la preferencia del sistema al cargar la vista. |
| `play(name)` / `toggleMuted()` | Efectos de sonido y silencio global. |
| `pushToast()` / `toast.success()` / `toast.error()` / `toast.info()` | Cola de avisos con límite de 4; los errores duran más que el resto. |
| `BattleFighter` | Recibe el combatiente, su vida/escudo y el estado de animación; calcula porcentajes y clases de la barra. |

## Cambios de esta revisión y por qué

- **Coherencia visual**: `PageHeader` unifica las cabeceras, `.hero` tiene estilos propios y los botones de decisión usan `ConfirmDialog` en lugar de `window.confirm`.
- **Movimiento cómodo**: `prefers-reduced-motion` se respeta en CSS y en la crónica; la rueda del mapa ya no bloquea el scroll (solo hace zoom con `Ctrl`/`⌘`).
- **Español**: `Piso N`, `Récord N`, plurales, `Sortear`, `Puntos de Fe`, `Poder`, `Milagroso` y concordancias revisadas en textos, ayudas y mensajes de validación.
- **Temas**: ocho paletas con `color-mix()` para los tonos derivados, acento por mazmorra en `--mz-accent` y restauración de la preferencia al salir.
- **Formularios y cargas**: estados ocupados deshabilitan los botones, los errores de API se muestran con `reportError()` y las narraciones no encadenan diálogos nativos.
- **Componentización**: utilidades compartidas (`reportError`, `useDismissable`, `nodeKinds`, `motion`), `ConfirmDialog`, `PageHeader`, `BattleFighter` y `DungeonMap`. La lógica que queda en `DungeonRun.vue` es la máquina de estados de la partida: extraerla exigiría prop drilling y haría el código menos legible, así que se conserva en la página.
- **Sin dependencias nuevas**: no se instalaron paquetes de npm ni de Composer; se reutilizan Vue e Inertia ya presentes.

## Revisión de noviembre de 2026

### Errores y movimiento

- Las tarjetas de `/mazmorras` dejaron de compartir el `minmax(180px, 1fr)` de las cartas de santos: ahora usan `minmax(320px, 1fr)`, más separación y más padding, y el retrato del próximo nodo mide 64 px.
- Se retiró la escena WebGL: el descenso usa solo el mapa 2D, sin conmutador de vista ni estado guardado en `localStorage`.
- «Reintentar» devuelve el foco al botón «Saltar animación» cuando la batalla vuelve a empezar. Antes se enfocaba un nodo que acababa de desmontarse.
- `scrollIntoView({ behavior: 'smooth' })` de Equipo y del diario de mazmorra respeta `prefers-reduced-motion()`.
- La crónica ya no arrastra el scroll hacia el final si el jugador se ha desplazado a leer líneas anteriores: solo se sigue pegado al final cuando estaba pegado.
- `PullReveal` omite el `animation-delay` escalonado con movimiento reducido, y la barra de progreso de Inertia sigue el `--accent` activo en lugar de quedar dorada.

### Accesibilidad y formularios

- `AppLayout` muestra los avisos `flash` también en la carga inicial (`watch` con `immediate: true`); antes se perdían tras una redirección.
- `ProgressMeter` acepta `label` y las barras del santuario y la colección lo usan. Las barras sin componente (Orar, expedición y vida de combatientes) llevan `role="progressbar"` con nombre accesible.
- `ConfirmDialog` nombra el diálogo con `aria-labelledby`/`aria-describedby` apuntando a su título y mensaje reales.
- El diario de mazmorra implementa el patrón de pestañas completo: `id`, `aria-controls`, `role="tabpanel"`, `tabindex` roving y navegación con flechas.
- El menú de perfil pasa de `role="menu"` incompleto a un `role="group"` con `aria-expanded`, coherente con su contenido.
- `.search:focus-within` restaura el foco visible que `outline: 0` eliminaba; `.btn--danger` usa texto oscuro sobre el rojo para cumplir contraste; los nodos deshabilitados del mapa dejan de parecer clicables.
- El indicador de guardado de Equipo tiene `aria-live="polite"`.

### Español y temas

- «Launcher de Santos» → «Lanzador de Santos»; «(dev)» → «(desarrollo)»; `Nv.` → `Nivel`; `Fe`/`XP` → `Puntos de Fe`/`Experiencia`; `PX` → `Poder`.
- «Sin revelar» se reserva para el santo velado; los no obtenidos ahora dicen «Sin obtener» y su ficha indica «Aún no tienes a este santo en tu santoral».
- El destacado del santuario usa `<h2>` porque `PageHeader` ya emite el único `<h1>` de la página.
- Las cifras grandes (Fe, victorias, derrotas y recompensas) pasan por `formatNumber()`.
- `theme.js` llama `surface` al tercer color de la paleta, igual que la variable CSS; el favicon y el color de respaldo de la barra de carga usan el dorado de Gloria.
- Los colores de sala están centralizados en `--node-*` y coinciden en mapa 2D y leyenda; `dungeonNodes.js` ya no duplica valores hex.

### Componentización y datos

- `GraceChip.vue` concentra el chip de Puntos de Fe del layout; `ProgressMeter` se reutiliza con nombre accesible.
- La barra de «mejor piso» del hub se eliminó: el dominio no tiene número máximo de pisos, así que `best_floor * 10` se llenaba a partir del piso 11. Ahora solo se muestran las cifras reales.
- Se conservan en sus páginas los slots de equipo y la máquina de estados de `DungeonRun.vue`: sus variantes (niveles/poder frente a vida/bendición) hacen que extraerlas exigiera más props y ramas que el código que duplican. Es deuda explícita, no un olvido.

### Mapa 2D

- La vista 3D y el conmutador «3D / Mapa» desaparecieron: `DungeonScene.vue` y la dependencia `three` se eliminaron, y `DungeonRun.vue` ya no guarda `sanctorum:dungeon-view`.
- El tablero vive en `DungeonMap.vue`: recibe el mapa, el equipo y `canChoose`, y avisa al padre con `move`; el zoom, el arrastre y el centrado son responsabilidad del componente.
- Las salas pasan de círculos de 34 px a tarjetas redondeadas de 42 px con tinte por tipo, etiqueta («Aquí», nombre de la sala o «Sin explorar») y chip «Ir» solo cuando se puede avanzar; los pasillos son más gruesos y el equipo se dibuja sobre la sala actual.
- Los controles de zoom se mudaron al mapa como grupo flotante con `aria-label`, y el fondo tiene un degradado suave que respeta la paleta activa.

### Verificación

`npm run build` compila sin errores. La revisión visual se hizo con árbol de accesibilidad, DOM y consola del navegador: no hay errores de consola ni diálogos nativos (`confirm`/`alert`).
