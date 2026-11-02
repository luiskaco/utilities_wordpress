# WordPress Utilities & Plugins Toolkit

Repositorio central de micro-plugins, utilidades y herramientas modulares para WordPress. Diseñadas para ser livianas, portables, sin dependencias externas y listas para ser implementadas en cualquier entorno de producción.

---

## 📦 Catálogo de Plugins

| Plugin | Categoría | Versión | Estado | Descripción |
|---|---|---|---|---|
| [**Essential Hardening & Security**](./plugins/essential-hardening/) | Seguridad & Hardening | `1.0.0` | `Producción` | Suite de 7 escudos: desactiva comentarios (403 directo), anti-enumeración de usuarios, cabeceras HTTP de seguridad, ofuscación de versiones y bloqueo de editor PHP. |

---

## 🎯 Filosofía del Toolkit

- **Zero Bloat:** Ningún plugin incluye librerías innecesarias, trackers ni peticiones a servidores externos.
- **Portabilidad Total:** Cada plugin en la carpeta `plugins/` es 100% independiente y puede copiarse directamente a cualquier instalación de WordPress.
- **Rendimiento Nativo:** Construidos sobre la API nativa de WordPress y los estándares de PHP modernos (PHP 7.4 a PHP 8.3+).
- **Flexibilidad:** Compatibles tanto como plugins tradicionales administrables desde el panel de WordPress o como plugins obligatorios (*Must-Use* en `mu-plugins`).

---

## 🚀 Cómo Usar Cualquier Plugin en tu Proyecto

### Método 1: Plugin Estándar
1. Descarga o copia la carpeta del plugin deseado desde `plugins/<nombre-del-plugin>/`.
2. Pégala en el directorio `wp-content/plugins/` de tu sitio WordPress.
3. Ve a **Plugins > Plugins Instalados** en tu escritorio de WordPress y haz clic en **Activar**.

### Método 2: Must-Use Plugin (`mu-plugins`)
1. Si necesitas que el plugin se ejecute de forma permanente sin opción de ser desactivado desde el panel, copia el archivo PHP principal en la carpeta `wp-content/mu-plugins/`.

---

## 📚 Documentación Técnica

- [Guía técnica de Essential Hardening & Security](./docs/essential-hardening.md)
- [Guía para agregar y desarrollar nuevos plugins](./docs/DEVELOPMENT.md)

---

## 📄 Licencia

Todos los plugins de este repositorio se distribuyen bajo la licencia **GPL-2.0-or-later**.
