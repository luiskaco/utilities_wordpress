# AGENTS.md — Reglas del proyecto

Instrucciones para cualquier agente de IA que trabaje en este repo (estándar abierto [AGENTS.md](https://agents.md)). El proyecto puede continuarlo otra herramienta: estas reglas no dependen de ninguna.

---

## 0. Cómo Responder

Escribí como le explicarías algo a un colega que no estuvo mirando: claro, directo y en tono natural, de persona a persona. Sin relleno ni rodeos, pero con el contexto necesario para entender sin releer.

**Reglas de escritura:**

1. **Primero la respuesta, en lenguaje simple:** qué pasa y qué significa para el usuario. El cómo y el detalle técnico, después y solo si suman.
2. **Nada de nombres internos sin explicar.** Un ID de tarea, una variable, una función o un archivo aparece solo si el usuario lo necesita, y con una frase de qué es.
3. **Una idea por párrafo, frases cortas.** Sin paréntesis dentro de paréntesis ni condiciones encadenadas.
4. **Opciones: como mucho 2–3,** cada una con lo que gana el usuario, y una recomendada.
5. **Comandos que el usuario tiene que correr, en un bloque de código aparte** — nunca en medio de la frase.
6. **Una sola pregunta al final, fácil de contestar** (sí/no o elegir una opción). Las condiciones van antes, no dentro de la pregunta.
7. **Lo más corto que se entienda.** Una pregunta simple se contesta en 1–3 frases. Sin repetir lo que dijo el usuario, sin resumir paso a paso lo que se acaba de hacer y sin cerrar con ofrecimientos genéricos ("¿algo más?").

**Criterio propio — no des la razón por cortesía:**

- Antes de ejecutar lo que pide el usuario, evaluarlo de verdad: qué gana, qué cuesta y qué riesgo tiene. Si hay un problema o una opción mejor, decirlo primero, con el motivo, aunque no lo haya preguntado.
- Separar lo que se sabe de lo que se supone. Si algo no se verificó, decirlo.
- Revisar también lo propio: si aparece algo que contradice lo que se dijo antes, corregirse en voz alta.
- Sin desacuerdo inventado: si la idea es buena, decirlo en una línea y seguir.
- La decisión es del usuario: si insiste después de escuchar la objeción, se hace a su manera y no se repite la objeción.

**Formato de cierre** — al terminar una tarea:

1. **Primera línea:** el resultado en una frase ("Listo: …", o qué falta si quedó a medias).
2. **Hasta 3 viñetas** con lo que cambia para el usuario — impacto, no listas de archivos, rutas ni valores (colores, tokens, hashes).
3. **Revisión** en una línea, si hubo código (§ 8).
4. **Una sola pregunta** al final, solo si hace falta una decisión o acción del usuario.

Largo proporcional al cambio: un fix chico cierra en 2 líneas. Sin narrar el proceso ("primero revisé…, luego…"). Tablas solo para comparar opciones, no para informar. El detalle técnico, solo si el usuario lo pide.

---

## 1. Regla de Oro: Planificar Antes de Actuar

**NUNCA generar código o modificar archivos sin pasar primero por estos 3 pasos:**

1. **Analizar:** Desglosa el problema, identifica impactos colaterales, propone solución conceptualmente.
2. **Validar:** Detente y pregunta si hay ambigüedad en alguna de estas condiciones:
   - Más de una arquitectura válida con trade-offs distintos
   - La tarea afecta más de 3 archivos existentes
   - Hay decisiones irreversibles (migraciones, cambios de schema, eliminación de datos)
3. **Ejecutar:** Solo cuando la estrategia esté clara y aprobada.

---

## 2. Principios

- Sistemas seguros, escalables, mantenibles y listos para producción.
- Priorizar: claridad, modularidad, simplicidad, reutilización.
- Evitar: deuda técnica, hacks rápidos, sobreingeniería.
- Identificadores y nombres de archivo siempre en inglés (variables, funciones, tipos, clases, rutas). Comentarios y documentación en español. Respuestas en el idioma del usuario.

---

## 3. Stack Base

| Capa | Tecnología |
|------|-----------|
| Frontend | React / Next.js / Vue / Vanilla JS |
| Backend | Node.js / Python (FastAPI) |
| CMS | WordPress |
| Base de datos | PostgreSQL / MySQL / MongoDB / Supabase |
| Estilos | Tailwind CSS / CSS puro |
| Runtime | Node.js 20+ / Python 3.11+ |

**📌 Versiones actualizadas — regla obligatoria:**

Antes de escribir o sugerir código que use una librería, framework, SDK o CLI (React, Next.js, Prisma, Tailwind, FastAPI, etc.):

1. **No asumas** APIs, sintaxis ni versiones desde memoria — pueden estar desactualizadas.
2. **Consulta la documentación vigente** con Context7 MCP (`resolve-library-id` → `query-docs`) antes de generar el código. Si no está disponible, usa web search.
3. **Respeta el lockfile del proyecto:** revisa `package.json` / `requirements.txt` / `pyproject.toml` y su lockfile antes de proponer versiones o upgrades; no cambies versiones fijadas sin avisar.
4. **En instalaciones nuevas** (proyecto sin lockfile), usa la última versión **estable** compatible con el runtime de esta tabla — nunca fijes versiones obsoletas por costumbre.

---

## 4. Naming & Git

**Naming:** `kebab-case` archivos · `camelCase` variables · `PascalCase` clases · `UPPER_SNAKE_CASE` constantes · `/api/v1/recursos` endpoints

**Commits:** Conventional Commits — `feat` / `fix` / `refactor` / `docs` / `chore` / `perf`

**Tasks:** Cuando el usuario mencione modificaciones, cambios o nuevas funcionalidades — actualizar el archivo de tareas activo del proyecto (`06_TASKS.md` o equivalente) antes de codificar.

---

## 5. Documentación del Proyecto `/docs`

| Archivo | Propósito |
|---------|-----------|
| `01_PROJECT_PRD.md` | Requisitos de producto — `RF-xx`/`RNF-xx` con criterios de aceptación |
| `02_SDD.md` | Diseño del sistema |
| `03_SYSTEM_SPEC.md` | Especificaciones técnicas |
| `04_DATA_MODEL.md` | Esquemas y entidades |
| `05_ARCHITECTURE.md` | Decisiones arquitectónicas |
| `06_TASKS.md` | Backlog activo + estado de traspaso (lo primero que lee quien retoma el proyecto) |
| `07_DECISIONS.md` | ADRs |
| `08_CHANGELOG.md` | Historial de versiones |
| `09_TESTING.md` | Cómo correr los tests, estrategia y trazabilidad requisito → test |
| `10_RUNBOOK.md` | Entornos, deploy, rollback, monitoreo e incidentes — a más tardar en el primer deploy |
| `CONTEXT.md` | Glosario del dominio — sin numerar: es un glosario vivo, no un documento de fase (opcional, desde 3 términos) |

Actualizar el archivo correspondiente al completar cada tarea.

**⚠️ Excepción:** Si el proyecto se desvía de esta tabla, del stack de § 3 o de alguna regla, el desvío se documenta en la sección **Proyecto** de `AGENTS.md` (raíz del proyecto), que tiene prioridad sobre ellas.

---

## 6. Jerarquía de Reglas

1. Este archivo de memoria del proyecto — máxima prioridad
2. Skills/rules de convenciones (`security`, `architecture`, `ui-ux`, `seo`, `pwa`, `operations`, `cms`, `database`, `stack-conventions`) — workspace, override local
3. Comandos/workflows (`/grill`, `/new-feature`, `/bug-fix`, `/security-audit`, `/deploy-checklist`, `/session-close`, `/tools-reference`) — checklists operativos que se ejecutan en un momento puntual; cuando un comando resume una convención que también vive en una skill/rule, esa skill/rule es la fuente de verdad si hay discrepancia
4. Resto de skills según contexto del proyecto

---

## 7. Comandos Disponibles

Invocar con `/` si tu herramienta los expone como comandos; si no, ver **Mapa del proyecto** más abajo:

| Comando | Cuándo |
|---------|--------|
| `/grill` | Antes de `/new-feature`, cuando el plan todavía es difuso — la IA interroga hasta afilarlo |
| `/new-feature` | Antes de iniciar cualquier tarea nueva |
| `/bug-fix` | Cuando algo está roto — identifica causa raíz antes de codificar |
| `/session-close` | Al terminar el día o una tarea — actualiza docs y genera commit |
| `/security-audit` | Antes de subir features con auth, APIs o datos sensibles |
| `/deploy-checklist` | Antes de merge a main o deploy a producción |
| `/tools-reference` | Setup inicial o cuando necesites ver skills y herramientas disponibles |

---

## 8. Auto-revisión Interna

Aplicar solo en tareas que generen, modifiquen o revisen código — omitir en consultas informativas.

**⚠️ Si el usuario adjunta o menciona un informe de auditoría externo (revisión manual, otro agente, etc.) — el Inspector no ejecuta. La auditoría ya fue realizada. Solo implementar las correcciones indicadas.**

Antes de entregar cualquier código, revisar internamente con estas pasadas — **omitir las que no apliquen** a la tarea (ej. ⚙️ en un cambio solo visual):

- **🎨 Frontend:** presentación — mobile-first, 5 estados de UI, accesibilidad, estética
- **⚙️ Backend:** lógica, endpoints, datos — seguridad, modularidad, validación
- **🧪 Verificado:** qué se ejecutó de verdad — build, tests, flujo probado. Nunca dar por probado lo que no se corrió
- **📝 Docs:** `06_TASKS.md` (con el estado de traspaso), `08_CHANGELOG.md` y el ADR si hubo decisión — al día
- **🔍 Inspector:** audita contra las skills/rules de seguridad, ui-ux y arquitectura — si detecta problema crítico, bloquea entrega y corrige primero
- **⚠️ Riesgo:** solo si hubo algo irreversible — migración, borrado de datos, push o deploy a producción

**Reporte al usuario:** una sola línea al final del cierre (§ 0), en texto normal — nunca en bloque de código. Pasada sin observaciones: `✅`. 🧪 dice qué se ejecutó. Solo se explica, en pocas palabras, la pasada que encontró o corrigió algo:

> Revisión: 🎨 ✅ · ⚙️ ✅ · 🧪 build + tests · 📝 ✅ · 🔍 ✅

> Revisión: 🎨 ✅ · 🧪 sin verificar: no hay tests · 📝 falta CHANGELOG · 🔍 corregido: XSS en el formulario · ⚠️ push a main

**Uso de subagentes** — cada uno arranca sin contexto y vuelve a leer el proyecto, así que cuestan:

- Cambios chicos (pocos archivos, sin riesgo), directo, sin subagente.
- Uno por tarea grande, y reanudarlo si la herramienta lo permite, en vez de lanzar otro: conserva lo que ya leyó.
- A quien implementa, instrucciones exactas: los archivos y los puntos a resolver. A quien revisa, el alcance (el diff o la feature), no una lista cerrada de archivos: tiene que poder seguir el dato hasta donde llega.
- Revisiones una sola vez, al final. Un cambio chico lleva una sola revisión, salvo que toque login, pagos o datos sensibles: eso siempre pasa por la auditoría de seguridad.
- Build, tests y lint, con comandos, no con subagentes.
- Pedir informes breves: hallazgos y resultados, no explicaciones.

---

## Mapa del proyecto

Este proyecto se armó con el kit de **Antigravity** de AI Rules Hub, pero
cualquier agente puede continuarlo: todo lo que necesita está en el repo, en
archivos markdown planos.

| Qué | Dónde |
|---|---|
| Rules técnicas (§ 6, punto 2) | `.agents/rules/<regla>.md` — leer la que aplique antes de tocar esa área |
| Skills | `.agents/skills/` — una carpeta por skill, con su `SKILL.md` |
| Subagentes | `.agents/agents/<nombre>/agent.md` |
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
> Lo que se anote acá tiene prioridad sobre las reglas de arriba (§ 5).

- **Qué es:** toolkit de micro-plugins de WordPress portables (repo `utilities_wordpress`). Cada carpeta de `plugins/` es un plugin independiente que se copia a cualquier sitio. No es un sitio web: el WordPress de la raíz solo sirve para probarlos en local.
- **Stack real:** PHP 7.4 a 8.3+ sobre la API nativa de WordPress (5.8+). Sin Node, sin build y sin dependencias externas salvo las ya incluidas en `vendor/`. Local en Laragon.
- **Comandos:** no hay build ni tests automatizados. Verificar sintaxis con `php -l` sobre los archivos tocados y probar el plugin activándolo en el WordPress local (enlazado con `mklink /J`, ver `docs/DEVELOPMENT.md`). Publicar una versión: tag `<slug>-v<versión>`; GitHub Actions (`.github/workflows/release-plugins.yml`) arma el ZIP y el Release, y Plugin Update Checker lo ofrece como actualización.
- **Estructura:**
  - `plugins/essential-hardening/` — escudos de seguridad (WAF, rate limit, cabeceras, anti-enumeración).
  - `plugins/modern-wp-migrator/` — migración y backups; derivado de All-in-One WP Migration (librerías `lib/` de ServMask).
  - `plugins/wp-llms-txt/` — generador y endpoint de `/llms.txt` y `/llms-full.txt`.
  - `docs/` — una guía técnica por plugin y `DEVELOPMENT.md` (cómo agregar y publicar plugins).
  - El núcleo de WordPress, `wp-content/` y `wp-config.php` de la raíz no se versionan ni se tocan.
- **Documentación:** no usa la tabla de § 5. La documentación vive en `README.md` (catálogo de plugins), `docs/<plugin>.md` y el `README.md` de cada plugin; al cambiar un plugin se actualizan esos archivos y su `changelog`/versión.
- **Excepciones a las reglas:**
  - Las reglas de SEO no aplican: no es un sitio público. `wp-llms-txt` genera esos archivos para los sitios donde se instala y sigue el estándar de llmstxt.org.
  - Código de terceros vendorizado (`plugins/*/vendor/`, `plugins/modern-wp-migrator/lib/vendor/`): no se edita a mano.
  - Prefijos únicos o `final class` en todo: cada plugin convive con plugins ajenos. Nonces, capabilities, sanitizar entradas y escapar salidas son obligatorios (regla `security`, porque son plugins de seguridad y migración).
  - Al subir la versión de un plugin, cambiar a la vez la cabecera PHP y su constante `VERSION`.
