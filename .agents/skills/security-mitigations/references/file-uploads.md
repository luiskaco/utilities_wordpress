# Subida y descarga de archivos

## Al recibir

- Autenticación y permisos antes de aceptar el archivo. Limitar la frecuencia por usuario.
- Lista de extensiones permitidas, solo las que el negocio necesita.
- Validar la firma del archivo (magic bytes) contra el tipo esperado. Nunca confiar solo en la extensión ni en el `Content-Type`.
- Tamaño máximo, también en el servidor web o el proxy.
- Comprimidos: limitar el tamaño después de descomprimir, y comprobar que cada ruta quede dentro de la carpeta destino.

## Imágenes y documentos

- Decodificar y volver a codificar las imágenes a un formato permitido, quitando metadatos.
- SVG y HTML son contenido activo: sanitizarlos o servirlos solo como descarga.
- Pasar antivirus o sandbox cuando los archivos se compartan con otras personas.

## Al guardar

- Nombre generado por la aplicación (UUID). Guardar el original aparte, solo como dato.
- Fuera del directorio público, en otro host o en un bucket privado.
- Sin permiso de ejecución en la carpeta de subidas.

## Al servir

- Verificar permisos en cada descarga.
- Preferir otro dominio, para que un archivo malicioso no corra en el origen de la app.
- `Content-Disposition: attachment`, `Content-Type` fijado por el servidor y `X-Content-Type-Options: nosniff`.
- URLs firmadas con vida corta, limitadas en tipo y tamaño cuando el cliente sube directo al bucket.

## Señales de alerta al revisar

- Nombre del usuario usado en la ruta de disco.
- Carpeta de subidas que ejecuta PHP.
- Bucket público "por comodidad".
- Validación solo en el navegador.

**Fuentes:** OWASP File Upload Cheat Sheet · OWASP ASVS 5.0
