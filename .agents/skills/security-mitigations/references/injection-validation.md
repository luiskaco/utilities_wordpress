# Inyección y validación

Todo lo que entra de afuera es dato hostil: body, query, cabeceras, cookies, archivos, webhooks y respuestas de terceros.

## Validación

- Schema en el servidor para cada entrada (Zod, Pydantic, Joi): tipo, largo, formato y rango.
- Lista de lo permitido, no de lo prohibido. Rechazar campos inesperados.
- Validar después de decodificar y normalizar, una sola vez.
- Validar no reemplaza parametrizar ni escapar: cada uno cubre un riesgo distinto.

## SQL y NoSQL

- Consultas parametrizadas u ORM. Nunca concatenar.
- Los nombres de columna u orden que vengan del usuario pasan por una lista cerrada: no se pueden parametrizar.
- Prisma: `$queryRaw` como tagged template, nunca `*Unsafe` con datos del usuario.
- MongoDB: rechazar objetos donde se espera texto, para evitar operadores como `$ne` o `$where`.

## Sistema operativo y archivos

- Evitar el shell. Si hace falta un comando, pasar los argumentos como lista (`execFile`, `subprocess.run([...])`), con lista cerrada de valores.
- Rutas: resolver la ruta final y comprobar que quede dentro de la carpeta permitida. Rechazar `..` no alcanza.

## Otros formatos

- **Plantillas:** nunca armar la plantilla con texto del usuario: pasarlo como variable.
- **XML:** desactivar entidades externas y DTD.
- **Deserialización:** usar JSON. No usar `pickle`, `unserialize` ni similares con datos externos.
- **JavaScript:** en mezclas profundas, rechazar `__proto__`, `constructor` y `prototype`.
- **Expresiones regulares:** limitar el largo de la entrada y evitar patrones con repetición anidada.
- **Cabeceras y correo:** rechazar saltos de línea en valores que vayan a cabeceras o direcciones.
- **Redirecciones:** destinos en lista cerrada o rutas relativas validadas.

## Señales de alerta al revisar

- Interpolación de texto dentro de SQL, comandos o rutas.
- `eval`, `exec`, `system`, `Function()` o `shell=True` con datos externos.
- Validación solo en el frontend.

**Fuentes:** OWASP Top 10:2025 A05 · OWASP Cheat Sheets: Input Validation, Injection Prevention, Query Parameterization, OS Command Injection Defense, XML External Entity Prevention, Deserialization
