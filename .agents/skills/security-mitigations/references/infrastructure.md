# Infraestructura

## Exposición

- Bases de datos, Redis y colas sin acceso público. Solo por red privada.
- Paneles de administración, métricas y documentación interna detrás de VPN, IP permitida o SSO.
- Modo debug, `.git`, `.env`, backups y phpinfo no accesibles desde internet. Probarlo pidiendo esas URLs.
- Revisar subdominios sin uso: un registro DNS que apunta a un servicio dado de baja se puede tomar.

## Permisos

- Mínimo privilegio para cada servicio y persona. Una cuenta por servicio.
- MFA en la consola del proveedor. La cuenta raíz no se usa a diario.
- Buckets privados por defecto, con el bloqueo de acceso público activo.
- Usuario de base de datos de la app sin permisos de `DROP` ni `ALTER`.

## Servidores y contenedores

- Actualizaciones de seguridad automáticas o con fecha fija.
- SSH solo con clave, sin login de root. Firewall que abra solo lo necesario.
- Contenedores sin root, con sistema de archivos de solo lectura cuando se pueda, y sin secretos en la imagen.
- En la nube, IMDSv2 obligatorio.

## TLS y red

- Certificados con renovación automática y alerta de vencimiento.
- Redirigir HTTP a HTTPS y usar HSTS.
- Rate limiting también en el borde: CDN, WAF o proxy. El WAF es una barrera extra, no corrige el código.

## Ambientes

- Desarrollo, staging y producción con recursos y credenciales separados.
- Staging sin datos reales, o anonimizados, y sin indexar.
- Infraestructura como código, revisada por PR.

## Backups

- Automáticos, cifrados y fuera del servidor principal.
- Una copia que no se pueda borrar con las credenciales de producción, por ransomware.
- Restauración probada de verdad, con fecha registrada.

## Señales de alerta al revisar

- Puertos `3306`, `5432`, `6379` o `27017` abiertos a internet.
- Comodines en políticas IAM (`"Action": "*"`).
- Credenciales de larga duración en máquinas o pipelines.

**Fuentes:** OWASP Top 10:2025 A02 · OWASP Cheat Sheets: Infrastructure as Code, Docker Security, Secrets Management · OWASP ASVS 5.0
