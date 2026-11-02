# Documentación Técnica: Essential Hardening & Security

Suite modular, ligera y sin dependencias para el endurecimiento y seguridad integral de sitios WordPress.

---

## 1. Visión General

El plugin **Essential Hardening & Security** está diseñado bajo los principios de máxima eficiencia y mínima sobrecarga:
- **Cero dependencias externas:** no requiere librerías pesadas, suites de análisis ni conexiones a servidores remotos.
- **Sin impacto en rendimiento:** ejecuta únicamente hooks nativos de WordPress en memoria con tiempo de ejecución menor a 1ms.
- **Portabilidad universal:** desacoplado por completo de cualquier tema o entorno específico. Funciona tanto como plugin estándar en `wp-content/plugins/` o como plugin obligatorio en `wp-content/mu-plugins/`.

---

## 2. Los 7 Módulos de Protección

### 2.1. Desactivación Total de Comentarios y Trackbacks
Ideal para sitios institucionales, corporativos, tiendas o blogs sin moderación activa donde los comentarios representan un vector de spam masivo e inyecciones de enlaces maliciosos:
- **Front-end:** Devuelve `false` en `comments_open` y `pings_open`, y un arreglo vacío en `comments_array`.
- **Tipos de Contenido:** Remueve el soporte (`comments`, `trackbacks`) en posts, páginas y CPTs registrados.
- **Protección de Endpoint:** Intercepta cualquier llamada POST directa hacia `wp-comments-post.php` y responde con **HTTP 403 Forbidden**.
- **REST API:** Suprime los endpoints `/wp/v2/comments` y `/wp/v2/comments/{id}`.
- **Feeds:** Bloquea feeds RSS y Atom de comentarios (`do_feed_rss2_comments`, `do_feed_atom_comments`).
- **Panel Administrativo:** Oculta el menú `edit-comments.php`, redirige accesos directos al escritorio y remueve las cajas de metadatos en editores.

### 2.2. Bloqueo de Enumeración de Usuarios (Anti-Reconnaissance)
Impide que atacantes y bots automáticos descubran los nombres de usuario válidos para ejecutar ataques de diccionario o fuerza bruta:
- **Escaneos por URL:** Intercepta peticiones del tipo `/?author=1`, `/?author=2` y las redirige con un **301 Permanente** a la portada.
- **REST API Pública:** Oculta el endpoint `/wp/v2/users` a visitantes anónimos, retornando un **HTTP 404 Not Found**. Los usuarios autenticados con permisos continúan teniendo acceso normal.

### 2.3. Cabeceras HTTP de Seguridad
Inyecta cabeceras defensivas estándar en la respuesta HTTP (`send_headers`):
- `X-Frame-Options: SAMEORIGIN`: Protege contra ataques de clickjacking impidiendo que el sitio sea embebido en iframes de terceros.
- `X-Content-Type-Options: nosniff`: Fuerza a los navegadores a respetar el tipo MIME declarado, evitando ejecución de código malicioso disfrazado.
- `X-XSS-Protection: 1; mode=block`: Activa el filtro defensivo XSS en navegadores compatibles.
- `Referrer-Policy: strict-origin-when-cross-origin`: Limita el envío de URLs de referencia a dominios externos.
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`: Desactiva permisos de hardware innecesarios.
- `X-Pingback`: Remueve la cabecera para mitigar ataques de amplificación.

### 2.4. Ocultamiento de Huellas del CMS
Dificulta el escaneo automatizado de vulnerabilidades basadas en versión:
- Retira la etiqueta `<meta name="generator" content="WordPress ...">` del `<head>` y de los feeds.
- Elimina el parámetro de consulta `?ver=X.X.X` de las hojas de estilo y scripts cargados en el front-end público.

### 2.5. Desactivación de XML-RPC
- Bloquea por completo la interfaz XML-RPC (`add_filter('xmlrpc_enabled', '__return_false')`), neutralizando ataques históricos de fuerza bruta y ataques DDoS de reflexión.

### 2.6. Mensajes de Login Genéricos
- Ofusca los mensajes de error en `wp-login.php` mostrando siempre: *"Usuario o contraseña incorrectos."*, evitando que un atacante determine si acertó con un nombre de usuario existente.

### 2.7. Bloqueo de Edición de Archivos PHP
- Define la constante `DISALLOW_FILE_EDIT = true` para deshabilitar los editores de temas y plugins dentro del panel de administración, mitigando inyecciones de código ante compromisos de sesión.

---

## 3. Configuración en Base de Datos

El plugin almacena sus preferencias en una única entrada en `wp_options`:
- **Option Name:** `essential_hardening_settings`
- **Estructura:** Array asociativo serializado con valores booleanos:
  ```php
  array(
      'disable_comments'       => true,
      'block_user_enumeration' => true,
      'security_headers'       => true,
      'hide_wp_version'        => true,
      'disable_xmlrpc'         => true,
      'generic_login_errors'   => true,
      'disallow_file_edit'     => true,
  )
  ```

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
