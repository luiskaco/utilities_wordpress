---
trigger: model_decision
description: Activar al configurar CI/CD, logging, manejo de errores externos/timeouts, infraestructura, o al integrar un agente/LLM que ejecute tool calls
---

# Operations, AI Security & Quality Rules

---

## AI Security

Referencias: OWASP Top 10 for LLM Applications 2026 y Top 10 for Agentic Applications 2026.

### Prompt Injection

Ningún filtro la evita del todo: diseñar asumiendo que va a pasar y limitar qué puede hacer el modelo y a dónde llega su salida.

- Separar instrucciones del sistema y datos del usuario — nunca en el mismo string ni interpolados en el system prompt.
- Todo contenido externo (páginas, mails, archivos, respuestas de herramientas) es dato, nunca instrucciones.

### Herramientas, Agentes y MCP

- Las mínimas herramientas, con la mínima funcionalidad y los mínimos permisos. Evitar las abiertas, como un shell o un fetch genérico.
- Ejecutar con los permisos del usuario que pidió la acción, no con una cuenta de servicio amplia.
- La autorización se decide en código, en cada llamada: el modelo no decide qué está permitido.
- Confirmación humana antes de acciones destructivas o irreversibles.
- Servidores MCP solo de fuentes conocidas y con versión fijada: son dependencias con acceso a tus datos.

### Datos y Aislamiento

- Contexto por usuario — nunca compartir contexto entre sesiones de diferentes usuarios.
- Secretos nunca en el contexto del modelo (API keys, passwords, tokens). Asumir que el system prompt se puede filtrar.
- RAG: aplicar los permisos del usuario al recuperar documentos, no después.
- Trazabilidad completa: loggear qué prompt entró, qué salió, qué herramientas se usaron.

### Salidas y Consumo

- Tratar la salida del modelo como input no confiable: validar el schema y escapar antes de ejecutar o renderizar.
- Nunca ejecutar código generado por IA sin revisión en producción.
- Rate limiting, cuotas y límite de costo en endpoints que consumen LLMs.

---

## Cloud e Infraestructura

- **IAM:** Mínimo privilegio para cada servicio y rol. Nunca credenciales de admin para servicios.
- **Buckets/Storage:** Privados por defecto. Acceso público solo donde es explícitamente necesario.
- **Servicios aislados:** Cada servicio con su propio scope de permisos — sin credenciales compartidas.
- **Secrets en cloud:** Usar Secret Manager del proveedor (AWS Secrets Manager, GCP Secret Manager) en producción.
- **Ambientes separados:** dev, staging y prod con recursos e infraestructura independientes.

---

## CI/CD

- **Secrets:** Variables de entorno del sistema CI (GitHub Actions Secrets, etc.) — nunca en el código del workflow.
- **Escaneo automático:** `npm audit --audit-level=high` / `pip-audit` / `composer audit` debe pasar antes de deploy.
- **Instalación:** desde el lockfile y sin scripts no autorizados (ver `security.md § Dependencias`).
- **Acciones de terceros:** fijadas por SHA, no por tag. Permisos mínimos para el token del pipeline.
- **Dependencias:** Dependabot o Renovate configurado para PRs automáticos de seguridad.
- **Build seguro:** Nunca loggear variables de entorno en outputs del pipeline.
- **Branch protection:** `main` protegido — requiere PR + review + CI verde para merge.
- **Deploy automático:**
  - `dev` branch → staging (automático)
  - `main` branch → producción (automático tras CI verde)

---

## Resiliencia

- **Stateless:** Servicios sin estado local — cualquier instancia maneja cualquier request.
- **Reintentos con backoff:** Máximo 3 reintentos con backoff exponencial (1s, 2s, 4s) en llamadas externas.
- **Timeouts explícitos:** Toda llamada externa con timeout definido (default: 10s APIs, 30s uploads).
- **Circuit breaker:** Servicios críticos con circuit breaker para degradación controlada.
- **Degradación controlada:** Si un servicio falla, la app sigue funcionando en modo reducido.
- **Sin SPOF:** Identificar y eliminar Single Points of Failure en la arquitectura.
- **Rollback:** Plan de rollback documentado y probado antes de cada deploy mayor.
- **Backups:** Automáticos con restore probado mensualmente.

---

## Observabilidad

### Logging Estructurado (JSON)

Todo log debe seguir esta estructura:

```json
{
  "timestamp": "2026-05-28T10:00:00Z",
  "level": "error",
  "service": "auth",
  "event": "login_failed",
  "user_id": "uuid-o-null",
  "request_id": "uuid",
  "ip": "x.x.x.x",
  "message": "descripción legible"
}
```

- Nunca loggear passwords, tokens, cookies ni PII en texto plano.
- Loggear siempre: auth failures, cambios de permisos, acciones destructivas, errores 5xx.

### Request Tracing

- `request_id` único en cada request, propagado en todos los servicios.
- Correlacionar logs de entrada → procesamiento → respuesta con el mismo `request_id`.

### Monitoreo

- Alertas en: error rate > 1%, latencia p95 > 2s, CPU/memoria > 80%.
- Audit logs para acciones sensibles (cambios de rol, eliminaciones, accesos admin).
- Tracking de auth failures por IP (detectar brute force).

---

## Control de Calidad y Testing

### Validaciones Técnicas

- Build exitoso sin errores.
- Consola limpia — cero warnings ni errores en producción.
- Imports válidos, sin dependencias rotas.

### Testing por Prioridad

1. **Crítico:** Flujos de autenticación (login, logout, refresh, MFA).
2. **Alto:** Flujos de negocio principales (las 3 acciones más frecuentes del usuario).
3. **Medio:** Edge cases de formularios, validaciones, uploads.
4. **Bajo:** UI states (loading, error, empty) en componentes secundarios.

### Cobertura Mínima

- Lógica de negocio crítica: 80% cobertura de tests unitarios.
- Flujos de auth: 100% cubiertos con tests de integración.
- APIs públicas: contrato validado con tests de contrato o e2e.

### Regla Final

**El código que compila no es código terminado.**
Una tarea está completa cuando la lógica del flujo completo fue probada, no solo que el build pase.
