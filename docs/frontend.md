# Frontend y componentes

## Entrada

`resources/js/app.js` monta Inertia, resuelve las páginas con `import.meta.glob` y aplica el tema antes de montar Vue para evitar el parpadeo de color.

`resources/views/app.blade.php` fija `lang="es"`, `data-theme="gloria"` y `data-font="clasica"` como valores iniciales.

## Componentes

| Componente | Uso |
| --- | --- |
| `AppLayout.vue` | Barra superior, navegación, perfil y avisos |
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

`resources/css/app.css` es una hoja única sin Tailwind: reset, temas, primitivas y estilos de las tres áreas (santuario, Orar y mazmorras). No hay archivos CSS adicionales.
