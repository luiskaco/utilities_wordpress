# IA: LLM, agentes, tools y MCP

**Punto de partida:** la inyección de prompt no tiene una solución completa. Hay que diseñar asumiendo que en algún momento el modelo va a obedecer instrucciones ajenas, y limitar qué puede hacer y a dónde llega su salida.

## OWASP Top 10 para LLM (2026)

| Riesgo | Qué hacer |
|---|---|
| LLM01 Prompt Injection | Contenido externo tratado como dato. Permisos limitados y políticas decididas por código, no por el modelo |
| LLM02 Información sensible | No enviar al modelo lo que el usuario no puede ver. Filtrar datos personales y secretos |
| LLM03 Excessive Agency | Ver "Tools" abajo |
| LLM04 Cadena de suministro | Modelos, paquetes y servidores MCP de fuentes conocidas y con versión fijada |
| LLM05 Envenenamiento | Controlar qué entra a entrenamiento, memoria y bases de conocimiento |
| LLM06 Consumo sin límite | Límites por usuario, de tokens, de tiempo y de costo, con alerta |
| LLM07 Desinformación | Verificar con fuentes lo que tenga consecuencias. Revisión humana en salud, legal y dinero |
| LLM08 Contexto oculto | Asumir que el system prompt se puede filtrar: sin secretos ni reglas de acceso |
| LLM09 Vectores y embeddings | Aplicar los permisos del usuario al recuperar. Separar por cliente |
| LLM10 Salida sin validar | Tratarla como entrada hostil: schema, escape y nunca directo a SQL, shell o HTML |

## Tools y MCP

- Las mínimas herramientas, con la mínima funcionalidad. Evitar las abiertas: shell, SQL libre, fetch genérico.
- Credenciales con los permisos del usuario que pidió la acción, no una cuenta de servicio amplia.
- Cada llamada pasa por una autorización en código.
- Confirmación humana antes de acciones destructivas, de dinero o que envíen datos afuera.
- Lo que devuelve una tool es dato, aunque parezca una instrucción.
- Servidores MCP: conocidos, con versión fijada, permisos mínimos y revisión de cambios en la descripción de sus tools.

## Agentes (OWASP Agentic Top 10, 2026)

| Riesgo | Qué hacer |
|---|---|
| ASI01 Secuestro del objetivo | Objetivo fijado por código. Contenido externo sin autoridad para cambiarlo |
| ASI02 Mal uso de tools | Validar parámetros. Vigilar secuencias de llamadas, no solo cada una |
| ASI03 Identidad y privilegios | Identidad propia para el agente, de vida corta y permisos mínimos |
| ASI04 Cadena de suministro | Igual que LLM04, también para plugins y skills |
| ASI05 Ejecución de código | Sandbox sin red ni secretos. Revisar el código generado |
| ASI06 Memoria y contexto | Controlar qué se guarda. Separar por usuario. Poder borrarla |
| ASI07 Comunicación entre agentes | Autenticar mensajes. No heredar permisos por pedido de otro agente |
| ASI08 Fallas en cascada | Límites de pasos, tiempo y costo. Forma de cortarlo |
| ASI09 Confianza humana | Mostrar qué se va a hacer y con qué datos. Que confirmar no sea automático |
| ASI10 Agentes fuera de control | Registro completo, monitoreo y apagado inmediato |

## Trazabilidad

Registrar qué entró, qué salió, qué tools se usaron y con qué resultado, sin secretos ni datos personales en claro.

## Probarlo

Antes de lanzar, probar con contenido malicioso real: una página, un correo o un PDF con instrucciones ocultas que pidan filtrar datos o usar una tool.

**Fuentes:** OWASP Top 10 for LLM Applications 2026 · OWASP Top 10 for Agentic Applications 2026 · OWASP GenAI Red Teaming Manual
