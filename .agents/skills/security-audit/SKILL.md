---
name: security-audit
description: "Usar antes de subir cualquier feature con auth, APIs o datos sensibles"
metadata:
  kind: workflow
---

# Workflow: Auditoría de Seguridad

# Invocar con: /security-audit

# Usar antes de subir cualquier feature con auth, APIs o datos sensibles

> Checklist de verificación rápida. El detalle, ejemplos de código y criterio completo de cada punto viven en `security.md` — es la fuente de verdad. Si algo acá parece desactualizado respecto a `security.md`, gana `security.md`.

Delegar la auditoría en el subagente de seguridad (`security-auditor` en
Claude Code, `security` en Antigravity), pasándole el diff o la feature a
revisar: recorre esta lista y el resto de `security.md` en su propio contexto
y devuelve los hallazgos con severidad. Si el tool no tiene ese subagente,
recorrer la lista acá. Para el detalle de cada punto, abrir el tema que corresponda
de la skill `security-mitigations`.

---

## 🔒 Security Audit

### 1. OWASP Top 10:2025

- [ ] **A01 Broken Access Control** — cada endpoint verifica permisos en el servidor. IDOR: pertenencia del recurso validada dentro del query (`where`), `404` y no `403` cuando no pertenece al usuario. SSRF: URLs externas validadas con allowlist. CORS: sin `Access-Control-Allow-Origin: *` en endpoints con auth o datos sensibles
- [ ] **A02 Security Misconfiguration** — headers de seguridad configurados, stack traces ocultos
- [ ] **A03 Software Supply Chain** — `npm audit`, `pip-audit` o `composer audit` sin críticas; lockfile commiteado; instalación según `security.md § Dependencias`
- [ ] **A04 Cryptographic Failures** — passwords con bcrypt/Argon2id, HTTPS activo
- [ ] **A05 Injection** — queries parametrizadas / ORM; si hay `$queryRaw`/`$executeRaw` en Prisma, son tagged template (nunca `*Unsafe` con input de usuario). XSS: outputs escapados, sin `innerHTML`/`dangerouslySetInnerHTML` con data del usuario
- [ ] **A06 Insecure Design** — abuso y límites pensados para login, pagos y datos sensibles
- [ ] **A08 Integrity Failures** — webhooks con firma verificada, sin deserializar datos no confiables
- [ ] **A09 Logging and Alerting** — fallos de login y acciones sensibles registrados y con alerta
- [ ] **A10 Exceptional Conditions** — si algo falla a mitad de camino, se niega el acceso y se revierte la transacción

### 2. Autenticación (A07)

- [ ] Tokens en cookies `HttpOnly` + `Secure` — nunca `localStorage`
- [ ] Con librería de auth → configurada según su documentación vigente (no reimplementar JWT a mano)
- [ ] Con auth propia → rotación de refresh token y contraseñas según `security.md § Autenticación`
- [ ] Rate limiting activo en endpoints de auth (20 req/min)
- [ ] Logout invalida la sesión en el servidor

### 3. Secrets y Variables

- [ ] Sin credenciales hardcodeadas (`git grep -i "api_key\|password\|secret\|token"`)
- [ ] `.env.example` actualizado con variables nuevas
- [ ] API keys con scope mínimo

### 4. Datos Sensibles y Uploads

- [ ] Logs sin passwords, tokens ni PII
- [ ] Respuestas de error genéricas — sin stack traces al cliente
- [ ] Si hay uploads: tipo validado por contenido en el servidor, **no** por extensión ni `Content-Type` declarado — ver `security.md § File Uploads`

### Resultado

Si hay hallazgos — listarlos con severidad:

- 🔴 **Critical** — bloquea el deploy, corregir antes de subir
- 🟠 **High** — corregir en el próximo commit
- 🟡 **Medium** — agregar a `06_TASKS.md` como tarea pendiente

---

**Si todo está limpio:** proceder con `/deploy-checklist`
