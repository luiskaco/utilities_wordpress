---
name: ui-ux
description: "Activar al construir o revisar interfaces, componentes visuales, formularios, estados de carga/error, accesibilidad, dark mode o i18n/traducciones"
---

# UI/UX Rules

## Principios de Interfaz

- Mobile-first: diseñar desde 320px, escalar a 768px y 1280px.
- Feedback visual en menos de 100ms para toda interacción.
- Flujos frecuentes en máximo 3 pasos.
- Acciones destructivas siempre con confirmación o ventana de undo.
- Estado relevante (filtros, tabs, paginación) reflejado en URL.

### 5 Estados Obligatorios de UI

| Estado | Descripción |
|--------|-------------|
| `loading` | Spinner o skeleton mientras espera datos |
| `skeleton` | Estructura visual previa a los datos reales |
| `error` | Mensaje de error + acción de recuperación |
| `success` | Confirmación visual de acción completada |
| `empty` | Mensaje útil cuando no hay datos — nunca UI rota |

> Proyecto PWA: agregar `offline` como 6to estado obligatorio — ver `pwa.md`.

---

## Accesibilidad (WCAG 2.1 AA)

- Contraste texto/fondo: **4.5:1** normal, **3:1** texto grande.
- Todos los inputs con `<label>` o `aria-label`.
- Botones de solo icono con `aria-label` descriptivo.
- Iconos decorativos con `aria-hidden="true"`.
- Actualizaciones async (toasts, validaciones) con `aria-live="polite"`.
- HTML semántico antes de ARIA (`<button>`, `<a>`, `<label>`, `<table>`).
- Jerarquía `<h1>`–`<h6>` sin saltos.
- Nunca `outline: none` sin reemplazo — usar `focus-visible:ring-*`.
- Usar `:focus-visible` sobre `:focus`.
- `prefers-reduced-motion` respetado en todas las animaciones.

---

## Skeletons

- Representar la estructura real del contenido, no barras genéricas.
- CLS < 0.1 — imágenes con `width` y `height` explícitos siempre.
- Animar solo `opacity` y `transform` (compositor-friendly).

---

## Formularios

- `autocomplete` y `name` significativo en cada input.
- Tipo correcto: `email`, `tel`, `url`, `number` + `inputmode` apropiado.
- Nunca bloquear paste.
- Labels clickables (`htmlFor` o wrapping).
- `spellCheck={false}` en emails, códigos y usernames.
- Submit habilitado hasta que empieza el request — spinner durante request.
- Errores inline junto al campo; focus en primer error al hacer submit.
- Advertir antes de navegar con cambios sin guardar.

---

## Tipografía

- `…` no `...` | `"` `"` no `"` | `&nbsp;` en `10 MB`, `⌘ K`, nombres de marca.
- Estados de carga: `"Cargando…"`, `"Guardando…"`
- `font-variant-numeric: tabular-nums` en columnas numéricas.
- `text-wrap: balance` en títulos.
- No usar Inter para todo — elegir pares tipográficos según contexto.

---

## Dark Mode

- Tokens semánticos, nunca colores hardcodeados por tema:
  `--bg-primary`, `--bg-surface`, `--text-primary`, `--text-secondary`,
  `--border-default` — cada uno con valor light y dark, nunca `dark:bg-gray-900`
  disperso por todo el código.
- Contraste **4.5:1** normal / **3:1** texto grande en **ambos** temas — no
  asumir que dark mode "ya es accesible" por ser oscuro. Verificar cada par
  fondo/texto en los dos modos.
- Fuente de la preferencia, en orden:
  1. Selección explícita del usuario (persistida — cookie o `localStorage`,
     nunca solo en memoria).
  2. `prefers-color-scheme` del sistema si no hay selección explícita.
  3. Nunca forzar un tema sin dar opción de cambiarlo.
- Evitar negro puro (`#000`) sobre blanco puro (`#fff`) — usar grises con
  tinte (`#0a0a0a` / `#fafafa`) para reducir fatiga visual y halation.
- Imágenes/ilustraciones con fondo transparente o versión alternativa para
  dark — nunca un PNG con fondo blanco flotando sobre `--bg-primary` oscuro.
- Elevación (cards, modals, dropdowns) se comunica con **superficie más clara**
  en dark mode, no con `box-shadow` — las sombras casi no se perciben sobre
  fondos oscuros.
- Sin flash de tema incorrecto al cargar (FOUC): aplicar el tema antes del
  primer paint (script inline en `<head>` o SSR del tema desde cookie),
  nunca setear la clase de tema en un `useEffect` post-hidratación.
- `color-scheme: light dark` en `:root` para que scrollbars, inputs y
  controles nativos del navegador respeten el tema automáticamente.

---

## i18n

- Todo string visible al usuario vive en archivos de traducción
  (`messages/es.json`, `messages/pt.json`, `messages/en.json`…) — nunca texto
  hardcodeado en el componente, ni siquiera "temporalmente".
- Key namespacing por módulo, no un solo archivo plano:
  `checkout.errors.card_declined`, no `error_1` ni un `common.json` gigante.
