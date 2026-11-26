# 05 — Frontend

## Índice

- [1. Resumen de la capa](#1-resumen-de-la-capa)
- [2. Estructura y flujo de datos](#2-estructura-y-flujo-de-datos)
- [3. Hallazgos](#3-hallazgos)
  - [FE-001 — URL de desarrollo escrita a mano en Vue](#fe-001--url-de-desarrollo-escrita-a-mano-en-vue)
  - [FE-002 — Guardado automático sin cancelación ni espera](#fe-002--guardado-automático-sin-cancelación-ni-espera)
  - [FE-003 — El botón de salto no cancela el temporizador en curso](#fe-003--el-botón-de-salto-no-cancela-el-temporizador-en-curso)
  - [FE-004 — `localStorage` limitado a preferencias (informativo)](#fe-004--localstorage-limitado-a-preferencias-informativo)
  - [FE-005 — Zoom del mapa sin equivalente de teclado](#fe-005--zoom-del-mapa-sin-equivalente-de-teclado)
- [4. Accesibilidad verificada](#4-accesibilidad-verificada)

## 1. Resumen de la capa

| ID | Severidad | Estado |
| --- | --- | --- |
| `FE-001` | Media | Verificado estáticamente |
| `FE-002` | Media | Verificado estáticamente |
| `FE-003` | Informativa | Verificado estáticamente |
| `FE-004` | Informativa | Verificado estáticamente |
| `FE-005` | Baja | Verificado estáticamente |

El frontend es cohesionado: componentes reutilizables, cliente API único, errores en español, foco visible y respeto a `prefers-reduced-motion`. No se encontró `v-html`, interpolación sin escapar `{!! !!}` ni almacenamiento de datos de cuenta en el navegador. La deuda se concentra en el ciclo de vida de dos interacciones asíncronas.

## 2. Estructura y flujo de datos

| Zona | Archivos | Observación |
| --- | --- | --- |
| Páginas Inertia | `resources/js/Pages/*.vue` (8 archivos) | `DungeonRun.vue` concentra la animación de combate (1313 líneas) |
| Componentes | `resources/js/Components/*.vue` | `SaintCard`, `SaintDetail`, `DungeonMap`, `PullReveal`, `ConfirmDialog`, `ToastStack`, `ThemePicker`, `GraceChip`, `BattleFighter` |
| Layouts | `resources/js/Layouts/AppLayout.vue` | Navegación, perfil, sonido y reinicio de desarrollo |
| Librerías | `resources/js/lib/*.js` | `api`, `player`, `dialog`, `theme`, `sfx`, `toast`, `motion`, `dungeonNodes` |
| Estilos | `resources/css/app.css` (2622 líneas) | Una sola hoja, variables CSS y `color-mix()` |

Flujo de una mutación típica:

```js
// resources/js/lib/api.js:23-37
response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    },
    body: data === null ? undefined : JSON.stringify(data),
});
```

`reportError()` convierte cualquier fallo en un toast y un sonido (`resources/js/lib/api.js:59-63`), con `firstError()` para mostrar el primer mensaje de validación del servidor.

## 3. Hallazgos

### FE-001 — URL de desarrollo escrita a mano en Vue

| Campo | Detalle |
| --- | --- |
| **ID** | `FE-001` |
| **Severidad** | Media |
| **Capa** | Frontend / Integración Inertia |
| **Referencia** | Convención del repositorio: "Prefiere rutas nombradas y `route()`" |
| **Ubicación** | `resources/js/Layouts/AppLayout.vue:79-83`, `:173`; `app/Http/Middleware/HandleInertiaRequests.php:43`; `routes/web.php:39` |
| **Descripción** | El botón de reinicio envía `router.post('/desarrollo/reiniciar-cuenta')` como literal. El servidor ya calcula `canResetAccount` con `Route::has('dev.account.reset')`, pero no comparte la URL. |
| **Impacto** | Si se renombra o protege la ruta, el botón sigue visible en local y devuelve 404 sin aviso; se rompe la convención de rutas nombradas del proyecto. |
| **Escenario** | Se cambia la ruta a `/dev/reiniciar` durante una limpieza; en local el botón falla silenciosamente y nadie lo detecta porque las pruebas no cubren el cliente. |
| **Recomendación** | Añadir `'resetRoute' => fn () => Route::has('dev.account.reset') ? route('dev.account.reset') : null` en `HandleInertiaRequests::share()` y usar `router.post(page.props.resetRoute)` en el componente, con `v-if="page.props.resetRoute"`. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (1 hora) |
| **Estado de verificación** | Verificado por búsqueda en `resources/`; `canResetAccount` aparece solo en `AppLayout.vue:173`. |

**Evidencia**:

```js
// resources/js/Layouts/AppLayout.vue:79-83
function confirmResetAccount() {
    confirmReset.value = false;
    play('click');
    router.post('/desarrollo/reiniciar-cuenta');
}
```

**Ejemplo de corrección**:

```php
// app/Http/Middleware/HandleInertiaRequests.php:43
'canResetAccount' => app()->environment(['local', 'testing']) && Route::has('dev.account.reset'),
'resetRoute' => fn () => Route::has('dev.account.reset') ? route('dev.account.reset') : null,
```

```js
router.post(page.props.resetRoute);
```

---

### FE-002 — Guardado automático sin cancelación ni espera

| Campo | Detalle |
| --- | --- |
| **ID** | `FE-002` |
| **Severidad** | Media |
| **Capa** | Frontend / Ciclo de vida |
| **Referencia** | CWE-362 (Race Condition) — variante en cliente |
| **Ubicación** | `resources/js/Pages/Team.vue:49-52`, `:69-94`, `:97-114` |
| **Descripción** | El equipo se guarda con un debounce de 700 ms y, al desmontar la página, `onBeforeUnmount` dispara `persist()` sin `await` y sin `AbortController`. La petición puede completarse después de la navegación y su error no se muestra al usuario. |
| **Impacto** | Si el usuario cambia de equipo y navega rápido, puede creer que se guardó cuando la petición fue abortada por el navegador o falló; no hay reintento ni indicador persistente. |
| **Escenario** | El usuario reordena santos y pulsa "Mazmorras" antes de 700 ms. El guardado se lanza en `onBeforeUnmount`, la navegación Inertia cancela el `fetch` y el servidor conserva el equipo anterior. |
| **Recomendación** | Usar el evento `router.on('before', ...)` de Inertia para esperar a `persist()` antes de navegar, o persistir de forma síncrona con `router.post(..., { preserveScroll: true })`. Mantener un `AbortController` y marcar el estado como `pending` hasta confirmación. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Medio (3-4 horas) |
| **Estado de verificación** | Verificado por lectura de `Team.vue`; no se probó en navegador (`node_modules/` ausente). |

**Evidencia**:

```js
// resources/js/Pages/Team.vue:49-52
function scheduleSave() {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => persist(), 700);
}

// resources/js/Pages/Team.vue:108-114
onBeforeUnmount(() => {
    window.clearTimeout(debounceTimer);

    if (!saving.value && !sameTeam(team.value, savedTeam.value)) {
        persist();
    }
});
```

**Ejemplo de corrección**:

```js
// Keep the pending save visible across navigation.
let pendingSave = null;

function persist(manual = false) {
    if (pendingSave) {
        return pendingSave;
    }

    pendingSave = post('/equipo', { team: [...team.value] })
        .finally(() => { pendingSave = null; });

    return pendingSave;
}

onBeforeUnmount(() => {
    window.clearTimeout(debounceTimer);
});
```

---

### FE-003 — El botón de salto no cancela el temporizador en curso

| Campo | Detalle |
| --- | --- |
| **ID** | `FE-003` |
| **Severidad** | Informativa |
| **Capa** | Frontend / Animación |
| **Referencia** | CWE-400 (Uncontrolled Resource Consumption) — impacto mínimo |
| **Ubicación** | `resources/js/Pages/DungeonRun.vue:208-225`, `:474-493`, `:811-812`, `:844-849` |
| **Descripción** | `skipAnimation()` solo pone `skipRequested = true`. El `await wait(...)` ya planificado sigue hasta su vencimiento (máximo ~420 ms según `delayFor`) antes de que el bucle deje de esperar. Los timers se limpian correctamente al desmontar. |
| **Impacto** | El salto tarda una fracción de segundo en hacer efecto. No hay fuga: `clearTimers()` se ejecuta en `onBeforeUnmount`. Con `prefers-reduced-motion` el salto está activo desde el inicio (`DungeonRun.vue:234`). |
| **Escenario** | Un usuario pulsa "Saltar" durante un `cast` (380 ms × ritmo); la animación se detiene en el siguiente ciclo, no inmediatamente. |
| **Recomendación** | Si se quiere salto instantáneo, guardar el ID del temporizador activo y llamar a `window.clearTimeout()` desde `skipAnimation()`. No es urgente. |
| **Ejemplo** | Sin código nuevo: reutilizar `clearTimers()` dentro de `skipAnimation()` y fijar `applyEntry(entry, true)` para el resto. |
| **Esfuerzo** | Bajo (30 minutos) |
| **Estado de verificación** | Verificado por lectura estática. |

**Evidencia**:

```js
// resources/js/Pages/DungeonRun.vue:484-492
if (!skipRequested.value) {
    await wait(delayFor(entry) * pacing);
}
...
applyEntry(entry, skipRequested.value);

// resources/js/Pages/DungeonRun.vue:811-812
function skipAnimation() {
    skipRequested.value = true;
}
```

---

### FE-004 — `localStorage` limitado a preferencias (informativo)

| Campo | Detalle |
| --- | --- |
| **ID** | `FE-004` |
| **Severidad** | Informativa |
| **Capa** | Frontend / Privacidad |
| **Referencia** | OWASP ASVS V3 — protección de datos en cliente |
| **Ubicación** | `resources/js/lib/theme.js:4-6`, `:95-108`; `resources/js/lib/sfx.js:3-15`; `resources/js/lib/motion.js:1-4` |
| **Descripción** | El navegador guarda únicamente tema, tipografía y estado de silenciado. Ambas lecturas están envueltas en `try/catch`, de modo que el modo privado o un `localStorage` bloqueado no rompe la app. Ningún dato de cuenta, progreso o token se escribe en el cliente. |
| **Impacto** | Ninguno negativo; se registra como control positivo. |
| **Escenario** | Un usuario con cookies de terceros bloqueadas sigue pudiendo jugar; solo pierde la preferencia de tema. |
| **Recomendación** | Mantener la separación: si en el futuro se guarda progreso offline, cifrarlo y documentarlo, y nunca duplicar el token de jugador. |
| **Ejemplo** | Sin código nuevo. |
| **Esfuerzo** | No aplica |
| **Estado de verificación** | Verificado por búsqueda de `localStorage` en `resources/js/`. |

**Evidencia**:

```js
// resources/js/lib/sfx.js:7-13
function readMuted() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}
```

---

### FE-005 — Zoom del mapa sin equivalente de teclado

| Campo | Detalle |
| --- | --- |
| **ID** | `FE-005` |
| **Severidad** | Baja |
| **Capa** | Frontend / Accesibilidad |
| **Referencia** | WCAG 2.1 SC 2.1.1 (Keyboard); ASVS V5 |
| **Ubicación** | `resources/js/Components/DungeonMap.vue:95-126`, `:129-138`, `:189-195` |
| **Descripción** | El desplazamiento (`pan`) del mapa ampliado solo se controla con puntero (`@pointerdown`, `@pointermove`). Los botones de zoom sí son accesibles por teclado y las salas son `<button>` con `aria-label`, por lo que la navegación funciona, pero no se puede desplazar la vista con flechas. |
| **Impacto** | Una persona que use solo teclado puede ampliar el mapa pero no recentrar visualmente una zona alejada sin ratón. El botón central "Centrar el mapa" mitiga parcialmente el problema. |
| **Escenario** | Con zoom al 250 %, un usuario de teclado tabula hasta una sala situada al borde; el foco se mueve pero el viewport no sigue, por lo que no ve dónde está. |
| **Recomendación** | Añadir `tabindex="0"` al viewport y un `@keydown` que mueva `pan` con las flechas, con `aria-label="Mapa de la mazmorra; usa las flechas para desplazarte"`. Respetar `prefers-reduced-motion` para el desplazamiento. |
| **Ejemplo** | Bloque inferior. |
| **Esfuerzo** | Bajo (2-3 horas) |
| **Estado de verificación** | Verificado por lectura del componente. |

**Evidencia**:

```vue
<!-- resources/js/Components/DungeonMap.vue:129-138 -->
<div
    class="mz-viewport"
    :class="{ 'is-zoomed': zoom > ZOOM_MIN, 'is-dragging': drag.active }"
    @wheel="onWheel"
    @pointerdown="onDragStart"
    @pointermove="onDragMove"
    @pointerup="onDragEnd"
    @pointercancel="onDragEnd"
>
```

**Ejemplo de corrección**:

```vue
<div
    class="mz-viewport"
    tabindex="0"
    role="group"
    aria-label="Mapa de la mazmorra; usa las flechas para desplazarte"
    @keydown="onKeydown"
>
```

```js
function onKeydown(event) {
    const step = 40;
    const deltas = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
    const delta = deltas[event.key];

    if (!delta || zoom.value <= ZOOM_MIN) {
        return;
    }

    event.preventDefault();
    pan.x += delta[0];
    pan.y += delta[1];
}
```

## 4. Accesibilidad verificada

| Control | Evidencia |
| --- | --- |
| Foco visible global | `resources/css/app.css:202-205` (`:focus-visible` con contorno de 3 px) |
| Reducción de movimiento global | `resources/css/app.css:233-242` |
| Diálogo de confirmación con foco atrapado y `Escape` | `resources/js/Components/ConfirmDialog.vue:18-33`; `resources/js/lib/dialog.js:35-89` |
| Cierre de menús con `Escape` y restauración de foco | `resources/js/lib/dialog.js:7-32` |
| Avisos con `aria-live` | `resources/js/Components/ToastStack.vue:10-16` |
| Estado de guardado anunciado | `resources/js/Pages/Team.vue:227` (`aria-live="polite"`) |
| Navegación con `aria-current="page"` | `resources/js/Layouts/AppLayout.vue:113-125` |
| Salas del mapa como botones con `aria-label` | `resources/js/Components/DungeonMap.vue:152-174` |
| Foco devuelto tras animación y cambio de pestaña | `resources/js/Pages/DungeonRun.vue:758-773` |
| Saltar animación activo con movimiento reducido | `resources/js/Pages/DungeonRun.vue:234`, `:811-812` |
| Sin `window.confirm` / `window.alert` | Búsqueda negativa en `resources/js/` |
