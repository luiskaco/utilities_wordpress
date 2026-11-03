---
trigger: glob
globs: "*.tsx,*.ts,*.jsx,*.js,*.py,*.php,*.css"
description: Convenciones específicas por lenguaje/framework — aplica automáticamente según la extensión del archivo que se está editando
---

# Stack Conventions

### Convenciones por stack

**Next.js**

- App Router por defecto (no Pages Router salvo excepción justificada)
- Server Components por defecto — Client Components solo cuando se necesita interactividad
- Server Actions para mutaciones de formularios
- `use context7 to resolve next.js` antes de implementar patrones nuevos

**Node.js / API**

- Express o Fastify según el proyecto
- Validación con Zod en todos los endpoints
- `/api/v1/` como prefijo base siempre

**Python**

- FastAPI para APIs REST
- Pydantic para validación de schemas
- `use context7 to resolve fastapi` antes de implementar patrones nuevos

**WordPress**

- Aplicar reglas de `cms.md` — hardening, updates, 2FA obligatorio
- Headless (REST API / WPGraphQL) cuando se combina con Next.js
- `functions.php` con comentarios por sección, nunca monolítico

**React**

- Componentes funcionales con hooks únicamente
- Estado global: Zustand o Context API según complejidad
- Formularios: React Hook Form + Zod

**Vanilla JS**

- ES Modules nativos (`import`/`export`) — nunca `var`
- DOM manipulation con `querySelector` — nunca jQuery salvo que el proyecto ya lo use
- Eventos con `addEventListener` — nunca atributos `onclick` inline
- Organizar en módulos por responsabilidad (no un solo `main.js` con todo)
- `use context7 to resolve javascript` para APIs del browser que puedan haber cambiado

**Vue**

- Composition API (`<script setup>`) por defecto — Options API solo en código legacy
- Pinia para estado global — nunca Vuex en proyectos nuevos (Vuex solo si el proyecto ya lo usa)
- Componentes de un solo archivo (`.vue`) organizados por feature, no por tipo
- `defineProps`/`defineEmits` tipados con TypeScript
- `use context7 to resolve vue` antes de implementar patrones nuevos

**CSS Puro**

- Variables CSS (`--color-primary`) para todos los valores reutilizables
- Mobile-first con `min-width` media queries (nunca `max-width` primero)
- BEM como convención de naming: `.block__element--modifier`
- Nunca `!important` salvo casos excepcionales documentados
- `clamp()` para tipografía y espaciado fluido en lugar de breakpoints para cada tamaño
- `grid` y `flexbox` — nunca `float` para layouts