- Pluralización con las reglas del formato (ICU MessageFormat o el sistema del
  framework — `next-intl`, `react-i18next`) — nunca `count === 1 ? "item" : "items"`
  a mano, no todos los idiomas pluralizan en 2 formas (ej. español "1 producto"
  / "2 productos" vs. otros idiomas con 3+ formas plurales).
- Fechas, números, moneda y unidades siempre con `Intl.*` parametrizado por
  locale — nunca formateo manual (`toLocaleDateString()` sin locale explícito
  hereda el del server, no el del usuario). Refuerza la regla ya existente en
  `§ Anti-patterns`.
- Ruteo de idioma explícito en la URL (`/es/`, `/pt/`) — nunca solo por
  cookie/header sin reflejarlo en la URL, rompe SEO multi-idioma y
  compartir enlaces.
- `hreflang` + `lang` en `<html>` correctos por página (ya cubierto en
  `seo.md § SEO Multi-idioma`) — cada versión de idioma con su propio
  `<link rel="alternate" hreflang="...">`.
- Layout a prueba de expansión de texto: alemán/español pueden ser 30-40% más
  largos que inglés — nunca `width` fijo en botones/labels que dependen de texto
  traducido, usar `min-width` + contenido flexible.
- Si el mercado lo requiere, `dir="rtl"` a nivel `<html>` y CSS lógico
  (`margin-inline-start` en vez de `margin-left`) en vez de overrides por idioma.
- Nunca concatenar strings traducidos para formar oraciones
  (`t("hello") + " " + name + t("welcome")`) — el orden gramatical cambia
  entre idiomas. Usar interpolación dentro de la key: `t("welcome", { name })`.

---

## Performance Frontend

- Lazy loading en imágenes below-fold (`loading="lazy"`).
- `fetchpriority="high"` en imágenes críticas above-fold.
- Code splitting en rutas y componentes pesados.
- Virtualizar listas > 50 items (`virtua` o `content-visibility: auto`).
- `<link rel="preload" as="font">` + `font-display: swap` en fuentes críticas.
- `<link rel="preconnect">` en dominios CDN/assets.
- Nunca `transition: all` — listar propiedades explícitamente.
- No leer layout en render (`getBoundingClientRect`, `offsetHeight`).

---

## Comandos de Auditoría (impeccable)

| Comando | Cuándo usarlo |
|---------|--------------|
| `/impeccable audit` | Antes de PR — a11y, performance, responsive |
| `/impeccable critique` | Revisión de jerarquía visual y UX |
| `/impeccable polish` | Pase final antes de ship |
| `/impeccable harden` | Errores, i18n, overflow, edge cases |
| `/impeccable bolder` | Diseño genérico o aburrido |
| `/impeccable quieter` | Diseño sobrecargado visualmente |
| `/impeccable typeset` | Tipografía necesita corrección |
| `/impeccable animate` | Agregar motion con propósito |

---

## Anti-patterns — Nunca

- `user-scalable=no` / `maximum-scale=1`
- `onPaste` + `preventDefault`
- `transition: all`
- `outline: none` sin reemplazo de focus
- `<div onClick>` en lugar de `<button>`
- Imágenes sin `width`/`height`
- Arrays > 50 sin virtualizar
- Inputs sin label
- Botones icono sin `aria-label`
- Fechas/números hardcodeados (usar `Intl.*`)
- Texto gris sobre fondos de color
- Cards dentro de cards
- `bounce`/`elastic` easing
- Purple-to-blue gradients por defecto
- Colores de tema hardcodeados (`dark:bg-gray-900` disperso) en vez de tokens semánticos
- Aplicar tema en `useEffect` post-hidratación (causa flash de tema incorrecto)

---

## Skills de Diseño — Cuándo Usar Cada Una

| Skill | Momento | Qué hace |
|-------|---------|----------|
| `frontend-design` | Al **iniciar** UI nueva, con o sin sistema de diseño ya definido (caso habitual en Orange Latam) | Dirección estética — tipografía, jerarquía visual, evita defaults genéricos; infiere un sistema si no existe |
| `impeccable` | Al **revisar** UI existente — requiere que `frontend-design` ya haya corrido antes | Audita, pule y critica con 23 comandos |
| `web-design-guidelines` | Al **auditar** estándares | Verifica a11y, forms, performance técnica |

**Nunca activar más de 2-3 a la vez.** Elegir según el momento de la tarea.

### Jerarquía si hay conflicto

1. `web-design-guidelines` — estándares técnicos (a11y, WCAG, forms)
2. `frontend-design` — sistema de diseño o dirección estética
3. `impeccable` — vocabulario y auditoría de diseño

### Instalación

Los comandos de instalación de cada skill viven en `tools-reference.md`
(workflow `/tools-reference`) — fuente de verdad única, no se duplican acá.

---

## SEO

El SEO técnico vive en su propia regla: **`seo.md`** — meta tags, Open Graph,
structured data, canonical, hreflang y Core Web Vitals como ranking factor.
Varias reglas de este archivo (`§ Accesibilidad`, `§ Skeletons`,
`§ Performance Frontend`, `§ i18n`) tienen impacto SEO directo y están
referenciadas desde ahí.
