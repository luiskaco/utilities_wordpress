# Control de acceso

Es el riesgo número uno de OWASP. Que el usuario haya iniciado sesión no significa que pueda hacer esto con este dato.

## Reglas

- **Denegar por defecto:** una ruta o acción nueva queda cerrada hasta que se le da permiso.
- **Verificar en el servidor, en cada operación.** Ocultar un botón no protege nada.
- **Un solo lugar para las reglas:** una función o política común, no condiciones sueltas por todo el código.
- **Tres niveles que se revisan por separado:**
  - **Objeto:** ¿este usuario puede ver este registro? Filtrar en la propia consulta (`WHERE id = ? AND owner_id = ?`).
  - **Función:** ¿este rol puede ejecutar esta acción? Las de administración, separadas.
  - **Propiedad:** ¿puede leer o cambiar este campo? Respuestas y escrituras con lista de campos permitidos: nunca guardar el body entero (`role`, `is_admin`, `price`).
- **Aislar clientes:** en sistemas multi-cliente, el `tenant_id` sale de la sesión, nunca del request.
- **Un ID difícil de adivinar no es un control:** un UUID filtrado da acceso si no se verifica el permiso.
- Si el recurso no pertenece al usuario, responder `404` para no confirmar que existe.

## Por stack

- **Next.js:** verificar también dentro de cada Route Handler y Server Action. El `proxy` o middleware no alcanza como único control.
- **Supabase:** RLS activo en toda tabla expuesta, con políticas por `auth.uid()`. La `service_role` solo en el servidor.
- **Rutas y archivos:** validar permisos también en descargas, exportaciones, informes, jobs y URLs firmadas.

## Cómo probarlo

- Con el usuario A, pedir recursos del usuario B cambiando el ID en URL, body y query.
- Con un usuario común, llamar directamente a los endpoints de administración.
- Mandar campos de más al guardar y comprobar que se ignoran o rechazan.
- Dejar estas pruebas como tests: un cambio de permisos puede romperlas sin que se note.

## Señales de alerta al revisar

- Consultas por ID sin filtro de dueño o de cliente.
- `user_id` o `role` tomados del request.
- Guardado masivo (`update(req.body)`, `$request->all()`).
- Endpoints internos sin verificación por "no estar enlazados".

**Fuentes:** OWASP Top 10:2025 A01 · OWASP API Top 10 2023 (API1, API3, API5) · OWASP Authorization Cheat Sheet · OWASP ASVS 5.0
