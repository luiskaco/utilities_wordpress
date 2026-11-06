# Navegador: XSS, CSRF, CORS y cabeceras

## XSS

- Usar el escape del framework. Evitar `dangerouslySetInnerHTML`, `v-html`, `innerHTML` y `document.write`.
- Si hay que mostrar HTML del usuario, sanitizarlo con DOMPurify o una librería equivalente.
- Escapar según el contexto: HTML, atributo, URL y JavaScript son distintos. Validar que las URL sean `https:` para evitar `javascript:`.
- Con `postMessage`, verificar `origin` al recibir y nunca enviar con `*` si el mensaje tiene datos.

## CSP

Política estricta recomendada:

```
Content-Security-Policy:
  script-src 'nonce-{RANDOM}' 'strict-dynamic';
  object-src 'none';
  base-uri 'none';
  frame-ancestors 'none';
  form-action 'self'
```

- Nonce nuevo en cada respuesta. Con páginas estáticas, usar hash.
- Desplegar primero con `Content-Security-Policy-Report-Only`, revisar los reportes y después aplicar.
- `unsafe-inline` y `unsafe-eval` quitan casi toda la protección contra XSS.
- Es una segunda barrera: no reemplaza escapar la salida.

## CSRF

Aplica cuando la autenticación viaja en cookies. En orden:

1. Protección integrada del framework, si existe.
2. Si no: token sincronizador, o doble envío firmado cuando no hay estado en el servidor.
3. Como defensa adicional o alternativa: comprobar `Sec-Fetch-Site`, y `Origin` cuando no esté.
4. `SameSite=Lax` o `Strict` en las cookies. Ayuda, pero no alcanza solo.

Nunca cambiar datos con `GET`. En APIs JSON, exigir `Content-Type: application/json` y rechazar los tipos simples.

## CORS

- Lista explícita de orígenes. Nunca reflejar el `Origin` recibido sin compararlo.
- Nunca `*` con credenciales. No permitir el origen `null`.
- Con orígenes dinámicos, agregar `Vary: Origin`.
- CORS no protege el servidor: solo decide qué sitios pueden leer la respuesta. La autorización va aparte.

## Cookies

`Secure` y `HttpOnly` en toda cookie de sesión. `SameSite` siempre explícito. Sin `Domain` salvo que haga falta compartirla. Prefijo `__Host-` cuando se pueda.

## Cabeceras

| Cabecera | Valor |
|---|---|
| `Strict-Transport-Security` | `max-age=63072000; includeSubDomains` (`preload` solo con todos los subdominios en HTTPS) |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `X-Frame-Options` | `DENY` (junto con `frame-ancestors`) |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=()`, solo lo que no se use |
| `Cross-Origin-Opener-Policy` | `same-origin`, salvo que se usen ventanas de pago o login de terceros: probar antes |
| `Cross-Origin-Resource-Policy` | `same-site` |
| `Cross-Origin-Embedder-Policy` | `require-corp`, solo si se probó: puede bloquear recursos de terceros |
| `Cache-Control` | `no-store` en respuestas con datos sensibles |

Quitar `X-Powered-By`, `Expect-CT`, `Public-Key-Pins` y `X-XSS-Protection`. Dejar `Server` sin versión.

## Scripts de terceros

Cada script externo puede leer todo lo que hay en la página. Limitarlos, cargarlos después del consentimiento si corresponde y usar `integrity` (SRI) cuando la versión sea fija.

**Fuentes:** OWASP Cheat Sheets: Cross Site Scripting Prevention, Content Security Policy, Cross-Site Request Forgery Prevention, HTTP Headers · OWASP Top 10:2025 A02 y A05
