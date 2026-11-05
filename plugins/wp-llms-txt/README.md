# WP LLMs.txt Manager

Plugin ligero y sin dependencias externas para generar, editar y servir archivos `llms.txt` y `llms-full.txt` en WordPress según la especificación abierta de [llmstxt.org](https://llmstxt.org).

Diseñado para optimizar la indexación, contexto y comprensión semántica de tu sitio web por parte de modelos de lenguaje de gran tamaño (LLMs) y motores de respuesta por IA (ChatGPT, Claude, Perplexity, Cursor, Copilot, etc.).

---

## 🚀 Características Principales

- **Ruta Virtual Limpia:** Sirve `/llms.txt` y `/llms-full.txt` dinámicamente con cabeceras `Content-Type: text/plain; charset=utf-8` y `X-Robots-Tag: index, follow` sin requerir archivos estáticos en disco ni permisos especiales en el servidor.
- **Editor en el Administrador:** Panel simple y accesible en **Ajustes > LLMs.txt** para editar tu archivo Markdown en cualquier momento.
- **Autogenerador Inteligente en 1 Clic:** Detecta el nombre del sitio, descripción, páginas principales y entradas publicadas para crear un borrador inicial estructurado bajo el estándar oficial.
- **Soporte para `/llms-full.txt`:** Endpoint extendido opcional para documentación exhaustiva y catálogos de recursos.
- **Sincronización Física Opcional:** Si deseas servir el archivo de forma estática pura por el servidor web (Nginx/Apache), puedes activar la sincronización para que se escriba en la raíz de WordPress al guardar cambios.
- **Zero Bloat & Alto Rendimiento:** Un solo archivo PHP, sin dependencias de terceros y con caché HTTP amigable para agentes de IA.
- **Respuesta directa:** `/llms.txt` y `/llms-full.txt` responden `200` sin redirecciones a una barra final.
- **No toca archivos ajenos:** si ya existe un `llms.txt` en la raíz que no creó este plugin, no lo sobrescribe ni lo borra.
- **Borrador seguro:** el borrador autogenerado solo aparece en el editor; no se guarda hasta pulsar **Guardar Cambios**. No incluye entradas ni páginas protegidas con contraseña.

---

## 📋 Especificación Soportada

El plugin sigue el estándar propuesto por la comunidad y documentado en [llmstxt.org](https://llmstxt.org):

```markdown
# Nombre de la Web o Proyecto

> Breve descripción o propósito principal del sitio web.

Explicación detallada del contexto, servicios y enfoque.

## Páginas Principales
- [Inicio](https://ejemplo.com/): Portal principal.
- [Servicios](https://ejemplo.com/servicios/): Descripción de servicios ofrecidos.

## Artículos Destacados
- [Guía de Uso](https://ejemplo.com/guia/): Tutorial paso a paso.

## Opcional
- [Versión completa](https://ejemplo.com/llms-full.txt): Documentación extendida.
```

---

## 🛠️ Instalación y Activación

### Método 1: Estándar
1. Copia la carpeta `wp-llms-txt` a tu directorio `wp-content/plugins/`.
2. Dirígete a **Plugins > Plugins Instalados** en tu escritorio de WordPress.
3. Haz clic en **Activar**.

### Método 2: Enlace simbólico en desarrollo (Laragon / Windows)
```cmd
mklink /J "C:\laragon\www\pluginWordpress\wp-content\plugins\wp-llms-txt" "C:\laragon\www\pluginWordpress\plugins\wp-llms-txt"
```

---

## ⚙️ Uso

1. Ve a **Ajustes > LLMs.txt** en el menú de WordPress.
2. Si es tu primera vez, haz clic en **⚡ Autogenerar borrador desde el contenido del sitio** para poblar el editor con tus páginas y artículos actuales. El borrador **no se guarda solo**: revísalo y pulsa Guardar.
3. Modifica y personaliza el contenido Markdown según los recursos que desees priorizar para la IA.
4. Haz clic en **Guardar Cambios**.
5. Visita `https://tu-sitio.local/llms.txt` en tu navegador para verificar el resultado.

---

## 🗑️ Desinstalación

Al borrar el plugin desde WordPress se eliminan sus opciones y los archivos `llms.txt` / `llms-full.txt` **que él mismo creó** (solo si no fueron modificados a mano). Un archivo ajeno nunca se borra.

---

## 📝 Historial de cambios

Ver [docs/wp-llms-txt.md](../../docs/wp-llms-txt.md#6-historial-de-cambios).

---

## 📄 Licencia

Distribuido bajo licencia **GPL-2.0-or-later**.
