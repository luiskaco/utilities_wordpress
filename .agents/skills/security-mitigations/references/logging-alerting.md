# Logs, alertas e incidentes

Un log que nadie mira no detecta nada: lo importante tiene que generar una alerta.

## Qué registrar

- Login correcto y fallido, logout, cambios de contraseña y de MFA.
- Accesos denegados y cambios de rol o permisos.
- Acciones sensibles: exportar datos, borrar, pagos, cambios de configuración.
- Fallos de validación repetidos, firmas de webhook inválidas y límites superados.

Cada evento con: fecha en UTC, quién, desde dónde, qué acción, sobre qué recurso, resultado y `request_id`.

## Qué no registrar

Contraseñas, tokens, cookies, claves, tarjetas ni datos personales en claro. Enmascararlos en el logger, no en cada llamada.

## Protección

- Formato estructurado (JSON). Los saltos de línea del usuario quedan escapados, para que no pueda inventar líneas.
- Enviarlos fuera del servidor de la aplicación. Quien entra al servidor no debe poder borrarlos.
- Solo lectura para quien no los administra, y retención definida por tipo de dato.

## Qué alertar

- Picos de login fallido, por IP o sobre muchas cuentas.
- Login de administración desde un lugar o dispositivo nuevo.
- Muchos `403` o `404` seguidos sobre IDs distintos.
- Aumento de errores `5xx`, o exportaciones y borrados masivos.
- Probar las alertas de vez en cuando: una alerta rota no avisa.

## Ante un incidente

Tener escrito en `docs/10_RUNBOOK.md`:

1. Quién decide y cómo se avisa.
2. Cómo contener: revocar sesiones, rotar claves, cortar el acceso.
3. Cómo conservar evidencia antes de limpiar.
4. Cuándo hay que avisar a usuarios o autoridades: depende del país (ver `prelaunch-compliance-audit`).

**Fuentes:** OWASP Top 10:2025 A09 · OWASP Cheat Sheets: Logging, Logging Vocabulary · OWASP ASVS 5.0
