# WordPress

## Código de temas y plugins

Las cuatro preguntas en cada acción: quién puede, si lo pidió de verdad, qué entra y qué sale.

- **Permisos:** `current_user_can()` con la capability exacta. `is_admin()` no verifica permisos: solo dice que es una página del panel.
- **Nonces:** `wp_nonce_field()` y `check_admin_referer()` en formularios; `check_ajax_referer()` en AJAX. Protegen contra CSRF, no reemplazan el chequeo de permisos.
- **Entrada:** `wp_unslash()` y después `sanitize_text_field()`, `sanitize_email()`, `absint()`, `esc_url_raw()` o lista cerrada.
- **Salida:** escapar al imprimir con `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()` o `wp_kses_post()`. También en traducciones (`esc_html__()`).
- **Base de datos:** `$wpdb->prepare()` con marcadores. Los nombres de tabla o columna salen de una lista cerrada.
- **REST:** `register_rest_route()` siempre con un `permission_callback` real. `__return_true` solo si es público de verdad, y con `sanitize_callback` y `validate_callback` en los argumentos.
- **AJAX:** `wp_ajax_nopriv_` solo si los visitantes deben usarlo.
- **Archivos:** bloquear el acceso directo con `defined( 'ABSPATH' ) || exit;`. Subir con `wp_handle_upload()` y validar con `wp_check_filetype_and_ext()`. Nunca `include` con datos del usuario.
- **Prefijos** en funciones, opciones y tablas, para no chocar con otros plugins.
- **HTTP saliente:** `wp_safe_remote_get()` cuando la URL no es fija.
- **Prohibido:** `extract()` con datos externos, `eval()`, `unserialize()` con datos del usuario.
- **Revisión:** PHPCS con WordPress Coding Standards y Plugin Check antes de publicar.

## Instalación

- `wp-config.php` con permisos `400` o `440`, y si se puede fuera de la raíz pública.
- `define( 'DISALLOW_FILE_EDIT', true );` para quitar el editor del panel.
- Directorios `0755` y archivos `0644`. Solo `wp-content` con la escritura necesaria.
- Bloquear la ejecución de PHP en `wp-content/uploads`, y el acceso directo a archivos internos de `wp-includes`.
- Usuario de base de datos con solo `SELECT`, `INSERT`, `UPDATE` y `DELETE`. Tener en cuenta que algunos plugins y actualizaciones cambian el schema: dar más permisos durante esa tarea.
- Una base y un usuario por sitio.

## Operación

- Actualizaciones automáticas, al menos las de seguridad. Quitar plugins y temas sin uso, no solo desactivarlos.
- Plugins con mantenimiento activo. Revisar vulnerabilidades con WPScan o Patchstack.
- 2FA en toda cuenta con acceso al panel y límite de intentos de login.
- Usuario sin nombre predecible. Lista de usuarios por REST cerrada si no hace falta.
- XML-RPC desactivado si no se usa. Application Passwords solo si se usan.
- Copias de seguridad fuera del servidor y restauración probada.

## Señales de alerta al revisar

- `$_POST` o `$_GET` usados sin sanitizar, o impresos sin escapar.
- `$wpdb->query()` con variables interpoladas.
- `permission_callback` ausente o `__return_true` en rutas que escriben.
- Acciones de administración con nonce, pero sin `current_user_can()`.

**Fuentes:** WordPress Developer Resources: Common APIs Handbook (Security), Plugin Handbook (Security), Hardening WordPress · WordPress Coding Standards
