---
trigger: model_decision
description: Activar al escribir o revisar autenticación, endpoints de API, validación de inputs, uploads, manejo de secrets, CORS, cookies de tracking/analytics, consentimiento o compliance de privacidad, instalación o actualización de dependencias, o cualquier código que reciba datos del cliente
---

# Security Rules

## Regla de Oro

> **Nunca confiar en el frontend.** Todo dato del cliente es potencialmente malicioso.
> Validar, sanitizar y autorizar en el servidor, siempre.

Este archivo tiene los principios y lo prohibido. El detalle por tema (sesiones, CSP, CSRF, CORS, SSRF, webhooks, IA, WordPress…) está en la skill `security-mitigations`: abrí solo el tema que toque.

---

## OWASP Top 10:2025 — Mitigación Obligatoria

| Riesgo | Mitigación |
|--------|-----------|
| A01 Broken Access Control | RBAC en el servidor, verificado en cada endpoint. IDOR: validar que el usuario puede acceder a ese recurso. SSRF: allowlist de URLs externas y bloquear IPs privadas (10.x, 172.16.x, 192.168.x, 169.254.x). |
| A02 Security Misconfiguration | Headers de seguridad, sin stack traces al cliente, sin credenciales ni configuración por defecto. |
| A03 Software Supply Chain Failures | Ver § Dependencias. |
| A04 Cryptographic Failures | HTTPS, Argon2id o bcrypt para contraseñas. Nunca MD5 ni SHA1 para contraseñas. |
| A05 Injection | Queries parametrizadas u ORM, nunca concatenar inputs. XSS: escapar la salida, CSP y nunca `innerHTML` con datos del usuario. |
| A06 Insecure Design | Antes de codificar login, pagos o datos sensibles, pensar cómo se abusaría y qué límites hacen falta. |
| A07 Authentication Failures | Ver § Autenticación. |
| A08 Software or Data Integrity Failures | Verificar firma o integridad de lo que se instala, se actualiza o llega por webhook. No deserializar datos no confiables. |
| A09 Security Logging and Alerting Failures | Registrar fallos de login, cambios de privilegios y acciones sensibles, y que eso genere una alerta, no solo un log. |
| A10 Mishandling of Exceptional Conditions | Manejador global de errores. Si algo falla a mitad de camino, negar el acceso y revertir la transacción completa. Límites y cuotas para evitar el agotamiento de recursos. |

---

## Autenticación

- **Hashing:** `Argon2id` (preferido) o `bcrypt` con cost factor ≥ 12. Nunca MD5/SHA1.
- **Passwords (NIST SP 800-63B rev. 4):** mínimo 15 caracteres si son el único factor; mínimo 8 solo si siempre van con MFA. Permitir al menos 64.
- **Sin** reglas de composición ni cambio periódico obligatorio. Rechazar contraseñas comunes o comprometidas (HaveIBeenPwned). Permitir pegar y usar gestores de contraseñas.
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

## File Uploads

- Validar el tipo en el servidor por su contenido (magic bytes), con allowlist. Nunca confiar en la extensión ni en el `Content-Type` declarado.
- Limitar el tamaño y generar el nombre en el servidor.
- Guardar fuera del directorio público o en un bucket privado. Lo subido nunca se ejecuta.
- Servirlo desde otro origen, o con `Content-Disposition: attachment` y `X-Content-Type-Options: nosniff`. SVG y HTML son contenido activo: no servirlos inline.

---

## Dependencias

`npm audit` solo detecta vulnerabilidades ya conocidas: no frena un paquete recién comprometido.

- **Lockfile** siempre commiteado. En CI, instalar desde él (`npm ci`, `pnpm install --frozen-lockfile`, `composer install`).
- **Auditoría en CI antes de deploy:** `npm audit --audit-level=high`, `pip-audit` o `composer audit`.
- **Esperar 7 días antes de instalar una versión recién publicada,** así hay tiempo de detectarla y retirarla: `min-release-age=7` en `.npmrc` (npm 11) o `minimumReleaseAge` en pnpm. Un parche de seguridad urgente se exceptúa a mano.
- **Scripts de instalación bloqueados:** `ignore-scripts=true` en `.npmrc`. Si un paquete los necesita, se autoriza ese paquete.
- **Sin dependencias desde git o URLs:** `allow-git=none` en `.npmrc`. Solo registros oficiales.
- **Antes de instalar una nueva:** confirmar que el nombre existe y es el correcto, sobre todo si lo sugirió una IA. Revisar mantenedor, descargas y último update.
- **Actualizar con criterio:** Dependabot o Renovate para enterarse, y elegir cada subida de versión. Quitar lo que ya no se usa.

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
