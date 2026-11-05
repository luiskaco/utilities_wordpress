# AGENTS.md — Proyecto

Instrucciones de este repo, compartidas por Claude Code y Antigravity (estándar abierto [AGENTS.md](https://agents.md)).

Las **reglas generales** (los § que se citan acá) no están en este archivo: cada herramienta las carga sola desde `~/.claude/CLAUDE.md` o `~/.gemini/GEMINI.md`. Si no tenés esos archivos o usás otra herramienta, leé `.agents/CORE.md` al iniciar la sesión.

---

## Mapa del proyecto

Este proyecto se armó con el kit de **Claude Code y Antigravity** de AI Rules Hub, pero
cualquier agente puede continuarlo: todo lo que necesita está en el repo, en
archivos markdown planos.

| Qué | Dónde |
|---|---|
| Rules técnicas (§ 6, punto 2) | `.agents/rules/<regla>.md` — leer la que aplique antes de tocar esa área |
| Skills | `.agents/skills/` — una carpeta por skill, con su `SKILL.md` |
| Subagentes | `.agents/agents/<nombre>/agent.md` en Antigravity, `.claude/agents/<nombre>.md` en Claude Code |
| Comandos / workflows (§ 7) | `.agents/skills/<comando>/SKILL.md` |
| Estado del proyecto | `docs/` — ver "Continuar el trabajo" |

### Continuar el trabajo

1. **Antes de empezar:** leer en `docs/06_TASKS.md` la sección **Estado de
   traspaso** (dónde quedó, próximo paso, bloqueos) y después
   `docs/07_DECISIONS.md` (lo ya decidido — no re-decidirlo). Si este
   proyecto se desvía de alguna regla, está en la sección **Proyecto** de abajo.
2. **Para verificar tu trabajo:** `docs/09_TESTING.md` dice cómo correr los
   tests y qué requisito (`RF-xx` de `docs/01_PROJECT_PRD.md`) cubre cada uno.
   Para desplegar u operar: `docs/10_RUNBOOK.md`.
3. **Comandos de § 7:** si tu herramienta no los expone, leé el archivo del
   workflow y seguí sus pasos a mano.
4. **Al cerrar una tarea:** actualizar `docs/06_TASKS.md` — incluido el Estado
   de traspaso — y el documento que corresponda según § 5.

---

## Proyecto

> Completar al iniciar el proyecto — es lo primero que lee un agente nuevo.
> Lo que se anote acá tiene prioridad sobre las reglas generales (§ 5).

- **Qué es:** toolkit de micro-plugins de WordPress portables (repo `utilities_wordpress`). Cada carpeta de `plugins/` es un plugin independiente que se copia a cualquier sitio. No es un sitio web: el WordPress de la raíz solo sirve para probarlos en local.
- **Stack real:** PHP 7.4 a 8.3+ sobre la API nativa de WordPress (5.8+). Sin Node, sin build y sin dependencias externas salvo las ya incluidas en `vendor/`. Local en Laragon.
- **Comandos:** no hay build. Tests de `essential-hardening`: `php tests/essential-hardening/run.php` (WordPress local activo; ver `docs/DEVELOPMENT.md`). Verificar sintaxis con `php -l` sobre los archivos tocados y probar el plugin activándolo en el WordPress local (enlazado con `mklink /J`, ver `docs/DEVELOPMENT.md`). Publicar una versión: tag `<slug>-v<versión>`; GitHub Actions (`.github/workflows/release-plugins.yml`) arma el ZIP y el Release, y Plugin Update Checker lo ofrece como actualización.
- **Estructura:**
  - `plugins/essential-hardening/` — escudos de seguridad (WAF, rate limit, cabeceras, anti-enumeración).
  - `plugins/modern-wp-migrator/` — migración y backups; derivado de All-in-One WP Migration (librerías `lib/` de ServMask).
  - `plugins/wp-llms-txt/` — generador y endpoint de `/llms.txt` y `/llms-full.txt`.
  - `docs/` — una guía técnica por plugin y `DEVELOPMENT.md` (cómo agregar y publicar plugins).
  - El núcleo de WordPress, `wp-content/` y `wp-config.php` de la raíz no se versionan ni se tocan.
- **Documentación:** no usa la tabla de § 5. La documentación vive en `README.md` (catálogo de plugins), `docs/<plugin>.md` y el `README.md` de cada plugin. **Ante cualquier cambio grande** (función nueva, cambio de comportamiento, corrección de seguridad, algo eliminado) se actualizan, antes de publicar: el README del plugin, `docs/<plugin>.md` con su tabla *Historial de cambios*, el catálogo del README raíz y la versión (cabecera y constante). Detalle en `docs/DEVELOPMENT.md`.
- **Excepciones a las reglas:**
  - Las reglas de SEO no aplican: no es un sitio público. `wp-llms-txt` genera esos archivos para los sitios donde se instala y sigue el estándar de llmstxt.org.
  - Código de terceros vendorizado (`plugins/*/vendor/`, `plugins/modern-wp-migrator/lib/vendor/`): no se edita a mano.
  - Prefijos únicos o `final class` en todo: cada plugin convive con plugins ajenos. Nonces, capabilities, sanitizar entradas y escapar salidas son obligatorios (regla `security`, porque son plugins de seguridad y migración).
  - Al subir la versión de un plugin, cambiar a la vez la cabecera PHP y su constante `VERSION`.
