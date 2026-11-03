---
description: "si algo está roto — IA identifica causa raíz primero"
---

# Workflow: Bug Fix

# Invocar con: /bug-fix

Al ejecutar este workflow, seguir estos pasos ANTES de escribir código:

---

## 🐛 Bug Fix

### 1. Identificar la causa raíz

- ¿Cuál es el comportamiento esperado vs el actual?
- ¿En qué condiciones se reproduce el bug?
- ¿Cuándo apareció? ¿Qué cambió recientemente?
- ¿Afecta solo un caso o es sistémico?

**No escribir código hasta tener la causa raíz clara.**

En Claude Code, delegar este paso en el subagente `debugger`: lee logs y
trazas en su propio contexto y devuelve la causa raíz, la evidencia y el test
que reproduce el bug. Pasarle el síntoma y cómo reproducirlo.

### 2. Evaluar impacto

- [ ] ¿Cuántos archivos afecta el fix?
- [ ] ¿El fix puede romper otra funcionalidad?
- [ ] ¿Afecta el modelo de datos? → revisar `04_DATA_MODEL.md`
- [ ] ¿Afecta la seguridad? → invocar `/security-audit` después del fix

### 3. Actualizar 06_TASKS.md (obligatorio)

Agregar en `## In Progress`:

```
- [ ] fix: [descripción del bug] — causa: [causa raíz identificada]
```

### 4. Crear rama git

```bash
git checkout -b fix/nombre-descriptivo
```

### 5. Plan de fix

Describir en 2-3 pasos concretos cómo se va a resolver.
Esperar aprobación antes de proceder.

### 6. Después del fix — verificar

- [ ] El bug ya no se reproduce
- [ ] El fix no rompe funcionalidad existente
- [ ] Edge cases del mismo tipo fueron considerados
- [ ] Si el bug era de seguridad → invocar `/security-audit`

---

**Recordatorio:** Al terminar, invocar `/session-close`
