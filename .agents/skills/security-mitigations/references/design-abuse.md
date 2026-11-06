# Diseño seguro y abuso del negocio

Estos problemas no aparecen como un bug: el código hace lo que se pidió, pero se puede usar en contra del negocio. Se piensan antes de codificar.

## Antes de construir algo sensible

Responder en 5 minutos, y anotar en el ADR si cambia el diseño:

1. ¿Qué se protege? Dinero, datos, cuentas, reputación.
2. ¿Quién querría abusarlo? Usuario común, otro cliente, bot, alguien de adentro.
3. ¿Cómo lo haría? Automatizándolo, repitiéndolo, cambiando el orden de los pasos.
4. ¿Qué límite lo frena, y cómo nos enteramos?

## Dinero y cantidades

- Precio, descuento y total se calculan en el servidor. Nunca aceptarlos del cliente.
- Rechazar cantidades negativas y cero. Cuidar los redondeos: usar enteros o decimales, no `float`.
- Confirmar el pago con el proveedor, no con la pantalla de retorno.

## Carreras y repetición

- Operaciones que deben pasar una vez: clave de idempotencia y restricción única en la base.
- Saldos, stock y cupones: transacción con bloqueo o actualización atómica. Dos requests simultáneos no pueden gastar lo mismo.
- Tokens y cupones de un solo uso se marcan en la misma transacción.

## Flujos

- El servidor controla el estado. No se puede saltar del paso 1 al paso 4.
- Límites por usuario y por tiempo: registros, invitaciones, cupones, correos, SMS y llamadas a LLM.
- Protección contra bots en flujos que cuestan dinero o pueden agotar stock. Pruebas gratis, con límite por identidad real.
- Las acciones que afectan a otros necesitan límites: invitar, mensajes, exportar.

## Principios

- Permisos mínimos y varias barreras: que una falla no alcance para llegar al dato.
- Separar cuentas de cliente, datos y administración.
- Valores por defecto seguros: lo nuevo nace privado y cerrado.

## Cómo probarlo

Sumar a los tests casos de abuso: doble envío simultáneo, cantidad negativa, cupón repetido, pasos en otro orden.

**Fuentes:** OWASP Top 10:2025 A06 · OWASP API Top 10 2023 (API6) · OWASP Threat Modeling y Business Logic Security Cheat Sheets
