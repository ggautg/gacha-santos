# AGENTS.md — Sanctorum

Guía corta y vigente para trabajar en este repositorio. Si algo aquí contradice al código, **el código manda**: actualiza esta guía al corregirlo.

## Qué es esto

Lanzador de santos: gacha, colección, equipos y mazmorras procedurales. Backend **Laravel 13 / PHP 8.3**, frontend **Vue 3 + Inertia**, mapas 2D en SVG/CSS y estilos propios en una sola hoja CSS (no se usan clases de Tailwind aunque el paquete esté instalado). Sin login: la identidad es el `Player` de la sesión. Toda la interfaz, mensajes y documentación son **español**.

## Arrancar

```bash
composer install
npm install
cp .env.example .env      # DB_CONNECTION=mysql, DB_DATABASE=gacha_santos, root / contraseña en .env
php artisan key:generate
php artisan migrate --seed
npm run build
composer run dev          # http://127.0.0.1:8000
```

MySQL corre como servicio `MySQL80` de Windows. Si está parado: `Start-Service MySQL80` desde PowerShell con privilegios elevados. Las credenciales viven **solo en `.env`**; no las repitas en docs ni en código.

## Comandos de verificación obligatorios antes de terminar

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

## Convenciones del proyecto

- Usa `php artisan make:` con `--no-interaction` para crear archivos; no inventes carpetas base ni añadas dependencias sin aprobación.
- Controladores delgados: validan y delegan en `app/Services`, `app/Support` o modelos. Errores de dominio → `422` con `{ message, errors }` en español.
- Modelos y controladores llevan **PHPDoc** de clase y de cada método propio; explica responsabilidad, no reproduzca el código.
- Prefiere rutas nombradas y `route()`. Lee configuración con `config()` desde `config/sanctorum.php`, nunca valores sueltos.
- Todas las estructuras de control llevan llaves, aunque sean de una línea. Tipos explícitos en parámetros y retornos. Constructor con property promotion. Enums en `TitleCase`.
- Vue: componentiza primero; reutiliza lo que ya existe en `resources/js/Components` y `resources/js/lib`. La hoja `resources/css/app.css` es única: añade estilos con variables CSS (`--accent`, `--accent-2`, `--surface`, `--bg`, `--mz-accent`) y `color-mix()`.
- Accesibilidad no negociable: foco visible, modales con foco atrapado + `Escape`, `aria-live` en avisos, `prefers-reduced-motion` respetado en CSS y en las animaciones del mapa.
- Textos en español correcto: `Piso N`, `Récord N`, `Sortear`, `Puntos de Fe`; sin `window.confirm`/`alert`. Usa `ConfirmDialog`, `reportError()` y `toast`.
- Pruebas con PHPUnit + `RefreshDatabase` y factories. Cubre comportamiento observable (HTTP, estado, economía), no detalles de implementación. Suite: `php artisan test --compact`.
- Documenta cada cambio relevante en `docs/`: `arquitectura.md`, `frontend.md`, `mazmorras.md`, `testing.md`. No crees otros `.md` sin que te lo pidan.

## Skills (en `.agents/skills/`)

- `laravel-best-practices`: cualquier código PHP de Laravel.
- `testing-best-practices`: escribir o revisar pruebas.
- `tailwindcss-development`: solo si aparece Tailwind de verdad en el trabajo (hoy no se usa).
- `deploying-to-cloud`: despliegues en Laravel Cloud.
- `infer-conventions`: cuando haya que deducir convenciones nuevas.

## Mapa rápido

| Zona | Archivos |
| --- | --- |
| Dominio | `app/Models`, `app/Services`, `app/Support`, `app/Console/Commands` |
| HTTP | `app/Http/Controllers`, `app/Http/Middleware`, `routes/` |
| Datos | `database/migrations`, `database/seeders`, `database/factories` |
| Frontend | `resources/js/{Pages,Components,Layouts,lib}`, `resources/css/app.css` |
| Pruebas | `tests/Feature`, `tests/Unit` |
| Docs | `docs/` y `README.md` |
