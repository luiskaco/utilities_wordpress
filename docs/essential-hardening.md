# Documentación Técnica: Essential Hardening & Security

Suite modular, ultraligera y sin dependencias externas para el endurecimiento y seguridad perimetral integral de sitios WordPress.

- **Versión:** 1.7.0
- **Licencia:** GPL-2.0-or-later
- **Compatibilidad:** WordPress 5.8+ | PHP 7.4 - 8.5

---

## 1. Visión General

El plugin **Essential Hardening & Security** está diseñado bajo los principios de máxima eficiencia defensiva y mínima sobrecarga operativa:
- **Cero dependencias externas:** no requiere librerías pesadas, compiladores ni conexiones de telemetría a servidores remotos.
- **Sin impacto en rendimiento:** ejecuta únicamente hooks nativos de WordPress en memoria con tiempo de procesamiento sub-milisegundo.
- **Portabilidad universal:** desacoplado por completo de cualquier tema o hosting. Funciona tanto como plugin estándar en `wp-content/plugins/` o como plugin obligatorio en `wp-content/mu-plugins/`.
- **Persistencia limpia:** guarda un único registro en la tabla `wp_options` sin crear tablas adicionales.
- **Rescate de emergencia para 2FA:** configurable mediante `EH_DISABLE_2FA` en `wp-config.php`.

---

## 2. Los 24 Módulos de Protección y Auditoría

