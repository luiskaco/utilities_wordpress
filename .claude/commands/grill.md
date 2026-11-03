---
description: "Interrogatorio estructurado para afilar un plan antes de codificar — la IA pregunta, vos respondés, y recién ahí se planifica"
---

# Workflow: Grill

# Invocar con: /grill

Le da forma concreta a la Regla de Oro (`GEMINI.md § 1` / `CORE.md § 1`):
"planificar antes de actuar". En vez de que el agente asuma lo que falta, lo
pregunta.

**Cuándo:** antes de `/new-feature` en tareas donde el plan todavía es difuso,
o cuando el usuario trae una idea en vez de un requerimiento. Para tareas
chicas y claras, saltear e ir directo a `/new-feature`.

---

## Cómo se ejecuta

**Una pregunta por turno.** No listas de diez preguntas — se pregunta, se
espera la respuesta, y la siguiente pregunta depende de esa respuesta. Un
cuestionario de golpe se contesta a medias.

**Buscar el desacuerdo, no la confirmación.** El objetivo es encontrar dónde
el plan se rompe, no validar que suena bien. Si la respuesta abre un hueco
nuevo, ir por ahí.

**Parar cuando ya no aporta.** Cuando dos o tres preguntas seguidas no
cambian nada del plan, cerrar. Interrogar de más es tan malo como no hacerlo.

## Qué hay que dejar resuelto

1. **El problema real.** Qué pasa hoy sin esto, y a quién le pasa. Si la
   respuesta es una solución ("necesito un botón que..."), volver atrás:
   cuál es el problema que ese botón resuelve.
2. **Los bordes.** Qué pasa con cero elementos, con el caso de error, con el
   usuario sin permisos, con la conexión caída. Los bordes son donde
   aparecen los requisitos que faltaban.
3. **Lo que queda afuera.** Explicitar qué NO entra en esta tarea. Sin esto,
   el alcance se infla mientras se codifica.
4. **Las decisiones irreversibles.** ¿Hay migración, cambio de schema,
   borrado de datos, o contrato de API público? Si sí, eso es un ADR antes
   de tocar código (`07_DECISIONS.md`).
5. **Cómo se sabe que está listo.** Criterio verificable, no "que funcione
   bien". Si no se puede verificar, no está definido.
6. **El vocabulario.** Si aparecen términos del dominio nuevos o usados con
   dos sentidos distintos, aplicar la skill `domain-modeling` y dejarlos en
   `docs/CONTEXT.md`.

## Cierre

Al terminar, escribir un resumen de 5-10 líneas con: problema, alcance,
fuera de alcance, criterio de terminado y decisiones abiertas. Ese resumen es
el insumo del paso siguiente.

---

**Recordatorio:** con el plan afilado, invocar `/new-feature` (o
`project-planning` si es un proyecto entero, no una feature).
