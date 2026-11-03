---
description: "Al terminar el día o una tarea — verifica docs, código y genera commit sugerido"
---

# Workflow: Cierre de Sesión

# Invocar con: /session-close

Al ejecutar este workflow seguir estos pasos EN ORDEN:

**PASO 1 — ACTUALIZAR DOCS (antes de mostrar el checklist)**
Revisar qué cambió en la sesión y actualizar físicamente cada doc relevante en `/docs`.
NO limitarse a reportar — escribir los cambios en el archivo correspondiente.
Si un doc tiene cambios pendientes marcados como `⚠️ pendiente`, resolverlos ahora.

**PASO 2 — EMITIR EL BLOQUE DE CIERRE**

---

## ✅ Cierre de Sesión

### Docs actualizados

> Si se usó /new-feature en esta sesión: `06_TASKS.md` es **obligatorio** —
> marcar la tarea como `completed` o actualizar su estado.

- [ ] 01_PROJECT_PRD.md  — [motivo o "no aplica"]
- [ ] 02_SDD.md          — [motivo o "no aplica"]
- [ ] 03_SYSTEM_SPEC.md  — [motivo o "no aplica"]
- [ ] 04_DATA_MODEL.md   — [motivo o "no aplica"]
- [ ] 05_ARCHITECTURE.md — [motivo o "no aplica"]
- [ ] 06_TASKS.md        — **verificar estado de tarea activa** (en progreso / completada) y **reescribir "Estado de traspaso"**: dónde quedó, próximo paso concreto, bloqueos
- [ ] 07_DECISIONS.md    — [motivo o "no aplica"]
- [ ] 08_CHANGELOG.md    — [motivo o "no aplica"]
- [ ] 09_TESTING.md      — [tests nuevos en la tabla de trazabilidad, o "no aplica"]
- [ ] 10_RUNBOOK.md      — [si cambió deploy, entornos o infra, o "no aplica"]

### Verificación de código

Ejecutar activamente antes de marcar como completado:

- [ ] Correr el build — `npm run build` / `python -m pytest` / equivalente
- [ ] Flujo completo probado manualmente — no solo que compile
- [ ] Consola limpia — cero errores ni warnings
- [ ] Edge cases del flujo considerados y probados
- [ ] Imports válidos, sin dependencias rotas

### Commit sugerido

`tipo(scope): descripción en imperativo`

---

**Reglas:**

- Doc actualizado en esta sesión → `[x] motivo`
- Doc no relevante para esta sesión → `[ ] no aplica`
- Doc que debía actualizarse y NO se pudo por falta de información → `⚠️ pendiente` + explicación de qué falta
- **PROHIBIDO** marcar como `⚠️ pendiente` si la información necesaria ya existe en la sesión — en ese caso actualizar el doc directamente
- Omitir el bloque solo en respuestas puramente informativas (sin cambios en código o docs)
