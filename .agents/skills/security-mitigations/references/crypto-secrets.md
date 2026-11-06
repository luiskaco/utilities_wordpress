# Criptografía y secretos

## Criptografía

- **No escribir criptografía propia.** Usar librerías estándar y mantenidas.
- **En tránsito:** TLS 1.2 o superior, preferir 1.3. HTTPS en todo, también entre servicios si cruzan una red no confiable.
- **En reposo:** clasificar los datos y cifrar los sensibles con AES-256-GCM o ChaCha20-Poly1305. No reutilizar el nonce con la misma clave.
- **Aleatoriedad para seguridad:** `crypto.randomBytes`, `secrets` o `random_bytes`. Nunca `Math.random()`, `rand()` ni la hora.
- **Comparar** tokens y firmas en tiempo constante (`timingSafeEqual`, `hash_equals`, `compare_digest`).
- **Guardar lo mínimo:** lo que no se guarda no se filtra. Datos de tarjeta: dejárselos al proveedor de pagos.

## Secretos

- Fuera del código y del repositorio, incluidos historial, imágenes de Docker, logs y mensajes de error.
- En producción, gestor de secretos del proveedor. Claves distintas por ambiente y con permisos mínimos.
- Nada con secretos en variables que lleguen al navegador (`NEXT_PUBLIC_`, `VITE_`, `PUBLIC_`).
- Escaneo automático con `gitleaks` o la protección de push de GitHub.
- La app falla con un error claro si falta una variable obligatoria.

## Si se filtró un secreto

1. Rotarlo primero. Borrarlo de git no alcanza: sigue en el historial y en los clones.
2. Revisar los logs del servicio para ver si se usó.
3. Recién después limpiar el historial, si hace falta, y anotar qué pasó.

## Señales de alerta al revisar

- MD5 o SHA1 para algo de seguridad. SHA-256 solo no sirve para contraseñas.
- Modo ECB, IV fijo o clave escrita en el código.
- Verificación TLS desactivada (`verify=False`, `rejectUnauthorized: false`).
- `.env` versionado o valores reales en `.env.example`.

**Fuentes:** OWASP Top 10:2025 A04 · OWASP Cheat Sheets: Cryptographic Storage, Secrets Management, Transport Layer Security · OWASP ASVS 5.0