### 2.1. Micro-WAF / Cortafuegos de Peticiones y Query Strings
Inspecciona tanto la cadena de consulta cruda (`QUERY_STRING`) como la decodificada en cada petición HTTP antes de que los componentes del núcleo o plugins comiencen su procesamiento:
- **Inyección SQL:** Detecta `UNION SELECT`, `benchmark()`, `sleep(\d+)`.
- **Ejecución Remota de Código (RCE):** Detecta funciones de ofuscación y ejecución arbitraria como `eval(`, `base64_decode(`, `gzinflate(`, `passthru(`, `shell_exec(`.
- **Recorrido de Directorios / LFI:** Bloquea secuencias `../`, `..\`, `/etc/passwd` y accesos dirigidos hacia `wp-config.php`.
- **Cross-Site Scripting (XSS):** Bloquea `<script>` y esquemas `javascript:` en parámetros URL.
- **Respuesta:** Detiene la petición de inmediato con **HTTP 403 Forbidden**. Los administradores autenticados dentro del panel no se ven afectados para evitar falsos positivos durante tareas legítimas.

### 2.2. Mitigación de Fuerza Bruta y Rate-Limiting Configurable (Login Throttle)
Protege la pantalla de inicio de sesión `/wp-login.php` contra ataques masivos de diccionario:
- **Retardo disuasivo (Tarpit):** Añade una pausa deliberada de `sleep(2)` segundos al fallar cualquier intento de autenticación, frustrando la velocidad de los bots automatizados.
- **Bloqueo por IP:** Registra fallos sucesivos utilizando `transients` nativos en memoria indexados por hash de la IP.
- **Umbral de tolerancia configurable:** Selector de 3 intentos (estricto), 5 intentos (estándar recomendado) o 10 intentos (permisivo).
- **Duración de bloqueo configurable:** Permite fijar el tiempo de espera en 15 minutos, 30 minutos, 1 hora, 4 horas o 24 horas (`eh_ip_locked`).
- **Reseteo automático:** Al introducir credenciales correctas, el contador y bloqueo se eliminan al instante.

### 2.3. Protección del Directorio de Subidas (`wp-content/uploads/`)
Inyecta y mantiene un archivo `.htaccess` defensivo directamente en la raíz de `wp-content/uploads/`:
- **Regla Apache / Litespeed:** Deniega la ejecución de archivos ejecutables (`.php`, `.phtml`, `.php3`, `.php4`, `.php5`, `.php7`, `.phps`, `.phar`, `.inc`).
- **Impacto:** Si un atacante consigue subir un webshell o troyano mediante una vulnerabilidad en un plugin de terceros o formulario no sanitizado, el servidor web rechazará ejecutar el código PHP y devolverá un error 403.

### 2.4. Bloqueo de Enumeración de Usuarios (Anti-Reconnaissance)
Impide que atacantes y bots automáticos descubran los nombres de usuario válidos para ejecutar ataques de diccionario o fuerza bruta:
- **Escaneos por URL:** Intercepta peticiones del tipo `/?author=1`, `/?author=2` y las redirige con un **301 Permanente** a la portada.
- **REST API Pública:** Oculta el endpoint `/wp/v2/users` a visitantes anónimos, retornando un **HTTP 404 Not Found**. Los usuarios autenticados con permisos continúan teniendo acceso normal.

### 2.5. Anonimización de Nombres de Usuario en `body_class`
WordPress agrega automáticamente clases como `author-admin` o `author-{username}` a la etiqueta `<body>` en entradas individuales y archivos de autor.
- **Filtro defensivo:** Intercepta `body_class` y remueve cualquier clase que comience con `author-`.
- **Impacto:** Impide que herramientas de scraping extraigan los identificadores de login leyendo el código HTML de las páginas públicas.

### 2.6. Desactivación Total de Comentarios y Trackbacks
Ideal para sitios institucionales, corporativos, tiendas o blogs sin moderación activa donde los comentarios representan un vector de spam masivo e inyecciones de enlaces maliciosos:
- **Front-end:** Devuelve `false` en `comments_open` y `pings_open`, y un arreglo vacío en `comments_array`.
- **Tipos de Contenido:** Remueve el soporte (`comments`, `trackbacks`) en posts, páginas y CPTs registrados.
- **Protección de Endpoint:** Intercepta cualquier llamada POST directa hacia `wp-comments-post.php` y responde con **HTTP 403 Forbidden**.
- **REST API:** Suprime los endpoints `/wp/v2/comments` y `/wp/v2/comments/{id}`.
- **Feeds:** Bloquea feeds RSS y Atom de comentarios (`do_feed_rss2_comments`, `do_feed_atom_comments`).
- **Panel Administrativo:** Oculta el menú `edit-comments.php`, redirige accesos directos al escritorio y remueve las cajas de metadatos en editores.

### 2.7. Limpieza de Metadatos y Enlaces Obsoletos en `<head>`
Elimina etiquetas `link` obsoletas y delatoras generadas por defecto por el núcleo:
- `rsd_link`: Enlace a Really Simple Discovery (XML-RPC).
- `wlwmanifest_link`: Manifiesto para el software descontinuado Windows Live Writer.
- `wp_shortlink_wp_head`: Enlace de URL corta (`?p=123`).
- `rest_output_link_wp_head` y `wp_oembed_add_discovery_links`: Enlaces de autodescubrimiento en la cabecera.
- `adjacent_posts_rel_link_wp_head`: Enlaces relacionales adyacentes.

### 2.8. Ocultamiento de Huellas del CMS
Dificulta el escaneo automatizado de vulnerabilidades basadas en versión:
- Retira la etiqueta `<meta name="generator" content="WordPress ...">` del `<head>` y de los feeds.
- Elimina el parámetro de consulta `?ver=X.X.X` de las hojas de estilo y scripts cargados en el front-end público.

### 2.9. Cabeceras HTTP de Seguridad
Inyecta cabeceras defensivas estándar en la respuesta HTTP (`send_headers`):
- `X-Frame-Options: SAMEORIGIN`: Protege contra ataques de clickjacking impidiendo que el sitio sea embebido en iframes de terceros.
- `X-Content-Type-Options: nosniff`: Fuerza a los navegadores a respetar el tipo MIME declarado, evitando ejecución de código malicioso disfrazado.
- `X-XSS-Protection: 0`: desactiva a propósito el filtro XSS de navegadores antiguos (obsoleto, puede introducir fallos). HSTS y CSP son módulos aparte y opcionales (2.18 y 2.19).
- `Referrer-Policy: strict-origin-when-cross-origin`: Limita el envío de URLs de referencia a dominios externos.
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`: Desactiva permisos de hardware innecesarios.
- `X-Pingback`: Remueve la cabecera para mitigar ataques de amplificación.

