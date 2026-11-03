#!/usr/bin/env node
// Hook PreToolUse de los subagents de solo lectura (ADR-021).
//
// Claude Code le pasa por stdin el JSON de la llamada a Bash. Si el comando no
// es de lectura, sale con código 2 y el motivo en stderr: Claude Code cancela
// la llamada y le devuelve el motivo al subagent. Cualquier cosa que no se
// reconoce se bloquea — un falso positivo se arregla sumando el comando en
// shell-parse.mjs, donde viven el parser y la allowlist (ADR-026).
//
// Se activa desde el frontmatter del agente:
//   hooks:
//     PreToolUse:
//       - matcher: "Bash"
//         hooks:
//           - type: command
//             command: 'node "$CLAUDE_PROJECT_DIR/.claude/hooks/readonly-bash.mjs" || exit 2'
//
// El `|| exit 2` hace que falle cerrado: si el hook no arranca (sin Node, ruta
// mal resuelta, shell-parse.mjs faltante), Claude Code recibe exit 2 y bloquea
// en vez de dejar pasar.
import { readFileSync } from "node:fs";
import { Denied, checkReadonly } from "./shell-parse.mjs";

function main() {
  let command;
  try {
    command = JSON.parse(readFileSync(0, "utf8"))?.tool_input?.command;
  } catch {
    process.stderr.write("readonly-bash: no se pudo leer la llamada; se bloquea por seguridad.\n");
    process.exit(2);
  }
  if (typeof command !== "string" || command.trim() === "") process.exit(0);

  try {
    checkReadonly(command);
  } catch (err) {
    if (!(err instanceof Denied)) throw err;
    process.stderr.write(
      `Bloqueado: ${err.message}. Este subagent es de solo lectura (ADR-021): ` +
        "usá Read/Grep/Glob o un comando de lectura. Si el comando sí es de lectura y falta en la " +
        "lista, reportalo al agente principal en vez de buscarle la vuelta.\n"
    );
    process.exit(2);
  }
  process.exit(0);
}

main();
