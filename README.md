# Sanctorum

Launcher de santos: gacha temático, colección, formación de equipos y descensos procedurales en mazmorras 3D/2D. Backend en Laravel 13, frontend en Vue 3 con Inertia y una hoja de estilos propia sin dependencias de CSS externas.

## Requisitos

- PHP 8.3 con extensiones de Laravel
- Composer 2
- Node 20+ y npm
- MySQL 8 (servicio `MySQL80`, configurado en `.env` con usuario `root` y la contraseña indicada)

## Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configura DB_HOST, DB_DATABASE, DB_USERNAME y DB_PASSWORD en .env
php artisan migrate --seed
npm install
npm run build
composer run dev
```

La aplicación sirve por defecto en `http://127.0.0.1:8000`. El idioma de la interfaz y de los mensajes es español (`APP_LOCALE=es`).

Para comprobar la base de datos sin imprimir credenciales:

```bash
php artisan db:show
php artisan migrate:status
```

Todas las migraciones deben aparecer como `Ran`. Si el servicio `MySQL80` está detenido, Windows puede exigir privilegios de administrador: abre PowerShell como administrador y ejecuta `Start-Service MySQL80` (o `net start MySQL80`). Las credenciales no se repiten en esta documentación; la aplicación las lee de `.env`.

## Comandos habituales

| Comando | Uso |
| --- | --- |
| `composer run dev` | Servidor Laravel + Vite en modo desarrollo |
| `npm run build` | Compila CSS y JS para producción |
| `php artisan test --compact` | Suite completa de PHPUnit |
| `php artisan test --filter=DungeonTest` | Una sola prueba |
| `vendor/bin/pint --dirty --format agent` | Formatea los archivos PHP modificados |
| `php artisan route:list --except-vendor` | Lista las rutas de la aplicación |

Hay un detalle oculto incluido en el proyecto: el comando `php artisan sanctum`. Está marcado como oculto, así que no aparece en `php artisan list` ni en la interfaz. Al ejecutarlo escribe en terminal la oración guardada en el santoral. La oración vive codificada en base64 dentro de `app/Console/Commands/RevealSanctum.php` y no se reparte en ningún archivo de `resources/`, de modo que no puede verse navegando la web ni leyendo el código fuente del frontend. `SanctumCommandTest` verifica tanto la salida del comando como su invisibilidad en la interfaz.

## Estructura

```
app/
  Console/Commands/   Comandos Artisan propios
  Http/Controllers/   Controladores Inertia y JSON
  Http/Middleware/    Resolución del jugador y compartición de props
  Services/           Lógica de gacha y combate
  Support/            Reglas de mazmorra, presentación y mensajes
  Models/             Eloquent
resources/
  css/app.css         Hoja única de estilos
  js/app.js           Entrada Inertia
  js/Components/      Componentes Vue reutilizables
  js/Layouts/         Layout común
  js/Pages/           Páginas Inertia
  js/lib/             Utilidades (API, diálogos, nodos, movimiento, tema, sonido, toasts, jugador)
  views/              Blade raíz
tests/Feature/        Pruebas HTTP y de comandos
tests/Unit/           Pruebas sin framework
docs/                 Documentación técnica
```

## Documentación

- [Arquitectura backend](docs/arquitectura.md): capas, modelos y controladores documentados, contrato de errores y decisiones.
- [Frontend y componentes](docs/frontend.md): componentes, contrato de utilidades, temas, accesibilidad y cambios de la revisión.
- [Mazmorras](docs/mazmorras.md): flujo y endpoints del descenso.
- [Pruebas](docs/testing.md): ejecución, cobertura y alcance de PHPUnit.

La revisión completa queda registrada así: interfaz y mensajes en español (`docs/frontend.md`), movimiento y temas (`docs/frontend.md`), base de datos y comandos propios (`docs/arquitectura.md`), formularios y estados de carga (`docs/frontend.md`), componentización Vue (`docs/frontend.md`), documentación de modelos y controladores (`docs/arquitectura.md`) y verificación con PHPUnit (`docs/testing.md`). No se añadieron dependencias de Composer ni de npm.
