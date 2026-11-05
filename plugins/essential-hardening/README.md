# Essential Hardening & Security (WordPress Plugin)

Suite ultraligera, modular y sin dependencias para el endurecimiento (*hardening*) y protección perimetral de cualquier instalación WordPress. Diseñada para proteger portales institucionales, blogs, tiendas o sitios corporativos con cero sobrecarga técnica y sin tablas adicionales en base de datos.

- **Versión:** 1.5.0
- **Licencia:** GPL-2.0-or-later
- **Dependencias:** Cero (PHP nativo + Hooks de WordPress)

---

## 🛡️ Los 19 Escudos y Herramientas de Seguridad

### 1. Cortafuegos de Peticiones y Query Strings (Micro-WAF)
- Inspecciona en tiempo real las cadenas de consulta (`$_SERVER['QUERY_STRING']` y `$_SERVER['REQUEST_URI']`).
- Bloquea al instante (**HTTP 403 Forbidden**) patrones de ataque comunes:
  - Inyección SQL (`UNION SELECT`, `benchmark()`, `sleep()`).
  - Ejecución remota de código / PHP Obfuscation (`eval()`, `base64_decode()`, `gzinflate()`, `passthru()`, `shell_exec()`).
  - Recorrido de directorios y robo de archivos locales (`../../`, `/etc/passwd`, `wp-config.php`).
  - Cross-Site Scripting básico (`<script>`, `javascript:`).

### 2. Mitigación de Fuerza Bruta y Rate-Limiting Configurable (Login Throttle)
- Introduce un retardo disuasivo de **2 segundos** tras cualquier intento de inicio de sesión fallido, inutilizando ataques masivos por diccionario.
- Umbral configurable de intentos fallidos: **3 intentos (estricto)**, **5 intentos (recomendado)** o **10 intentos (permisivo)**.
- Tiempo de bloqueo configurable: **15 minutos**, **30 minutos**, **1 hora**, **4 horas** o **24 horas** de bloqueo temporal mediante *transients* en memoria.
- Libera automáticamente el contador tras un acceso autenticado exitoso.

### 3. Protección del Directorio de Subidas (`wp-content/uploads/`)
- Mantiene reglas defensivas en el archivo `.htaccess` de la carpeta `uploads/`.
- Deniega la ejecución directa de cualquier script ejecutable (`.php`, `.phtml`, `.php7`, `.phar`, `.inc`), anulando completamente la ejecución de webshells o troyanos subidos mediante vulnerabilidades de temas o plugins de terceros.

### 4. Anti-Enumeración de Usuarios (Reconnaissance Defense)
- Bloquea el sondeo de autores por query string (`?author=1`, `?author=2`), redirigiendo permanentemente (**HTTP 301**) a la portada.
- Oculta el endpoint `/wp/v2/users` de la REST API a usuarios no autenticados, evitando que bots y herramientas de escaneo (como WPScan) extraigan la lista de logins de administración.

### 5. Anonimización de Nombres de Usuario en `body_class`
- Filtra y elimina dinámicamente cualquier clase del tipo `author-{login}` del tag `<body>` en el HTML público.
- Evita que atacantes deduzcan usuarios leyendo el código fuente de los artículos o páginas.

### 6. Desactivación Total de Comentarios & Trackbacks
- Cierra comentarios en front-end en todos los posts y páginas.
- Bloquea peticiones directas a `wp-comments-post.php` con respuesta **HTTP 403 Forbidden**.
- Deshabilita endpoints de comentarios en la REST API (`/wp/v2/comments`).
- Bloquea feeds RSS/Atom de comentarios y limpia la interfaz de `wp-admin` (menús, cajas de edición y barra superior).

### 7. Limpieza de Metadatos y Enlaces Obsoletos en `<head>`
- Remueve enlaces innecesarios que delatan la arquitectura interna:
  - Really Simple Discovery (`rsd_link` / XML-RPC endpoint).
  - Manifiesto de Windows Live Writer (`wlwmanifest_link`).
  - Shortlinks redundantes (`wp_shortlink_wp_head`).
  - Enlaces de la REST API en el encabezado (`rest_output_link_wp_head` y `oembed`).

### 8. Ocultamiento de Huellas del CMS
- Retira la etiqueta `generator` de WordPress del `<head>` y de los feeds.
- Elimina el parámetro de versión (`?ver=X.X`) de hojas de estilo y scripts para visitantes públicos, dificultando el perfilado automatizado por atacantes.