### 2.10. Desactivación de XML-RPC
- Bloquea por completo la interfaz XML-RPC (`add_filter('xmlrpc_enabled', '__return_false')`), neutralizando ataques históricos de fuerza bruta y ataques DDoS de reflexión.

### 2.11. Mensajes de Login Genéricos
- Ofusca los mensajes de error en `wp-login.php` mostrando siempre: *"Usuario o contraseña incorrectos."*, evitando que un atacante determine si acertó con un nombre de usuario existente.

### 2.12. Bloqueo de Edición de Archivos PHP
- Define la constante `DISALLOW_FILE_EDIT = true` para deshabilitar los editores de temas y plugins dentro del panel de administración, mitigando inyecciones de código ante compromisos de sesión.

### 2.13. Honeypot Anti-Bot en Pantalla de Login (Sin Captcha)
Protección transparente contra bots y atacantes automatizados en `/wp-login.php`:
- **Campo trampa invisible:** Inyecta un campo camuflado en el formulario de login con estilos ocultos para humanos.
- **Detección inmediata:** Los bots automatizados que rastrean y autocompletan inputs lo llenan, y se les rechaza el login al instante (error de autenticación, sin retardo).
- **Sin molestias:** Los usuarios humanos no ven el campo ni deben resolver captchas visuales. Cero dependencias y privacidad total.

### 2.14. Alertas de Actualizaciones Pendientes y Completadas
Monitoreo preventivo y proactivo del ecosistema del sitio:
- **Chequeo diario en segundo plano:** Evalúa diariamente si existen versiones pendientes para plugins, temas o el núcleo de WordPress.
- **Reporte consolidado por correo:** Si se detectan actualizaciones disponibles, envía un correo detallado al administrador indicando los nombres de componentes, versiones actuales y nuevas, más el enlace directo de actualización.
- **Control anti-saturación:** Emplea una firma hash en memoria para emitir un único correo diario por grupo de actualizaciones, evitando el bombardeo continuo de mensajes.
- **Enrutamiento de auto-actualizaciones:** Garantiza que los informes de auto-actualizaciones exitosas ejecutadas por WordPress se entreguen a la casilla configurada.

### 2.15. Alertas de Inicio de Sesión de Administradores
Supervisión activa de accesos privilegiados al panel de control:
- **Hook de login:** Se engancha en `wp_login` y evalúa si el usuario autenticado posee la capacidad `manage_options`.
- **Información contextual:** Envía un correo electrónico con nombre de usuario, fecha y hora local, dirección IP y el `HTTP_USER_AGENT` del navegador.
- **Detección temprana:** Permite al administrador enterarse instantáneamente si alguien ha utilizado sus credenciales desde una ubicación geográfica o dispositivo inusual.

### 2.16. Doble Factor de Autenticación (2FA) por Correo
Protección en dos pasos nativa y sin aplicaciones externas:
- **Flujo interactivo:** Al verificar la contraseña en `wp-login.php`, el plugin retiene el login y genera un código criptográfico de 6 dígitos con validez de 10 minutos almacenado como transient encriptado.
- **Entrega segura:** Envía el código al correo del usuario y redirige a la pantalla interactiva de verificación en `wp-login.php?action=eh_2fa`.
- **Límite de intentos:** Permite un máximo de 3 intentos del código; al tercer fallo, el transient se destruye y el evento se registra como intento no autorizado.
- **Bypass de emergencia:** En caso de fallas de correo en el servidor, un administrador con acceso FTP o cPanel puede desactivar temporalmente el 2FA agregando `define( 'EH_DISABLE_2FA', true );` en `wp-config.php`.
- **Valor por defecto:** Deshabilitado (`false`) para activación opcional.

