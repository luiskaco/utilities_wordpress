# Dependencias y CI/CD

`npm audit` detecta vulnerabilidades ya publicadas. Un paquete recién comprometido no aparece todavía: por eso hacen falta las demás capas.

## Configuración base

`.npmrc` del proyecto (npm 11):

```ini
ignore-scripts=true
allow-git=none
min-release-age=7
```

- `ignore-scripts`: un paquete que necesite compilar al instalarse se autoriza de a uno.
- `min-release-age`: un parche de seguridad urgente se instala a mano o con una excepción.
- **pnpm:** `minimumReleaseAge` (en minutos), `trustPolicy: no-downgrade` y lista cerrada de paquetes con scripts. Revisar los nombres en su documentación según la versión.

## Instalación

- Lockfile siempre commiteado. En CI: `npm ci`, `pnpm install --frozen-lockfile`, `composer install` o `uv sync --locked`.
- Solo registros oficiales. Sin dependencias desde git, URLs o rutas locales.
- Antes de instalar una nueva: confirmar nombre, mantenedor, descargas y último update. Una IA puede sugerir nombres que no existen y que alguien registró después con malware.
- Elegir cada actualización. Dependabot o Renovate avisan; no aplicar todo automáticamente.
- Quitar lo que no se usa: cada paquete es superficie de ataque.

## Auditoría

- En CI, antes de deploy: `npm audit --audit-level=high`, `pip-audit` o `composer audit`.
- Vigilar CVE, NVD u OSV de lo que se usa, incluidas dependencias indirectas.
- En sistemas de producción, generar un SBOM (CycloneDX o SPDX) en cada build.
- Reemplazar librerías sin mantenimiento.

## GitHub Actions

- Fijar las acciones por SHA completo, no por tag.
- `permissions:` mínimos por workflow, partiendo de `contents: read`.
- Cuidado con `pull_request_target`: nunca ejecutar código del PR con secretos disponibles.
- No interpolar `${{ github.event.* }}` directo en `run:`: pasarlo por `env`.
- Secretos por ambiente y con aprobación manual para producción.
- OIDC en vez de claves largas para el deploy, cuando el proveedor lo permita.

## Repositorio y entorno

- `main` protegida: PR, revisión y CI verde.
- MFA en GitHub, npm y el proveedor de deploy.
- Máquinas de desarrollo actualizadas. Las extensiones del editor y los servidores MCP también son dependencias.
- Docker: imagen base fijada por digest y actualizada.

## Si se publica un paquete

- Trusted publishing en vez de tokens largos, con provenance.
- Las ramas protegidas y las revisiones también cubren los archivos de publicación.

## Despliegue

- Promover el mismo artefacto entre ambientes, sin recompilar.
- Despliegues graduales cuando se pueda, para limitar el daño de un proveedor comprometido.

**Fuentes:** OWASP Top 10:2025 A03 y A08 · documentación de npm (`config`, trusted publishing) · pnpm: Mitigating supply chain attacks · GitHub: Security hardening for Actions
