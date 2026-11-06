# Documentación Técnica: WP LLMs.txt Manager

## 1. Resumen Ejecutivo
`WP LLMs.txt Manager` es un micro-plugin para WordPress desarrollado bajo la arquitectura modular del toolkit `utilities_wordpress`. Su función es proporcionar un canal estándar de descubrimiento semántico para modelos de lenguaje masivo (LLMs) y sistemas de búsqueda generativa mediante la entrega y gestión de archivos `llms.txt` y `llms-full.txt`.

---

## 2. Decisiones de Arquitectura

### 2.1 Enrutamiento Virtual vs. Servidor Físico
- **Enfoque Primario (Virtual):** Emplea `add_rewrite_rule` acoplado al hook `template_redirect` de WordPress. Esto garantiza portabilidad total entre distintos tipos de hosting (compartido, VPS, Docker, Bedrock) sin depender de permisos de escritura en la carpeta raíz `ABSPATH`.
- **Mecanismo de Resiliencia:** Se implementa un detector en fallback analizando `$_SERVER['REQUEST_URI']`. Si el administrador no ha regenerado la estructura de enlaces permanentes (`flush_rewrite_rules`), las peticiones directas a `/llms.txt` o `/llms-full.txt` continúan respondiendo con código HTTP 200 y el MIME `text/plain; charset=utf-8`. Desde 1.0.2 el fallback solo atiende la **raíz del sitio** (respeta WordPress en subcarpeta); una ruta como `/foo/llms.txt` responde 404.
- **Sin redirección de barra final (desde 1.0.3):** el filtro `redirect_canonical` se desactiva para estas dos URLs. Antes WordPress respondía `301` a `/llms.txt/`; ahora responde `200` directo, como espera el estándar.
- **Sincronización Física (Opcional):** Permite escribir `llms.txt` y `llms-full.txt` directamente en disco para entornos de alto tráfico donde el servidor web (Nginx / Cloudflare / Apache) atiende archivos estáticos directamente antes de invocar a PHP.
- **Archivo físico ajeno (desde 1.1.0):** si existe un `llms.txt` o `llms-full.txt` real en la raíz, el servidor web lo entrega antes de que WordPress cargue y la ruta virtual no responde. El panel lo detecta y muestra un botón **Importar … al editor**. Importar solo carga el texto en el editor (sin guardar); luego se pulsa **Guardar Cambios** y se borra el archivo del hosting. El plugin nunca borra archivos que no creó.
- **Archivos gestionados (desde 1.0.2):** el plugin guarda el hash (`md5`) de cada archivo que escribe en la opción `wp_llms_txt_managed_files`. Solo sobrescribe o borra un archivo si su hash coincide con el que escribió él; si ya existía uno ajeno, no lo toca y avisa en el panel. Si se desactiva `llms-full.txt`, retira su copia física (solo si es suya).

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
3. **Borrador no persistente (desde 1.0.2):** "Autogenerar borrador" solo rellena el editor en memoria; no escribe en la base de datos hasta pulsar Guardar. El borrador excluye entradas y páginas con contraseña (`has_password => false`).
4. **Preservación de Sintaxis Markdown:** No se utiliza `sanitize_textarea_field()` sobre el cuerpo del documento porque elimina sintaxis crítica de Markdown (como `>`, `#`, asteriscos o corchetes). En su lugar, se normalizan los saltos de línea y se eliminan caracteres nulos con `sanitize_markdown_text()`.
5. **Cabeceras HTTP de Entrega:**
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

Clave aparte: `wp_llms_txt_managed_files` (array `nombre => md5`) con los archivos físicos que creó el plugin. `uninstall.php` elimina ambas claves y los archivos propios sin modificar.

---

## 5. Actualizaciones Automáticas vía GitHub (PUC)

El plugin incorpora `Plugin Update Checker (v5.7)` para recibir actualizaciones automáticas directamente desde GitHub:
- **Repositorio:** `luiskaco/utilities_wordpress`
- **Filtro de Versión:** `setReleaseVersionFilter('/^wp-llms-txt-/i')`
- **Asset Requerido:** `enableReleaseAssets('/^wp-llms-txt.*\.zip$/i', REQUIRE_RELEASE_ASSETS)`
- **Normalización de Versión:** Filtro sobre `request_info_result` y `request_update_result` para transformar `wp-llms-txt-vX.Y.Z` en `X.Y.Z` semver estándar de WordPress.

---

## 6. Historial de cambios

| Versión | Cambios |
|---------|---------|
| **1.1.0** | Nuevo: si en la raíz hay un `llms.txt` o `llms-full.txt` que el plugin no creó, el panel lo avisa y ofrece **Importar al editor**. Solo carga el contenido (máx. 2 MB, sin BOM ni bytes no UTF-8); no guarda ni borra el archivo, hay que guardarlo y borrar el archivo a mano. |
| **1.0.3** | `/llms.txt` y `/llms-full.txt` responden `200` directo, sin `301` a la barra final. |
| **1.0.2** | El borrador autogenerado ya no se guarda solo. No sobrescribe ni borra archivos `llms.txt` ajenos (hash de archivos gestionados). Excluye entradas con contraseña. El fallback sin permalinks solo atiende la raíz del sitio. Añade `uninstall.php`. El actualizador deja de depender del namespace interno `v5p7` de PUC. |
| **1.0.1** | Integración de Plugin Update Checker y release por GitHub Actions. |
| **1.0.0** | Versión inicial: endpoints virtuales, editor, autogenerador y sincronización física opcional. |
