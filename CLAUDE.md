# CLAUDE.md

Las reglas generales vienen de tu `~/.claude/CLAUDE.md` global; no se repiten acá para no cargarlas dos veces en cada turno.

Lo propio de este proyecto (stack real, estructura, excepciones) está en la sección **Proyecto** de `AGENTS.md`: leerla al iniciar la sesión. Ese archivo lo comparte Gemini/Antigravity, que trabaja en el mismo repo.

## Solo para Claude Code

- **Comandos** (`.claude/commands/`): `/grill`, `/new-feature`, `/bug-fix`, `/security-audit`, `/deploy-checklist`, `/session-close` y `/tools-reference`.
- **Skills** (`.claude/skills/`): las rules `architecture`, `cms`, `operations`, `security`, `stack-conventions` y `ui-ux`, más `api-security-best-practices`. Equivalen a `.agents/rules/` de Antigravity: si se cambia una, cambiar la otra.
- **Subagentes** (`.claude/agents/`): `planner`, `code-reviewer`, `ui-reviewer` (pantallas de administración de los plugins), `security-auditor`, `db-migration-reviewer`, `debugger`, `test-writer` y `web-researcher`.
