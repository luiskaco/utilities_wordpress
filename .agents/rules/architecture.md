---
trigger: model_decision
description: Activar al crear un módulo, archivo o feature nueva, o al decidir en qué carpeta/capa va un componente, servicio o endpoint
---

# Architecture Rules

## Estructura por Capas

```
UI → Lógica de negocio → Acceso a datos
```

Ninguna capa salta a otra. El frontend no contiene lógica de negocio.

## Modularidad Vertical (por dominio)

Un módulo = una carpeta en cada capa. Sin mezclar.

```
✅ app/[modulo]/          → rutas y páginas
   components/[modulo]/   → componentes
   lib/[modulo]/          → servicios, tipos, mocks

❌ components/
   lib/
   utils/                 → archivos mezclados de distintos módulos
```

- Compartido entre módulos → `components/ui/` o `lib/shared/`
- Comunicación entre módulos solo a través de interfaces/contratos — nunca importando internals
- Archivo nuevo siempre en la carpeta de su módulo

## Frontend Ligero

El frontend maneja solo:

- Presentación y estado de UI
- Llamadas a la API del backend

Nunca en el frontend:

- Lógica de negocio crítica
- Cálculos de precios, permisos o acceso
- Decisiones de autorización

## Servicios Stateless

Diseñar stateless desde el inicio. Cualquier instancia debe poder manejar cualquier request sin depender de estado local.

## Evitar Estrictamente

- Lógica de negocio en components de UI o HTTP controllers.
- Dependencias circulares entre módulos.
- God objects/services (una clase que hace todo).
- Configuración hardcodeada — todo por variables de entorno.
- Sobreingeniería en v1 — abstraer cuando haya 3+ casos de uso reales.

---

## API Design

- Versionar desde el inicio: `/api/v1/...`
- REST: recursos en plural, kebab-case (`/api/v1/blog-posts`)
- HTTP codes correctos: `200`, `201`, `400`, `401`, `403`, `404`, `422`, `429`, `500`
- Respuestas consistentes:

  ```json
  { "data": {}, "meta": {} }          // éxito
  { "error": "CODE", "message": "" }  // error
  { "errors": [{ "field": "", "message": "" }] }  // validación
  ```

- No romper contratos sin deprecation period + versión nueva.

---

## Checklist de Arquitectura

Antes de implementar un módulo o feature nuevo:

- [ ] ¿Está en la capa correcta? (UI / negocio / datos)
- [ ] ¿Introduce dependencias circulares?
- [ ] ¿El contrato de la API está definido antes del código?
- [ ] ¿El modelo de datos está en `04_DATA_MODEL.md`?
- [ ] ¿La decisión arquitectónica está en `07_DECISIONS.md` (ADR)?