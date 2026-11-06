---
name: tools-reference
description: "Setup inicial o consulta de skills/herramientas disponibles y cuándo usar cada una"
metadata:
  kind: workflow
---

# Tools Reference

---

## Herramientas Disponibles

### Skills de Diseño UI/UX

Activar según el momento de la tarea — no todas a la vez:

| Skill | Cuándo activarla |
|-------|-----------------|
| `frontend-design` | Al **iniciar** UI nueva, con o sin sistema de diseño ya definido (caso habitual en Orange Latam) |
| `impeccable` (polish) | Al **revisar** UI existente o antes de hacer PR — requiere que `frontend-design` ya haya corrido antes |
| `web-design-guidelines` | Al **auditar** estándares técnicos (a11y, forms, performance) contra Web Interface Guidelines de Vercel |
| `vercel-react-best-practices` | Al **escribir o revisar** componentes React/Next.js — 70 reglas de performance (waterfalls, memoización, bundle) |
| `vercel-composition-patterns` | Al **refactorizar** componentes con muchos props booleanos o al diseñar APIs de componentes reutilizables (shadcn/ui) |

**Regla para el agente:** nunca activar más de 2-3 a la vez. Elegir según el momento.

- Construyendo UI nueva (con o sin sistema de diseño definido) → `frontend-design`
- Revisando algo existente → `impeccable` + `web-design-guidelines`
- Escribiendo/revisando componentes React → `vercel-react-best-practices`
- Componente con muchos props booleanos o que se repite en varios proyectos → `vercel-composition-patterns`

Instalación:

```bash
# frontend-design (Anthropic)
npx skills add https://github.com/anthropics/skills --skill frontend-design

# web-design-guidelines
npx skills add https://github.com/vercel-labs/agent-skills --skill web-design-guidelines

# vercel-react-best-practices
npx skills add https://github.com/vercel-labs/agent-skills --skill vercel-react-best-practices

# vercel-composition-patterns
npx skills add https://github.com/vercel-labs/agent-skills --skill vercel-composition-patterns

# impeccable: ver instrucciones en impeccable.style
```

---

### Otras skills del kit

Cada proyecto trae solo las de su preset; el resto se agrega a medida.

| Skill | Cuándo activarla |
|-------|------------------|
| `security-mitigations` | Al implementar o auditar login, permisos, datos de usuarios, APIs, uploads, dependencias o IA: abre solo el tema que toca |
| `prelaunch-compliance-audit` | Primer lanzamiento, o al agregar login, pagos, formularios con datos personales o correo promocional |
| `api-security-best-practices` | Al diseñar o revisar una API |
| `seo-audit` | Para diagnosticar problemas de SEO en un sitio público |
| `ai-seo` | Para que el contenido aparezca citado en respuestas de IA, o al armar `llms.txt` |
| `error-handling-patterns` | Al diseñar el manejo de errores |
| `fastapi` | En proyectos con FastAPI |
| `prisma-database-setup` | Al iniciar un proyecto con Prisma o al cambiar su schema |
| `wordpress-block-theming` | En temas de WordPress basados en bloques |
| `brainstorming` | Para validar una idea antes de planificarla |
| `project-planning` | Proyecto nuevo o iniciativa grande: lleva una idea validada a los documentos de `/docs` |

---

### Subagentes

Corren en su propio contexto y cuestan tokens: usarlos para tareas grandes, no para cambios chicos.

| Para qué | Claude Code | Antigravity |
|----------|-------------|-------------|
| Planificar tareas grandes | `planner` | — |
| Revisar un diff | `code-reviewer` | `auditor` |
| Revisar interfaz | `ui-reviewer` | `frontend` |
| Auditar seguridad | `security-auditor` | `security` |
| Revisar migraciones | `db-migration-reviewer` | — |
| Encontrar la causa de un bug | `debugger` | — |
| Escribir tests | `test-writer` | — |
| Implementar en paralelo | — | `backend`, `frontend` |
| Investigar en internet | `web-researcher` | `web-researcher` |
| Servidores por SSH (opcionales) | `server-inspector`, `server-operator` | `server-inspector`, `server-operator` |

En Antigravity hay que pedirlos con palabras, no con `@`.

---

### Skill de dominio

| Skill | Cuándo activarla |
|-------|------------------|
| `domain-modeling` | Al aparecer un término del dominio nuevo o ambiguo, al detectar que la conversación y el código le dan sentidos distintos a la misma palabra, o al nombrar entidades, tipos y endpoints |

No compite con las de diseño — es transversal y de bajo costo, así que no
cuenta para el límite de 2-3 skills activas. Mantiene el glosario
`docs/CONTEXT.md`: lenguaje del negocio, no esquema técnico (eso es
`04_DATA_MODEL.md`) ni decisiones con trade-offs (eso es `07_DECISIONS.md`).

---

### MCP: Context7

Context7 está instalado como MCP. Provee documentación actualizada de librerías en tiempo real.

**Usar siempre antes de implementar con cualquier librería** — evita usar APIs desactualizadas.

Cuándo usarlo:

- Al trabajar con métodos o APIs que pueden haber cambiado entre versiones
- Al implementar patrones complejos (auth, SSR, server actions, migrations, etc.)
- Ante cualquier duda sobre la API correcta de una librería

Formato:

```
use context7 to resolve <librería> — <query específica>
```

Ejemplos:

```
use context7 to resolve next.js — server actions with form validation
use context7 to resolve prisma — upsert with nested relations
use context7 to resolve tailwindcss — responsive grid v4
use context7 to resolve react-query — optimistic updates
```

> El plan gratuito tiene un límite mensual. Usar con criterio — solo cuando la
> documentación es necesaria, no en cada línea de código.