### 2.17. Registro en Vivo de Ataques Neutralizados (Mini-Log)
Visibilidad en tiempo real de los intentos maliciosos bloqueados:
- **Capacidad rotativa:** Almacena de forma circular las últimas 25 incidencias neutralizadas en la opción `eh_security_events_log` con `autoload = false`.
- **Eventos registrados:** Bloqueos del Micro-WAF (inyección SQL, RCE, traversal), trampas honeypot disparadas, rate limiting de fuerza bruta, fallos de código 2FA y sondeo de usuarios por query string.
- **Interfaz administrativa:** Card dedicada con tabla clasificada por etiquetas de color, fecha/hora formateada, IP y detalle técnico, junto con botón para vaciar el historial.

---

### 2.18. HSTS (Forzar HTTPS) — opcional, desde 1.5.0
- Opción `hsts_enabled` (por defecto `false`) y `hsts_max_age` (por defecto 30 días; valores permitidos: 300, 86400, 2592000, 15552000, 31536000).
- Solo se envía si la petición es HTTPS (`is_ssl()`): por HTTP el navegador la ignora.
- `includeSubDomains` no se envía salvo que se active con el filtro `essential_hardening_hsts_include_subdomains`, porque rompe subdominios sin HTTPS válido.
- No incluye `preload`: es prácticamente irreversible.

### 2.19. Content-Security-Policy en modo solo-reporte — opcional, desde 1.5.0
- Opción `csp_report_only` (por defecto `false`). Envía **solo** `Content-Security-Policy-Report-Only`; nunca la cabecera que bloquea.
- Política por defecto compatible con un WordPress estándar (`'unsafe-inline'` y `'unsafe-eval'` en scripts, imágenes y fuentes por HTTPS). Se reemplaza con `essential_hardening_csp_policy`.
- Los saltos de línea de la política se eliminan para impedir la inyección de cabeceras.
- No hay endpoint de informes: las infracciones se ven en la consola del navegador.

### 2.20. Alertas de Administradores Nuevos o Promovidos — desde 1.6.0
- Opción `admin_change_alerts` (por defecto `true`).
- Hooks: `user_register` (usuario creado ya con rol `administrator`), `set_user_role` (nuevo rol `administrator` que no estaba en los anteriores) y `add_user_role`.
- Destinatario: `update_notify_email` o, si está vacío, el correo del administrador del sitio.
- Anti-ruido: un transient `eh_admin_change_<ID>` de 5 minutos evita avisos repetidos por el mismo usuario (crear un usuario dispara varios hooks).
- El correo incluye el usuario afectado, quién hizo el cambio (`wp_get_current_user()`, o "ninguno" si fue WP-CLI, cron o una petición sin sesión), la IP y la hora, y se registra como `Cambio de Admin` en el mini-log.
- Limitación: solo detecta cambios hechos a través de la API de WordPress. Un atacante que escriba directamente en la base de datos no dispara estos hooks.

