---
name: new-feature
description: "Antes de iniciar cualquier tarea nueva — define el plan, crea la rama git y actualiza 06_TASKS.md"
metadata:
  kind: workflow
---

# Workflow: Nueva Feature

# Invocar con: /new-feature

Al ejecutar este workflow, seguir estos pasos antes de escribir código:

---

## 🚀 Inicio de Feature

### 1. Definir la tarea

- ¿Qué problema resuelve esta feature?
- ¿Qué archivos se van a modificar?
- ¿Hay decisiones arquitectónicas que tomar antes de empezar?

### 2. Actualizar 06_TASKS.md (obligatorio)

Si `/docs/06_TASKS.md` no existe — crearlo con esta estructura (la de la
plantilla del kit):

```markdown
# 06 — Tasks (Backlog activo)

## Estado de traspaso
- **Última actualización:** YYYY-MM-DD
- **Dónde quedó:**
- **Próximo paso concreto:**
- **Bloqueos:**
- **No tocar:**

## En progreso

## Pendiente

## Completado
```

Agregar la tarea nueva en `## En progreso`, con ID correlativo y el requisito
de `01_PROJECT_PRD.md` que cubre (si no hay requisito, decir por qué):

```
- [ ] **T-07 — [nombre de la tarea]** · `RF-03`
  - **Hecho cuando:** [criterio verificable — qué test pasa o qué se observa]
```

- [ ] `07_DECISIONS.md` — si hay decisión arquitectónica, documentarla como ADR antes de codificar

### 3. Crear rama git

```bash
git checkout -b feat/nombre-descriptivo
# o
git checkout -b fix/nombre-descriptivo
```

### 4. Verificar contexto

- [ ] ¿El stack del proyecto está definido en `GEMINI.md` / `CORE.md`? (Nombre y base de datos)
- [ ] ¿Hay dependencias que instalar antes de empezar?
- [ ] ¿Afecta el modelo de datos? → revisar `04_DATA_MODEL.md` primero

### 5. Plan de ejecución

Describir en 3-5 pasos concretos qué se va a implementar antes de escribir código.
Esperar aprobación del plan antes de proceder.

---

**Recordatorio:** Al terminar, invocar `/session-close`
