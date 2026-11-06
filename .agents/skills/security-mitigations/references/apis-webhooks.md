# APIs, webhooks y llamadas externas

## OWASP API Top 10 (2023)

| Riesgo | Qué hacer |
|---|---|
| API1 Autorización por objeto | Verificar el permiso en cada acceso por ID (ver `access-control.md`) |
| API2 Autenticación rota | Ver `auth-sessions.md`. Las API keys no reemplazan la autenticación de usuarios |
| API3 Autorización por propiedad | Lista de campos en respuestas y escrituras. No devolver el objeto entero |
| API4 Consumo sin límite | Límites de frecuencia, tamaño, paginación, timeouts y de costo: correos, SMS, LLM |
| API5 Autorización por función | Endpoints de administración separados y verificados |
| API6 Flujos de negocio | Ver `design-abuse.md` |
| API7 SSRF | Ver abajo |
| API8 Mala configuración | CORS, cabeceras, errores y métodos HTTP cerrados |
| API9 Inventario | Saber qué versiones y hosts siguen activos. Apagar lo viejo y lo de debug. OpenAPI al día |
| API10 Consumo inseguro de terceros | Ver abajo |

## SSRF: si el servidor pide una URL que vino de afuera

- Preferir lista cerrada de destinos. Si no se puede: solo `https`, solo puertos esperados.
- Resolver el DNS y comprobar la IP final. Bloquear rangos privados, loopback y link-local, también en IPv6, incluida `169.254.169.254`.
- Conectarse a la IP ya validada, para evitar DNS rebinding.
- Desactivar redirecciones, o validar cada salto.
- En la nube, IMDSv2 con token. Reglas de red que limiten la salida del servicio.
- No devolver al usuario la respuesta cruda ni los errores de conexión.

## Webhooks que se reciben

- Verificar la firma con el **cuerpo crudo**, antes de parsearlo, y en tiempo constante.
- Rechazar con timestamp viejo (5 minutos es un valor común).
- Procesar de forma idempotente: los proveedores reenvían eventos.
- Responder rápido y procesar en segundo plano.
- Volver a consultar el estado al proveedor en operaciones de dinero.

## Datos de terceros

- Tratar sus respuestas como entrada no confiable: validar con schema.
- Timeout, TLS verificado y límite de tamaño. Redirecciones solo a destinos conocidos.
- Clave por integración, con permisos mínimos.

## GraphQL, si aplica

Limitar profundidad y complejidad. Verificar permisos en cada resolver. Introspección apagada en producción si la API no es pública.

**Fuentes:** OWASP API Security Top 10 2023 · OWASP Cheat Sheets: Server Side Request Forgery Prevention, REST Security, GraphQL · OWASP Top 10:2025 A01
