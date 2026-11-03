---
name: security
description: "Activar al escribir o revisar autenticación, endpoints de API, validación de inputs, uploads, manejo de secrets, CORS, cookies de tracking/analytics, consentimiento o compliance de privacidad, o cualquier código que reciba datos del cliente"
---

# Security Rules

## Regla de Oro

> **Nunca confiar en el frontend.** Todo dato del cliente es potencialmente malicioso.
> Validar, sanitizar y autorizar en el servidor, siempre.

---

## OWASP Top 10 — Mitigación Obligatoria

| Vulnerabilidad | Mitigación |
|----------------|-----------|
| Injection | Queries parametrizadas / ORM. Nunca concatenar inputs. |
| Broken Access Control | RBAC en el servidor, verificar en cada endpoint. |
| Cryptographic Failures | HTTPS, bcrypt/Argon2id, no MD5/SHA1 para passwords. |
| Security Misconfiguration | Headers de seguridad, no exponer stack traces. |
| Vulnerable Dependencies | `npm audit` en CI, mantenedor activo. |
| SSRF | Allowlist URLs externas, bloquear IPs privadas (10.x, 172.16.x, 192.168.x, 169.254.x). |
| XSS | Sanitizar outputs, CSP, nunca `innerHTML` con data del usuario. |
| IDOR | Validar que el usuario tiene acceso al recurso solicitado por ID. |
| Security Logging | Loggear auth failures, cambios de privilegios, acciones sensibles. |

---

## Autenticación

- **Hashing:** `Argon2id` (preferido) o `bcrypt` con cost factor ≥ 12. Nunca MD5/SHA1.
- **Passwords:** longitud ≥ 8, verificar contra HaveIBeenPwned API.
- **Access token:** JWT ≤ 15 minutos de expiración.
- **Refresh token:** Rotación en cada uso — invalidar el anterior al emitir uno nuevo.
- **Almacenamiento:** Cookies `HttpOnly` + `Secure` + `SameSite=Strict`. Nunca `localStorage`.
- **Logout:** Invalidar refresh token en el servidor, no solo borrar cookie del cliente.
- **Session fixation:** Regenerar session ID post-autenticación.
- **Brute force:** 5 intentos → 15 min espera. 10 intentos → bloqueo de cuenta.
- **MFA:** Modelo de usuario con `mfa_enabled` y `mfa_secret` desde v1 (TOTP/WebAuthn).

---

## Rate Limiting

| Contexto | Límite |
|----------|--------|
| General (APIs) | 100 req/min por IP |
| Endpoints de auth | 20 req/min por IP |
| Password reset / sensibles | 5 req/min por IP |
| Uploads | 10 req/min por usuario |

---

## Headers de Seguridad (obligatorios)

```
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

**Contacto de seguridad:** publicar `/.well-known/security.txt` (RFC 9116) en sitios en producción, con al menos `Contact:` (email o URL donde reportar fallas) y `Expires:` (fecha, máximo un año). Renovar `Expires` antes de que venza.

---

## Validación de Requests

- Schema completo en cada request (body, params, query, headers) — usar Zod, Joi o Pydantic.
- Rechazar campos no esperados (no silent ignore).
- Validar `Content-Type`.
- Payload máximo: **10MB** por defecto, reducir donde no se necesite.

---

## Secrets

- Nunca en el código ni en el repositorio.
- `.env` + `.gitignore` + `.env.example` actualizado siempre.
- App debe fallar con error claro si falta variable requerida.
- API keys con scope mínimo. Keys separadas por ambiente (dev/staging/prod).

```bash
# .env.example
DATABASE_URL=postgres://user:password@localhost:5432/dbname
JWT_SECRET=your-secret-here-min-32-chars
JWT_EXPIRES_IN=15m
REFRESH_TOKEN_EXPIRES_IN=7d
```

---

## Dependencias

- `npm audit --audit-level=high` debe pasar en CI antes de deploy.
- Lockfiles siempre commiteados.
- Revisar mantenedor + descargas + último update antes de instalar una nueva.

---

## Privacidad y Consentimiento (GDPR/LOPD)

Aplica a cualquier página con scripts de tracking, cookies no esenciales, o
formularios que capturan datos personales — el caso típico de sitios de
marketing.

### Cookies y Scripts de Terceros

- Categorizar todo script/cookie: `necessary` (siempre activo), `analytics`,
  `marketing`, `preferences` — nunca una sola categoría "todo o nada".
- Scripts de `analytics`/`marketing` (GA4, Meta Pixel, ads de terceros)
  **bloqueados hasta consentimiento explícito** — nunca cargar y luego pedir
  permiso ("cargar primero, preguntar después" no es consentimiento válido).
- Consentimiento granular: el usuario puede aceptar `analytics` y rechazar
  `marketing` por separado, no un único botón "aceptar todo".
- Rechazar debe ser tan fácil como aceptar — mismo nivel de prominencia visual
  en el banner (nunca "Aceptar" grande + "Configurar"/"Rechazar" como link chico).
- Sin dark patterns: nunca pre-marcar checkboxes de consentimiento opcional,
  nunca reabrir el banner en loop hasta que acepte todo.
- Consentimiento registrado con timestamp + versión de política aceptada —
  auditable, no solo una cookie booleana `consent=true`.
- Revocable en cualquier momento: link visible ("Preferencias de cookies")
  en footer, no solo al primer visit.

### Datos Personales

- Base legal explícita antes de capturar cualquier dato personal (consentimiento,
  contrato, interés legítimo) — documentar cuál aplica a cada formulario.
- Formularios de captura (leads, newsletter, contacto) con checkbox de
  consentimiento separado del submit — nunca implícito por enviar el formulario.
- Link a política de privacidad visible en todo formulario que capture datos.
- Minimización: solo pedir los campos que el flujo realmente necesita.
- Derecho al olvido: endpoint o proceso documentado para borrar datos de un
  usuario a pedido — no solo soft-delete si el requerimiento es borrado real.
- Retención definida por tipo de dato — nunca "guardar para siempre por defecto".

### Prohibido — Privacidad

- Cargar scripts de analytics/marketing antes de consentimiento.
- Cookie wall que bloquea el acceso al sitio si no se acepta tracking (salvo
  que el servicio dependa estrictamente de eso — caso raro).
- Pre-marcar consentimiento opcional como aceptado por defecto.
- Combinar consentimiento de cookies con aceptación de Términos y Condiciones
  en un solo checkbox.

---

## Prohibido

- `localStorage`/`sessionStorage` para tokens o datos sensibles.
- Scripts inline sin nonce en CSP.
- Stack traces en respuestas de error al cliente.
- Decisiones de autorización solo en el frontend.
- `innerHTML` / `dangerouslySetInnerHTML` con data del usuario.
