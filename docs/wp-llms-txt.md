# Documentación Técnica: WP LLMs.txt Manager

## 1. Resumen Ejecutivo
`WP LLMs.txt Manager` es un micro-plugin para WordPress desarrollado bajo la arquitectura modular del toolkit `utilities_wordpress`. Su función es proporcionar un canal estándar de descubrimiento semántico para modelos de lenguaje masivo (LLMs) y sistemas de búsqueda generativa mediante la entrega y gestión de archivos `llms.txt` y `llms-full.txt`.

---

## 2. Decisiones de Arquitectura

### 2.1 Enrutamiento Virtual vs. Servidor Físico
- **Enfoque Primario (Virtual):** Emplea `add_rewrite_rule` acoplado al hook `template_redirect` de WordPress. Esto garantiza portabilidad total entre distintos tipos de hosting (compartido, VPS, Docker, Bedrock) sin depender de permisos de escritura en la carpeta raíz `ABSPATH`.
- **Mecanismo de Resiliencia:** Se implementa un detector en fallback analizando `$_SERVER['REQUEST_URI']`. Si el administrador no ha regenerado la estructura de enlaces permanentes (`flush_rewrite_rules`), las peticiones directas a `/llms.txt` o `/llms-full.txt` continúan respondiendo con código HTTP 200 y el MIME `text/plain; charset=utf-8`.
- **Sincronización Física (Opcional):** Permite escribir `llms.txt` y `llms-full.txt` directamente en disco para entornos de alto tráfico donde el servidor web (Nginx / Cloudflare / Apache) atiende archivos estáticos directamente antes de invocar a PHP.

### 2.2 Estándar de Formato
Se apega a la especificación oficial de [llmstxt.org](https://llmstxt.org):
- Encabezado `#` (H1) para el nombre del proyecto o sitio.
- Bloque de cita `>` para el resumen o tagline.
- Texto descriptivo general.
- Secciones `##` (H2) que agrupan enlaces en listas desordenadas `- [Título](URL): Descripción opcional`.

---

## 3. Seguridad y Sanitización

1. **Permisos de Administración:** Todas las acciones de actualización y generación automática están restringidas a la capacidad `manage_options`.
2. **Validación de Integridad:** Se aplican nonces nativos de WordPress (`check_admin_referer` y `wp_verify_nonce`).
3. **Preservación de Sintaxis Markdown:** No se utiliza `sanitize_textarea_field()` sobre el cuerpo del documento porque elimina sintaxis crítica de Markdown (como `>`, `#`, asteriscos o corchetes). En su lugar, se normalizan los saltos de línea y se eliminan caracteres nulos con `sanitize_markdown_text()`.
4. **Cabeceras HTTP de Entrega:**
   - `Content-Type: text/plain; charset=utf-8`
   - `X-Robots-Tag: index, follow`
   - `X-Content-Type-Options: nosniff`
   - `Cache-Control: public, max-age=3600, stale-while-revalidate=86400`

---

## 4. Opciones Almacenadas (`wp_options`)

Se utiliza una única clave serializada: `wp_llms_txt_options` conteniendo:
- `llms_txt` (string): Contenido Markdown principal para `/llms.txt`.
- `llms_full_txt` (string): Contenido Markdown extendido para `/llms-full.txt`.
- `sync_physical_file` (bool): Bandera para replicar en el disco físico.
- `enable_full_txt` (bool): Habilitador del endpoint extendido.