### 2.21. Verificación de Integridad del Núcleo — opcional, desde 1.6.0
- Opción `core_integrity_check` (por defecto `false`). Cron diario `eh_daily_core_integrity_cron`, que se desprograma si se apaga la opción o se desactiva el plugin.
- Fuente: `get_core_checksums()` de WordPress (`api.wordpress.org/core/checksums/1.0/`), con la versión y el idioma instalados. Si el idioma no tiene lista propia (ej. `es_PE`) se usa la de `en_US` y `wp-includes/version.php` se marca como "no comparar" (cambia con el paquete de idioma).
- `compare_core_files()` devuelve tres listas: `modified` (md5 distinto), `missing` (falta) y `unexpected` (archivos `.php`, `.phtml`, `.phar` o `.php3-8` en la raíz, `wp-admin` o `wp-includes` que no están en la lista oficial). `wp-content` y `wp-config.php` quedan fuera.
- Se ignoran por defecto `readme.html`, `license.txt`, `wp-config-sample.php` y `wp-admin/install.php` (muchos hostings los eliminan); se ajusta con el filtro `essential_hardening_core_integrity_ignore`.
- Resultado en la opción `eh_core_integrity_last` (fecha, estado `clean` / `issues` / `unavailable` y listas). El correo se envía una vez por hallazgo distinto (hash en el transient `eh_last_core_alert_hash`, hasta 7 días) y se reinicia al quedar limpio. Si la API no responde, el estado es `unavailable` y **no** se envía correo.
- El correo no incluye rutas absolutas del servidor, solo rutas relativas a la raíz de WordPress.
- Antes de verificar se amplía el límite de ejecución a 300 s (`set_time_limit`), porque hay que leer unos 3.500 archivos. En pruebas tardó unos 3 s con los archivos ya en caché del sistema; la primera lectura en frío, en Windows con antivirus, llegó a 137 s. En un hosting normal se espera mucho menos, pero por eso el límite se amplía.
- Privacidad: la única llamada saliente es la de las sumas de control, una vez al día. No se envían datos del sitio aparte de la versión y el idioma de WordPress.

### 2.22. Protección de la Recuperación de Contraseña — desde 1.7.0
- Opción `lostpassword_protection` (por defecto `true`).
- `lostpassword_post`: máximo 5 solicitudes POST por IP y hora (transient `eh_lostpw_<md5(ip)>`, filtro `essential_hardening_lostpassword_max_per_hour`). Al superarlo añade el error `eh_lostpw_limited`. El evento `Recuperación` se registra solo la primera vez que se supera el límite. El contador no se renueva con los intentos bloqueados, así que un atacante no puede extender el castigo.
- `lostpassword_errors` (prioridad 99): si los únicos errores son `invalid_email`, o no hay errores pero el usuario no existe, redirige a `wp-login.php?checkemail=confirm`, la misma pantalla que ve una solicitud válida. Los errores de campo vacío y de límite se siguen mostrando.
- Limitación conocida: una solicitud válida envía un correo y una inexistente no, así que un atacante muy paciente podría distinguirlas midiendo tiempos de respuesta. El límite de 5 por hora lo hace poco práctico.

### 2.23. Desactivar Contraseñas de Aplicación — opcional, desde 1.7.0
- Opción `disable_application_passwords` (por defecto `false`). Aplica `__return_false` a `wp_is_application_passwords_available`.
- Motivo: la autenticación por contraseña de aplicación en la REST API no pasa por `wp-login.php`, así que no la cubren ni el 2FA por correo ni el bloqueo de intentos.

### 2.24. Contraseñas Robustas para Administradores — opcional, desde 1.7.0
- Opción `strong_admin_passwords` (por defecto `false`).
- Hooks: `user_profile_update_errors` (crear o editar usuarios; valida cuando el rol será `administrator` o el usuario existente tiene `manage_options`) y `validate_password_reset` (restablecimiento desde el correo).
- Reglas: longitud mínima 12 (filtro `essential_hardening_admin_password_min_length`, nunca menos de 8) y comprobación en Have I Been Pwned: `GET https://api.pwnedpasswords.com/range/<5 primeros caracteres del SHA-1>` con `Add-Padding: true`. La comparación del resto del hash se hace localmente.
- Fallo abierto: si la API no responde o devuelve un error, la contraseña se acepta. No se bloquea a nadie por una caída externa.
- El error se registra con el código `eh_weak_password`.

### Cambio en 2.4 (anti-enumeración)
Desde 1.7.0 también se elimina el proveedor `users` del mapa del sitio de WordPress (`wp_sitemaps_add_provider`), que exponía la lista de autores en `/wp-sitemap-users-1.xml`.

