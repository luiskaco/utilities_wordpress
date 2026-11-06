# Autenticación y sesiones

Antes de escribirlo a mano, usá la autenticación del framework o una librería mantenida.

## Contraseñas

- **Largo:** mínimo 15 caracteres si es el único factor; mínimo 8 solo si siempre va con MFA. Permitir al menos 64.
- **Sin** reglas de composición ni cambio periódico obligatorio. Pedir el cambio solo si hay indicios de compromiso.
- **Rechazar** contraseñas comunes o filtradas (Have I Been Pwned por rango de hash).
- **Permitir** pegar y usar gestores de contraseñas.
- **Hash:** Argon2id con al menos `m=19456` (19 MiB), `t=2`, `p=1`.
- **bcrypt** si Argon2id no está disponible: costo 12 o más. Solo procesa 72 bytes: limitar la entrada o aplicar antes un HMAC con pepper.
- **PBKDF2** solo si se exige FIPS: 600.000 iteraciones o más con HMAC-SHA-256.
- Rehashear al iniciar sesión cuando cambien los parámetros.

## Login y recuperación

- Misma respuesta y tiempo similar exista o no la cuenta, en login, registro y recuperación.
- Limitar intentos por cuenta y por IP, con espera creciente. Un bloqueo total permite dejar afuera a otra persona: preferir esperas o desbloqueo por correo.
- Token de recuperación aleatorio, de un solo uso, de vida corta (15 a 60 minutos) y guardado como hash.
- Al cambiar la contraseña: invalidar las demás sesiones y avisar por correo.
- Volver a pedir autenticación antes de cambiar correo, contraseña o MFA.

## MFA

- Preferir passkeys o WebAuthn, resistentes al phishing. TOTP es la alternativa común. SMS, solo como último recurso.
- Obligatorio en cuentas de administración.
- Códigos de recuperación de un solo uso y guardados como hash. Limitar también los intentos del segundo factor.

## Sesiones

- ID de al menos 128 bits generado con un generador criptográfico.
- Cookie con `HttpOnly`, `Secure`, `SameSite=Lax` o `Strict` y, si se puede, prefijo `__Host-` (sin `Domain` y con `Path=/`).
- Regenerar el ID al iniciar sesión y en cada cambio de privilegios.
- **Inactividad:** 15 a 30 minutos en bajo riesgo, 2 a 5 en alto valor. **Duración máxima:** 4 a 8 horas en uso de oficina.
- El logout invalida la sesión en el servidor. Debe estar visible en todas las páginas.

## JWT

- Si no hace falta, preferir sesiones en servidor: revocarlas es más simple.
- Lista fija de algoritmos permitidos. Rechazar `none`. Nunca tomar el algoritmo del token sin validarlo.
- Validar firma, `iss`, `aud` y `exp` en cada uso.
- Clave HMAC de al menos 256 bits aleatorios, o par de claves con rotación.
- Access token de 15 minutos o menos. Refresh token rotado en cada uso, y si se reutiliza uno viejo, revocar toda la familia.
- El contenido no está cifrado: sin datos sensibles.

## OAuth y OIDC

- Authorization Code con PKCE. No usar el flujo implícito.
- Validar `state`, y `nonce` en OIDC. Comparar el `redirect_uri` exacto, sin comodines.
- Pedir los permisos mínimos.

## Señales de alerta al revisar

- Mensajes distintos para "usuario no existe" y "contraseña incorrecta".
- Rutas sensibles fuera de la verificación de sesión.
- Tokens en `localStorage`, en la URL o en logs.
- Comparar tokens o firmas con `===` en vez de una comparación de tiempo constante.

**Fuentes:** NIST SP 800-63B rev. 4 · OWASP Cheat Sheets: Password Storage, Session Management, Authentication, Forgot Password · RFC 8725 (JWT) · RFC 9700 (OAuth) · OWASP ASVS 5.0
