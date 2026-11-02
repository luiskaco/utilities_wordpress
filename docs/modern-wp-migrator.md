# Documentación Técnica: Modern WP Migrator

Herramienta completa, modernizada y sin restricciones para la migración, exportación e importación de sitios WordPress.

---

## 1. Visión General

**Modern WP Migrator** es una bifurcación optimizada para entornos modernos de desarrollo y producción:
- **Límite de subida de 500 GB:** Desbloquea la restricción artificial tradicional de 512 MB para importar backups `.wpress` de gran envergadura directamente desde el navegador.
- **Restauración nativa en 1 clic:** Permite restaurar copias de seguridad directamente desde la lista de backups en el panel de WordPress.
- **Compatibilidad verificada:** Probado exhaustivamente en **PHP 8.0, 8.1, 8.2 y 8.3+** sobre WordPress 6.x.
- **Sin telemetría ni publicidad:** Código limpio, sin avisos comerciales, llamadas de seguimiento externas ni botones bloqueados.
- **Procesamiento por fragmentos (Chunks de 512 KB):** Evita errores de agotamiento de memoria (`memory_limit`) o tiempo de ejecución en servidores compartidos.

---

## 2. Características Principales

| Característica | Detalle Técnico |
|---|---|
| **Formato de archivo** | Archivos comprimidos `.wpress` estándar |
| **Límite de subida** | 500 GB (prácticamente ilimitado) |
| **Búsqueda y reemplazo** | Reemplazo serializado automático de dominios y rutas |
| **Filtros de exclusión** | Permite omitir spam, revisiones, medios o plugins al exportar |
| **Restauración directa** | 1 clic desde la lista de respaldos |
| **Compatibilidad PHP** | PHP 7.4 hasta PHP 8.3+ (0 errores de sintaxis) |

---

## 3. Instalación y Uso

1. Copia la carpeta `modern-wp-migrator` en `wp-content/plugins/`.
2. Activa el plugin desde **Plugins > Plugins Instalados**.
3. En el menú lateral aparecerá la sección **Modern WP Migrator**:
   - **Exportar:** Genera un archivo `.wpress` con toda la base de datos, temas, plugins y subidas.
   - **Importar:** Arrastra y suelta un archivo `.wpress` para restaurar o migrar el sitio.
   - **Copias de seguridad:** Administra y restaura backups existentes con un solo clic.
