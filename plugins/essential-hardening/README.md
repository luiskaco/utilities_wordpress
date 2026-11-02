# Essential Hardening & Security (WordPress Plugin)

Suite ligera, modular y sin dependencias para el endurecimiento (*hardening*) de cualquier sitio WordPress. Diseñada para proteger portales institucionales, blogs, tiendas o sitios corporativos con cero sobrecarga técnica.

---

## 🛡️ Protecciones Incluidas

1. **Desactivación Total de Comentarios & Trackbacks:**
   - Cierra comentarios en front-end en todos los posts y páginas.
   - Bloquea peticiones directas a `wp-comments-post.php` con respuesta **HTTP 403 Forbidden**.
   - Deshabilita endpoints de comentarios en la REST API (`/wp/v2/comments`).
   - Bloquea feeds RSS/Atom de comentarios.
   - Oculta menús, cajas de edición y nodos de la barra superior en `wp-admin`.

2. **Anti-Enumeración de Usuarios (Reconnaissance Defense):**
   - Bloquea escaneos de nombres de usuario mediante query string (`?author=1`, `?author=2`) redirigiendo a la portada.
   - Oculta el endpoint `/wp/v2/users` de la REST API a usuarios no autenticados (responde 404), impidiendo que bots descubran las cuentas para ataques de fuerza bruta.

3. **Cabeceras HTTP de Seguridad:**
   - `X-Frame-Options: SAMEORIGIN` (mitigación de clickjacking).
   - `X-Content-Type-Options: nosniff` (previene ejecución de tipos MIME confusos).
   - `X-XSS-Protection: 1; mode=block`.
   - `Referrer-Policy: strict-origin-when-cross-origin`.
   - `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
   - Supresión de cabecera `X-Pingback`.

4. **Ocultamiento de Huellas del CMS:**
   - Retira la etiqueta `generator` de WordPress del `<head>` y de los feeds.
   - Elimina el parámetro de versión (`?ver=X.X`) de hojas de estilo y scripts para visitantes públicos.

5. **Desactivación de XML-RPC:**
   - Bloquea la interfaz `xmlrpc.php` (`xmlrpc_enabled -> false`) neutralizando ataques DDoS de amplificación y fuerza bruta multicredencial.

6. **Mensajes de Login Genéricos:**
   - Muestra siempre *"Usuario o contraseña incorrectos"* sin revelar si el usuario existe o no en la base de datos.

7. **Bloqueo de Edición de Archivos en el Panel:**
   - Define `DISALLOW_FILE_EDIT` impidiendo la inyección o edición de código PHP desde el editor de temas/plugins del panel administrativo.

---

## 🚀 Instalación en Cualquier Proyecto WordPress

### Opción A: Como Plugin Estándar
1. Copia la carpeta `essential-hardening` dentro de `wp-content/plugins/`.
2. Ve a **Plugins > Plugins Instalados** en tu panel de WordPress.
3. Haz clic en **Activar**.
4. Puedes ajustar los escudos en **Ajustes > Seguridad Esencial**.

### Opción B: Como Plugin Obligatorio (*Must-Use*)
1. Copia el archivo `essential-hardening.php` o crea un cargador en `wp-content/mu-plugins/`.
2. Se ejecutará automáticamente sin necesidad de activación y ningún usuario podrá desactivarlo desde el panel.

---

## ⚙️ Compatibilidad

- **PHP:** 7.4 o superior (100% compatible con PHP 8.0, 8.1, 8.2 y 8.3).
- **WordPress:** 5.8 o superior.
- **Multisite:** Totalmente compatible.
- **Dependencias:** 0 (cero librerías externas ni consumo de recursos en segundo plano).