## 3. Configuración en Base de Datos

El plugin almacena sus preferencias en una única entrada en `wp_options`:
- **Option Name:** `essential_hardening_settings`
- **Estructura:** Array asociativo serializado:
  ```php
  array(
      'disable_comments'          => true,
      'block_user_enumeration'    => true,
      'security_headers'          => true,
      'hide_wp_version'           => true,
      'disable_xmlrpc'            => true,
      'generic_login_errors'      => true,
      'disallow_file_edit'        => true,
      'query_firewall'            => true,
      'login_throttle'            => true,
      'protect_uploads'           => true,
      'clean_head_meta'           => true,
      'hide_author_body_class'    => true,
      'login_honeypot'            => true,
      'throttle_max_attempts'     => 5,
      'throttle_lockout_duration' => 900,
      'update_notifications'      => true,
      'update_notify_email'       => '',
      'admin_login_alerts'        => true,
      'security_event_log'        => true,
      'email_2fa_enabled'         => false,
      'hsts_enabled'              => false,
      'hsts_max_age'              => 2592000,
      'csp_report_only'           => false,
      'admin_change_alerts'       => true,
      'core_integrity_check'      => false,
      'lostpassword_protection'   => true,
      'disable_application_passwords' => false,
      'strong_admin_passwords'    => false,
  )
  ```

El registro de incidencias neutralizadas se guarda de forma separada en `eh_security_events_log` (máximo 25 elementos, con `autoload = false` para no impactar consultas habituales).

---

## 4. Instalación como Must-Use Plugin (Blindaje Total)

Si deseas que el plugin se ejecute de forma obligatoria en un servidor y que ningún administrador pueda desactivarlo desde el panel:

1. Crea la carpeta `wp-content/mu-plugins/` si no existe.
2. Copia `essential-hardening.php` directamente dentro de `wp-content/mu-plugins/` o crea un cargador:
   ```php
   <?php
   // wp-content/mu-plugins/load-hardening.php
   require_once WPMU_PLUGIN_DIR . '/essential-hardening/essential-hardening.php';
   ```
3. WordPress lo ejecutará automáticamente antes de los plugins estándar.

## 5. Filtros de Personalización

Se usan desde el tema hijo, un `mu-plugin` o un plugin propio. No hace falta editar este plugin.

| Filtro | Para qué sirve |
|--------|----------------|
| `essential_hardening_hsts_include_subdomains` | Devolver `true` para añadir `includeSubDomains` a HSTS (solo si todos los subdominios tienen HTTPS válido). |
| `essential_hardening_csp_policy` | Reemplazar la política CSP en modo solo-reporte. |
| `essential_hardening_core_integrity_ignore` | Lista de rutas relativas que la verificación de integridad ignora (por defecto `readme.html`, `license.txt`, `wp-config-sample.php`, `wp-admin/install.php`). |
| `essential_hardening_core_root` | Directorio raíz de WordPress a verificar, para instalaciones con el núcleo en otra carpeta. |
| `essential_hardening_core_checksums` | Proveer las sumas de control manualmente (`array`) o simular que no hay (`false`). Pensado para pruebas y entornos sin salida a internet. |
| `essential_hardening_lostpassword_max_per_hour` | Máximo de solicitudes de recuperación de contraseña por IP y hora (por defecto 5). |
| `essential_hardening_admin_password_min_length` | Longitud mínima de la contraseña de un administrador (por defecto 12, mínimo 8). |
| `essential_hardening_client_ip` | Resolver la IP real detrás de un proxy o CDN de confianza (ej. Cloudflare). Por defecto solo se usa `REMOTE_ADDR`, porque `X-Forwarded-For` se puede falsificar. |
| `essential_hardening_failed_login_delay` | Segundos de retardo tras un login fallido (por defecto 2, máximo 5). `0` lo desactiva. |
| `essential_hardening_waf_patterns` | Añadir o quitar expresiones regulares del Micro-WAF. |
| `essential_hardening_waf_skip` | Devolver `true` para no inspeccionar una petición concreta (recibe la URI). |

