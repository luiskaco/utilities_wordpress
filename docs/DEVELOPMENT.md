# Guía de Desarrollo para utilities_wordpress

Este repositorio centraliza utilidades, micro-plugins y herramientas modulares para proyectos WordPress.

---

## 📁 Estructura del Repositorio

```
utilities_wordpress/
├── docs/                      # Documentación técnica general y por plugin
│   ├── essential-hardening.md
│   └── DEVELOPMENT.md
├── plugins/                   # Catálogo de plugins portables
│   └── essential-hardening/   # Plugin 1: Hardening & Seguridad
│       ├── essential-hardening.php
│       └── README.md
├── .gitignore                 # Aislamiento de WordPress core
└── README.md                  # Catálogo principal y guía de uso
```

---

## 🛠️ Cómo agregar un nuevo plugin al catálogo

1. **Crear carpeta del plugin:**
   Crea una subcarpeta en `plugins/<nombre-del-plugin>/` usando kebab-case.

2. **Cabecera estándar de WordPress:**
   El archivo principal debe llevar los metadatos completos:
   ```php
   <?php
   /**
    * Plugin Name:       Nombre del Plugin
    * Plugin URI:        https://github.com/luiskaco/utilities_wordpress
    * Description:       Descripción clara y concisa de la utilidad.
    * Version:           1.0.0
    * Requires at least: 5.8
    * Requires PHP:      7.4
    * Author:            Tu Nombre / Organización
    * License:           GPL-2.0-or-later
    * Text Domain:       nombre-del-plugin
    */
   ```

3. **Principios de Calidad:**
   - **Zero bloat:** Sin dependencias externas pesadas innecesarias.
   - **Idempotencia:** Evitar colisiones de nombres usando clases (`final class`) o prefijos únicos.
   - **Seguridad:** Validar nonces, sanitizar inputs (`sanitize_text_field`, etc.) y escapar outputs (`esc_html`, `esc_attr`).
   - **Documentación:** Cada plugin debe incluir su propio `README.md` con ejemplos de uso.

4. **Vincular en entorno local de pruebas (Laragon):**
   Para probar en vivo dentro de WordPress en Windows:
   ```cmd
   mklink /J "C:\laragon\www\pluginWordpress\wp-content\plugins\<nombre-plugin>" "C:\laragon\www\pluginWordpress\plugins\<nombre-plugin>"
   ```
