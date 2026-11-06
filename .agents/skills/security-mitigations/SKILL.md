---
name: security-mitigations
description: Mitigaciones de seguridad por tema (login, permisos, inyección, XSS, CSRF, CORS, uploads, secretos, dependencias, logs, errores, APIs, webhooks, IA, infraestructura, WordPress). Usar al implementar o auditar algo de eso, para abrir solo el tema que toca. Los principios y lo prohibido están en la regla security.
---

# Security Mitigations

Detalle de cómo proteger cada área. Se abre solo lo que toca el cambio: cada tema
cuesta unos cientos de tokens y leerlos todos no mejora la revisión.

## Cómo usarla

1. Mirá qué toca el cambio, incluido lo que expone: el endpoint que lo llama, la
   tabla que escribe, el rol que lo usa.
2. Abrí en `references/` solo los temas que correspondan.
3. Al implementar, aplicá lo que proteja un riesgo real de este proyecto. Al
   auditar, reportá solo lo que tenga un camino de explotación concreto.
4. Si aplicás una mitigación distinta o decidís no aplicarla, dejá el motivo en la
   sección **Proyecto** de `AGENTS.md` o en un ADR.

## Qué tema abrir

| Si el cambio toca… | Abrir |
|---|---|
| Login, registro, contraseñas, MFA, sesiones, JWT, OAuth | `references/auth-sessions.md` |
| Roles, permisos, datos por usuario o por cliente, RLS | `references/access-control.md` |
| Datos del cliente hacia SQL, shell, archivos, plantillas o XML | `references/injection-validation.md` |
| HTML dinámico, cookies, CORS, CSRF, CSP, cabeceras | `references/browser-web.md` |
| Subida o descarga de archivos | `references/file-uploads.md` |
| Cifrado, tokens, claves, variables de entorno | `references/crypto-secrets.md` |
| Instalar paquetes, lockfiles, CI/CD, publicar artefactos | `references/supply-chain-ci.md` |
| Logs, auditoría, alertas, respuesta a incidentes | `references/logging-alerting.md` |
| Manejo de errores, transacciones, timeouts, límites | `references/error-handling.md` |
| APIs, webhooks, llamadas a URLs externas o de terceros | `references/apis-webhooks.md` |
| Pagos, cupones, cuotas, flujos que se pueden abusar, feature nueva sensible | `references/design-abuse.md` |
| Integrar un LLM, agentes, tools, MCP o RAG | `references/ai-agents.md` |
| Hosting, cloud, contenedores, red, backups, paneles admin | `references/infrastructure.md` |
| Temas, plugins o instalaciones de WordPress | `references/wordpress.md` |

## Qué no hace

- **No es una lista completa:** ninguna lo es. Sale de las guías citadas en cada tema,
  revisadas en noviembre de 2026.
- **No demuestra que algo sea seguro:** eso requiere pruebas, revisión de quien conoce
  el negocio y, en sistemas críticos, una auditoría externa.
- **No reemplaza la documentación del framework:** si indica una forma nativa de
  resolver algo, usá esa antes que escribirla a mano.

Si este documento y la regla `security` difieren, manda la regla y hay que corregir
este. Lo legal y de privacidad está en `prelaunch-compliance-audit`.
