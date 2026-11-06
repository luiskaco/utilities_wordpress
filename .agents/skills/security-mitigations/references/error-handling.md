# Errores y condiciones excepcionales

Riesgo nuevo en OWASP 2025: la aplicación no evita, no detecta o responde mal a una situación inesperada.

## Fallar cerrado

- Si falla la verificación de permisos, de firma o de validación, se niega el acceso. Nunca seguir "por las dudas".
- Si falla algo a mitad de una operación, revertir la transacción completa y empezar de nuevo.
- Un servicio de autorización caído no significa "permitir".

## Capturar y responder

- Capturar los errores donde ocurren y resolverlos ahí cuando se pueda.
- Manejador global para los que se escapen. Sin rechazos de promesas ni excepciones sin atender.
- Mensaje genérico al cliente, con un `request_id`. El detalle queda en el log, sin secretos.
- Sin stack traces, consultas ni rutas internas en producción. Modo debug apagado.
- Respuestas de error uniformes: las diferencias revelan información, como si una cuenta existe.

## Limitar

- Timeout en toda llamada externa, consulta y job.
- Límites de frecuencia, tamaño, profundidad y paginación.
- Reintentos acotados, con espera creciente. Solo en operaciones idempotentes.
- Colas con reintentos máximos y cola de fallidos que alguien revise.

## Vigilar

- Monitorear errores repetidos: un patrón puede ser un ataque en curso.
- Agrupar errores idénticos por frecuencia, para que no llenen los logs.
- Probar con fallas reales: caída de la base, servicio externo lento, disco lleno, entrada malformada.

## Señales de alerta al revisar

- `catch` vacío, o que solo hace `console.log` y sigue.
- Un `try` alrededor del chequeo de permisos que continúa si falla.
- Dos escrituras relacionadas sin transacción.
- Recursos sin liberar en caso de error: conexiones, archivos, bloqueos.

**Fuentes:** OWASP Top 10:2025 A10 · OWASP Error Handling Cheat Sheet