```php
// Cloudflare: confiar en CF-Connecting-IP solo si el sitio está detrás de Cloudflare
add_filter( 'essential_hardening_client_ip', function ( $ip ) {
	return ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $ip;
} );
```

Notas de comportamiento:

- El Micro-WAF inspecciona la URL y la query string, no el cuerpo de los POST.
- Mientras una IP está bloqueada, los intentos nuevos no extienden el bloqueo ni aplican retardo.
- El Micro-WAF registra un evento por IP y minuto, aunque bloquee más peticiones.
- El 2FA emite como máximo 5 códigos cada 10 minutos por usuario; los fallos de 2FA cuentan para el bloqueo por IP.

---

## 6. Historial de cambios

| Versión | Cambios |
|---------|---------|
| **1.7.0** | Protección de cuentas. Recuperación de contraseña limitada a 5 solicitudes por IP y hora y con la misma respuesta exista o no la cuenta (activada por defecto). Opción para desactivar las contraseñas de aplicación, que se saltan el 2FA. Opción de exigir a los administradores contraseñas de al menos 12 caracteres que no estén en filtraciones conocidas (Have I Been Pwned, k-anonimato). La anti-enumeración quita también el mapa del sitio de usuarios. Filtros `..._lostpassword_max_per_hour` y `..._admin_password_min_length`. 24 escudos en total. |
| **1.6.0** | Alertas por correo cuando se crea un Administrador o se promueve una cuenta a ese rol (activadas por defecto). Verificación diaria opcional de la integridad del núcleo de WordPress contra las sumas de control oficiales, con botón "Verificar ahora" y resultado en el panel. Nuevos filtros `essential_hardening_core_integrity_ignore`, `..._core_root` y `..._core_checksums`. 21 escudos en total. `uninstall.php` y la desactivación limpian el cron y el informe nuevos. |
| **1.5.0** (compatibilidad) | Declarado PHP 7.4 a 8.5. Las pruebas se ejecutaron con PHP 8.3, 8.4 y 8.5 sin avisos; la sintaxis se revisa de 7.4 a 8.5 en la integración continua. |
| **1.5.0** | HSTS opcional (duración configurable, solo por HTTPS) y Content-Security-Policy en modo solo-reporte. Ambos vienen desactivados. 19 escudos en total. |
| **1.4.2** | **Seguridad:** corregido un bypass del 2FA por correo (el parámetro `eh_2fa_verified` permitía saltarlo). Reglas de `.htaccess` de uploads compatibles con Apache 2.4 sin `mod_access_compat` (evita error 500) y migración automática del bloque antiguo. Límite de 5 códigos 2FA cada 10 minutos por usuario. Los fallos de 2FA cuentan para el bloqueo de IP. Los intentos durante un bloqueo ya no lo extienden. Honeypot sin retardo. Filtros `essential_hardening_client_ip`, `..._failed_login_delay`, `..._waf_patterns` y `..._waf_skip`. El Micro-WAF registra un evento por IP y minuto. Limpieza al desactivar (cron y `.htaccess`) y `uninstall.php`. `X-XSS-Protection: 0`. Pruebas automáticas en `tests/essential-hardening/`. |
| **1.4.1** | Captura del error fatal de `wp_mail` cuando `mail()` está deshabilitada en el hosting. |
| **1.4.0** | Alertas de login de administrador, 2FA por correo opcional y registro de eventos de seguridad. |
| **1.3.0** | Alertas de actualizaciones pendientes por correo. |
| **1.2.0** | Honeypot anti-bot en el login y rate limiting configurable. |
| **1.1.x** | Nuevos escudos (Micro-WAF, protección de uploads, limpieza de `<head>`) y Plugin Update Checker. |
