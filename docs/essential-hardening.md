# Documentación Técnica: Essential Hardening & Security

Suite modular, ultraligera y sin dependencias externas para el endurecimiento y seguridad perimetral integral de sitios WordPress.

- **Versión:** 1.4.2
- **Licencia:** GPL-2.0-or-later
- **Compatibilidad:** WordPress 5.8+ | PHP 7.4 - 8.3+

---

## 1. Visión General

El plugin **Essential Hardening & Security** está diseñado bajo los principios de máxima eficiencia defensiva y mínima sobrecarga operativa:
- **Cero dependencias externas:** no requiere librerías pesadas, compiladores ni conexiones de telemetría a servidores remotos.
- **Sin impacto en rendimiento:** ejecuta únicamente hooks nativos de WordPress en memoria con tiempo de procesamiento sub-milisegundo.
- **Portabilidad universal:** desacoplado por completo de cualquier tema o hosting. Funciona tanto como plugin estándar en `wp-content/plugins/` o como plugin obligatorio en `wp-content/mu-plugins/`.
- **Persistencia limpia:** guarda un único registro en la tabla `wp_options` sin crear tablas adicionales.
- **Rescate de emergencia para 2FA:** configurable mediante `EH_DISABLE_2FA` en `wp-config.php`.

---

## 2. Los 17 Módulos de Protección y Auditoría

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
- `X-XSS-Protection: 1; mode=block`: Activa el filtro defensivo XSS en navegadores compatibles.
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
- **Detección inmediata:** Los bots automatizados que rastrean y autocompletan inputs lo llenan, disparando un bloqueo inmediato con **HTTP 403 Forbidden** y retardo disuasivo de 2 segundos.
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
