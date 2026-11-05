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

## 🧪 Pruebas

Las pruebas viven en `tests/<plugin>/` (fuera de `plugins/`, así que no entran en el ZIP de los releases). No hay PHPUnit: cada script carga el WordPress local de Laragon y deja la base de datos como estaba.

```bash
php tests/essential-hardening/run.php
```

- Hace falta activar el plugin en el WordPress local y tener PHP con `mysqli` (el `php.ini` de Laragon lo trae).
- Si `php` no está en el PATH: `C:\laragon\bin\php\php-8.3.x-Win32-vs16-x64\php.exe`.
- El script se niega a correr si el host no termina en `.test`, `.local` o `localhost`. Se cambia con `EH_TEST_HOST`.
- Cubre el 2FA (incluye la regresión del bypass `eh_2fa_verified`), el bloqueo de login, el honeypot y el `.htaccess` de uploads.
- Sale con código 1 si alguna prueba falla.
- Al corregir un bug de seguridad, añade primero la prueba que lo reproduce.

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

---

## 🚀 Publicación de Versiones y Actualizaciones Automáticas (PUC)

El repositorio usa **GitHub Actions** para empaquetar cada plugin en su propio archivo `.zip` e integrarlo con **Plugin Update Checker (PUC)**.

### Documentación obligatoria en cada cambio grande

Un cambio grande es una función nueva, un cambio de comportamiento visible, una corrección de seguridad o algo que se elimina. Antes de publicar:

- Actualiza el `README.md` del plugin si cambia lo que el usuario ve o configura.
- Actualiza `docs/<plugin>.md` (diseño, opciones almacenadas y la tabla **Historial de cambios**).
- Actualiza el catálogo en el `README.md` de la raíz (versión y descripción).
- Si hay una decisión de diseño que no se ve en el código, anótala en `docs/<plugin>.md`.

### Cómo publicar una nueva versión de un plugin:
1. Incrementa la versión en la cabecera PHP y constante `VERSION` de tu plugin. El workflow **rechaza** el release si la cabecera no coincide con el tag.
2. Crea una etiqueta git con el formato `<slug-plugin>-v<version>` y súbela:
   ```bash
   git tag wp-llms-txt-v1.0.1
   git push origin wp-llms-txt-v1.0.1
   ```
3. GitHub Actions empaquetará automáticamente la carpeta del plugin en `<slug-plugin>.zip` y creará un Release en GitHub.
4. Cualquier sitio WordPress con el plugin instalado detectará la actualización y permitirá actualizar en un clic desde el escritorio.

> Sube **un tag por vez**. Si se suben varios a la vez, GitHub puede no disparar el workflow; en ese caso se lanza a mano:

```bash
gh workflow run release-plugins.yml -f plugin=<slug-plugin> -f version=<version>
```

*También puedes disparar el empaquetado manualmente desde la pestaña **Actions > Release WordPress Plugin** en GitHub seleccionando el plugin y la versión.*
