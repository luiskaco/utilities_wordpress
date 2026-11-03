---
trigger: model_decision
description: Activar al configurar CI/CD, logging, manejo de errores externos/timeouts, infraestructura, o al integrar un agente/LLM que ejecute tool calls
---

# Operations, AI Security & Quality Rules

---

## AI Security

### Prompt Injection

- Nunca interpolar inputs del usuario directamente en system prompts.
- Sanitizar con allowlist antes de pasar contexto al modelo.
- Separar instrucciones del sistema de datos del usuario — nunca en el mismo string.

### Aislamiento y Gobernanza

- Contexto por usuario — nunca compartir contexto entre sesiones de diferentes usuarios.
- Secretos nunca en el contexto del modelo (API keys, passwords, tokens).
- Validar y sanitizar todos los outputs del modelo antes de ejecutar o renderizar.
- Limitar tool calls: definir explícitamente qué herramientas puede invocar el agente.
- Trazabilidad completa: loggear qué prompt entró, qué salió, qué herramientas se usaron.
- Límites operacionales: el agente no puede ejecutar operaciones destructivas sin confirmación humana.

### Validación de Outputs

- Nunca ejecutar código generado por IA sin revisión en producción.
- Validar schema de respuestas estructuradas antes de usar el dato.
- Rate limiting en endpoints que consumen LLMs — son costosos y lentos.

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
- **Escaneo automático:** `npm audit --audit-level=high` / `pip audit` debe pasar antes de deploy.
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
