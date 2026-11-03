---
trigger: model_decision
description: Activar al trabajar con WordPress, Drupal, o un CMS headless (Sanity, Contentful, Strapi) — hardening, API keys, webhooks
---

# CMS Rules

---

## CMS Tradicional (WordPress, Drupal)

> Las reglas de arquitectura en capas no aplican aquí.
> Aplicar seguridad via configuración, no código.

### Updates

- Updates automáticos: core + plugins de seguridad siempre en última versión.
- Plugins: mantenedor activo, +10k instalaciones, último update < 6 meses.
- Auditoría mensual — desinstalar plugins que no se usan.
- Herramientas: WPScan (WordPress), Drupal Security Advisories.

### Acceso Admin

- Usuario admin nunca llamado "admin", "administrator" o "root".
- 2FA obligatorio en todas las cuentas con acceso al panel.
- Panel admin protegido con IP allowlist o autenticación HTTP adicional.
- Rate limiting en login + bloqueo tras 5 intentos fallidos.

### Hardening

- Deshabilitar XML-RPC si no se usa.
- `DISALLOW_FILE_EDIT = true` en WordPress.
- Ocultar versión del CMS en headers y metadatos.
- Headers de seguridad via `.htaccess` o `nginx.conf`.
- Permisos: directorios `755`, archivos `644`, config `600`.

### Backups

- Automáticos diarios: base de datos + archivos.
- Retención mínima 30 días, almacenados fuera del servidor (S3 u otro).
- Verificar restore funcional al menos una vez al mes.

---

## CMS Headless (Sanity, Contentful, Strapi)

> El CMS headless es un servicio externo privilegiado.
> Aplican todas las reglas de security.md más estas específicas.

### API Keys — Una key por contexto

| Contexto | Permisos | Dónde guardarla |
|----------|----------|----------------|
| Frontend (SSG/SSR) | Solo lectura, solo publicado | Variable de entorno del servidor |
| Backend / webhooks | Lectura + escritura | Variable de entorno del servidor |
| Preview (drafts) | Solo lectura + borradores | Variable separada, expiración corta |
| CI/CD builds | Solo lectura | Secret del sistema CI |

- Nunca la misma key para múltiples contextos.
- Nunca keys de escritura expuestas al frontend.
- Variables separadas por ambiente: dev, staging y prod.

### Webhooks

- Validar firma HMAC antes de procesar cualquier payload.
- Rechazar sin firma válida con `401`.
- Idempotencia: manejar reenvíos sin efectos duplicados.
- Responder `200` rápido, procesar en background.

### Arquitectura de Escritura

```
✅ Frontend → Tu Backend → CMS
❌ Frontend → CMS directamente
```

Toda escritura pasa por el backend — que valida, sanitiza y autoriza primero.

### Strapi Self-Hosted

- Panel admin en URL diferente a la API pública.
- Revisar roles y permisos — por defecto son permisivos.
- Deshabilitar registro público si no es necesario.
- Actualizar en cada release de seguridad (historial activo de CVEs).