### 9. Cabeceras HTTP de Seguridad
- Inyecta cabeceras defensivas estándar de la industria en cada respuesta HTTP:
  - `X-Frame-Options: SAMEORIGIN` (prevención de clickjacking).
  - `X-Content-Type-Options: nosniff` (previene confusión de tipos MIME).
  - `X-XSS-Protection: 0` (desactiva el filtro XSS obsoleto de navegadores antiguos).
  - `Referrer-Policy: strict-origin-when-cross-origin`.
  - `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
  - Supresión de la cabecera delatadora `X-Pingback`.

### 10. Desactivación de XML-RPC
- Bloquea la interfaz `xmlrpc.php` (`xmlrpc_enabled -> false`), neutralizando vectores históricos de ataques DDoS por amplificación y ataques de fuerza bruta multicredencial.

### 11. Mensajes de Login Genéricos
- Sustituye los mensajes de error en `/wp-login.php` por *"Usuario o contraseña incorrectos"*, sin revelar si el nombre de usuario existe en la base de datos.

### 12. Bloqueo de Edición de Archivos en el Panel
- Define `DISALLOW_FILE_EDIT = true`, impidiendo la modificación de código PHP desde el editor web de temas y plugins si una sesión de administrador llegase a verse comprometida.

### 13. Honeypot Anti-Bot en Pantalla de Login (Sin Captcha)
- Inyecta un campo trampa invisible en el formulario de acceso `/wp-login.php`.
- Los usuarios humanos no lo ven ni deben resolver acertijos visuales. Los bots automatizados que autocompletan formularios lo llenan y se les rechaza el login al instante.
- Cero dependencias: no requiere cuentas de terceros, APIs ni cookies de rastreo externas.

### 14. Alertas de Actualizaciones Pendientes y Completadas
- Monitorea periódicamente el estado de plugins, temas y el núcleo de WordPress para evitar intrusiones por componentes desactualizados.
- Envía un correo electrónico consolidado al administrador si detecta versiones con actualizaciones pendientes, con la lista detallada y enlace directo.
- Filtro inteligente anti-spam: envía solo un resumen consolidado diario evitando la saturación de correos que causan otros plugins como Wordfence.
- Enruta las notificaciones automáticas de WordPress cuando se completan auto-actualizaciones exitosas al email configurado.

### 15. Alertas de Inicio de Sesión de Administradores
- Envía una notificación inmediata por correo electrónico cada vez que un usuario con privilegios de Administrador (`manage_options`) inicia sesión en el sitio.
- Incluye nombre de usuario, fecha y hora local, dirección IP y el agente de usuario (navegador y sistema operativo).
- Permite detectar de inmediato accesos no autorizados o credenciales filtradas.

### 16. Doble Factor de Autenticación (2FA) por Correo Electrónico
- Sistema 2FA nativo y sin dependencias: genera un código numérico aleatorio de 6 dígitos con validez de 10 minutos enviado al correo del administrador tras verificar la contraseña.
- Bloquea la sesión al tercer intento fallido del código para mitigar ataques por adivinación.
- Incluye mecanismo de rescate de emergencia: si el servidor no puede enviar correos temporalmente, puede desactivarse añadiendo `define( 'EH_DISABLE_2FA', true );` en `wp-config.php`.
- **Deshabilitado por defecto** para activarlo voluntariamente según la infraestructura de correo de cada sitio.

### 17. Registro en Vivo de Ataques Neutralizados (Mini-Log de 25 Eventos)
- Panel de control visual en `wp-admin` que muestra los últimos 25 incidentes bloqueados en tiempo real.
- Registra bloqueos del Micro-WAF, disparos de la trampa honeypot, bloqueos por fuerza bruta, intentos fallidos de 2FA y sondeo de usuarios.
- Almacenamiento circular ultraligero en base de datos (`eh_security_events_log`) con `autoload = false`, garantizando cero impacto en el rendimiento o crecimiento de la base de datos.
- Incluye botón para vaciar el historial con confirmación interactiva.

---

### 18. HSTS (Forzar HTTPS) — opcional
- Envía `Strict-Transport-Security` solo en conexiones HTTPS, con la duración que elijas (5 minutos, 1 día, 30 días, 6 meses o 1 año).
- Viene **desactivado**. Actívalo solo con un certificado SSL válido y renovándose bien: si caduca con HSTS activo, los visitantes no podrán entrar hasta que se renueve.
- Recomendado: empezar con 5 minutos, comprobar que todo funciona y subir la duración.

### 19. CSP en modo solo-reporte — opcional
- Envía `Content-Security-Policy-Report-Only`: **no bloquea nada**. El navegador anota en su consola (F12) qué recursos incumplirían la política.
- Viene **desactivado**. Úsalo para ver el impacto antes de endurecer; la política se ajusta con el filtro `essential_hardening_csp_policy`.

## 🚀 Instalación en Cualquier Proyecto WordPress

### Opción A: Como Plugin Estándar
1. Copia la carpeta `essential-hardening` dentro de `wp-content/plugins/`.
2. Ve a **Plugins > Plugins Instalados** en tu panel de WordPress.
3. Haz clic en **Activar**.
4. Puedes ajustar o activar/desactivar cualquiera de los 19 escudos en **Ajustes > Seguridad Esencial**.

### Opción B: Como Plugin Obligatorio (*Must-Use*)
1. Copia el archivo `essential-hardening.php` o crea un cargador en `wp-content/mu-plugins/`.
2. Se ejecutará automáticamente sin necesidad de activación y ningún usuario podrá desactivarlo desde el panel.

---

## ⚙️ Compatibilidad

- **PHP:** 7.4 o superior (100% compatible con PHP 8.0, 8.1, 8.2 y 8.3).
- **WordPress:** 5.8 o superior (probado hasta WordPress 7.1).
- **Multisite:** Totalmente compatible.
- **Rendimiento:** 0 consultas pesadas, sin cron jobs innecesarios, sin telemetría ni publicidad.

---

## 📝 Historial de cambios

Ver [docs/essential-hardening.md](../../docs/essential-hardening.md#6-historial-de-cambios).
