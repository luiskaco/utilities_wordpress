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
recorrer la lista acá.

---

## 🔒 Security Audit

### 1. OWASP Top 10

- [ ] **Injection** — queries parametrizadas / ORM; si hay `$queryRaw`/`$executeRaw` en Prisma, son tagged template (nunca `*Unsafe` con input de usuario)
- [ ] **Broken Access Control** — cada endpoint verifica permisos en el servidor
- [ ] **Cryptographic Failures** — passwords con bcrypt/Argon2id, HTTPS activo
- [ ] **Security Misconfiguration** — headers de seguridad configurados, stack traces ocultos
- [ ] **Vulnerable Dependencies** — `npm audit` o `pip audit` sin vulnerabilidades críticas
- [ ] **SSRF** — URLs externas validadas con allowlist
- [ ] **XSS** — outputs sanitizados, sin `innerHTML`/`dangerouslySetInnerHTML` con data del usuario
- [ ] **IDOR** — pertenencia del recurso validada dentro del query (`where`), `404` y no `403` cuando no pertenece al usuario
- [ ] **CORS** — sin `Access-Control-Allow-Origin: *` en endpoints con auth o datos sensibles

### 2. Autenticación

- [ ] Tokens en cookies `HttpOnly` + `Secure` — nunca `localStorage`
- [ ] Proyecto Next.js → NextAuth v5 configurado según `security.md § NextAuth v5` (no reimplementar JWT a mano)
- [ ] Proyecto con auth custom → refresh token rotation implementada según `security.md § Autenticación`
- [ ] Rate limiting activo en endpoints de auth (20 req/min)
- [ ] Logout invalida la sesión en el servidor

### 3. Secrets y Variables

- [ ] Sin credenciales hardcodeadas (`git grep -i "api_key\|password\|secret\|token"`)
- [ ] `.env.example` actualizado con variables nuevas
- [ ] API keys con scope mínimo

### 4. Datos Sensibles y Uploads

- [ ] Logs sin passwords, tokens ni PII
- [ ] Respuestas de error genéricas — sin stack traces al cliente
- [ ] Si hay uploads: tipo validado por magic bytes en el servidor, **no** por extensión ni `Content-Type` declarado — ver `security.md § File Uploads`

### Resultado

Si hay hallazgos — listarlos con severidad:

- 🔴 **Critical** — bloquea el deploy, corregir antes de subir
- 🟠 **High** — corregir en el próximo commit
- 🟡 **Medium** — agregar a `06_TASKS.md` como tarea pendiente

---

**Si todo está limpio:** proceder con `/deploy-checklist`
