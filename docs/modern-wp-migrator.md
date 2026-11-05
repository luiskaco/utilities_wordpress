# Documentación Técnica: Modern WP Migrator

Herramienta completa, modernizada y sin restricciones para la migración, exportación e importación de sitios WordPress.

---

## 1. Visión General

**Modern WP Migrator** es una bifurcación optimizada para entornos modernos de desarrollo y producción:
- **Límite de subida de 500 GB:** Desbloquea la restricción artificial tradicional de 512 MB para importar backups `.wpress` de gran envergadura directamente desde el navegador.
- **Restauración nativa en 1 clic:** Permite restaurar copias de seguridad directamente desde la lista de backups en el panel de WordPress.
- **Compatibilidad verificada:** exportación completa ejecutada con **PHP 8.3, 8.4 y 8.5** sobre WordPress 7.1 sin avisos ni errores; sintaxis revisada de PHP 7.4 a 8.5 en la integración continua.
- **Sin telemetría ni publicidad:** Código limpio, sin avisos comerciales, llamadas de seguimiento externas ni botones bloqueados. Desde 7.113.3 se eliminó también el formulario de *feedback* que enviaba el correo y el mensaje del usuario a servmask.com.
- **Procesamiento por fragmentos (Chunks de 512 KB):** Evita errores de agotamiento de memoria (`memory_limit`) o tiempo de ejecución en servidores compartidos.

---

## 2. Características Principales

| Característica | Detalle Técnico |
|---|---|
| **Interfaz Modernizada (2026 UI)** | Pestañas de navegación instantánea, drag & drop de alta visibilidad y tarjetas diagnósticas |
| **Formato de archivo** | Archivos comprimidos `.wpress` estándar |
| **Límite de subida** | 500 GB (prácticamente ilimitado) |
| **Búsqueda y reemplazo** | Reemplazo serializado automático de dominios y rutas |
| **Filtros de exclusión** | Permite omitir spam, revisiones, medios o plugins al exportar |
| **Restauración directa** | 1 clic desde la lista de respaldos |
| **Compatibilidad PHP** | PHP 7.4 hasta PHP 8.5 (0 errores de sintaxis) |

---

## 3. Instalación y Uso

1. Copia la carpeta `modern-wp-migrator` en `wp-content/plugins/`.
2. Activa el plugin desde **Plugins > Plugins Instalados**.
3. En el menú lateral aparecerá la sección **Modern WP Migrator**:
   - **Exportar:** Genera un archivo `.wpress` con toda la base de datos, temas, plugins y subidas.
   - **Importar:** Arrastra y suelta un archivo `.wpress` para restaurar o migrar el sitio.
   - **Copias de seguridad:** Administra y restaura backups existentes con un solo clic.

---

## 4. Compatibilidad con All-in-One WP Migration

Este plugin es un fork y conserva las clases `Ai1wm_*`, las constantes `AI1WM_*` y el dominio de traducción del original. **No pueden estar activos a la vez.** Desde 7.113.3, `modern-wp-migrator.php` comprueba al cargar si `AI1WM_PLUGIN_BASENAME` o `Ai1wm_Main_Controller` ya existen; si es así, no carga el resto del plugin y muestra un aviso de administrador, en lugar de provocar un error fatal por redeclaración.

Las correcciones de seguridad del código original **no llegan solas**: hay que revisarlas y traerlas a mano. El número `7.113.x` indica la base de la que partió el fork, no que esté al día con el original.

---

## 5. Historial de cambios

| Versión | Cambios |
|---------|---------|
| **7.113.4** | Soporte declarado para PHP 7.4 a 8.5: añade `Requires PHP` y `Requires at least` a la cabecera. La exportación completa se ejecutó con PHP 8.3, 8.4 y 8.5 sin avisos. Revisión de sintaxis automática en la integración continua. |
| **7.113.3** | Se elimina el formulario y el endpoint de *feedback* hacia servmask.com. Evita el error fatal si All-in-One WP Migration está activo. El actualizador deja de depender del namespace interno `v5p7` de PUC. |
| **7.113.2** | Corrección del actualizador para releases en monorepo. |
| **7.113.0 – 7.113.1** | Integración en el repositorio central, interfaz modernizada y Plugin Update Checker. |
