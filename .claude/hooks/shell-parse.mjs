// Parser de shell y reglas de solo lectura compartidas por los hooks de Bash:
// readonly-bash.mjs (ADR-021) y ssh-guard.mjs (ADR-026). No es un hook: lo
// importan los dos, y el generador del manifest lo empaqueta con cada agente
// que usa alguno de ellos.

export class Denied extends Error {}

export function deny(reason) {
  return new Denied(reason);
}

/**
 * Parte el comando en segmentos (separados por `&&`, `||`, `;`, `|`, `&` o salto
 * de línea) y cada segmento en palabras, respetando comillas. Rechaza lo que
 * puede ejecutar o escribir sin aparecer como comando: sustitución de comandos,
 * process substitution y redirecciones que no van a /dev/null.
 *
 * Cada segmento trae, además de sus palabras:
 *   op        separador que lo precede ("" en el primero, "|", "||", "&&", ";", "&", "\n")
 *   stdin     tiene una redirección de entrada sin comillas (`<`, `<<`, `<<<`)
 *   expands   tiene un `$` fuera de comillas simples (expansión de variable)
 *   globs     tiene `*`, `?` o `[` sin comillas (los expande la shell local)
 *   background  termina en `&` (corre en segundo plano)
 *   redirects destinos de `>`/`>>` — solo con `allowWrites`, que en vez de
 *             rechazarlos los anota (ssh-guard en modo operate los revisa)
 */
export function parseCommand(cmd, { allowWrites = false } = {}) {
  const newSeg = (op) => ({ words: [], op, stdin: false, expands: false, globs: false, background: false, redirects: [] });
  const segments = [newSeg("")];
  const current = () => segments[segments.length - 1];
  let word = "";
  let inWord = false;
  let quote = null;

  const pushWord = () => {
    if (inWord) current().words.push(word);
    word = "";
    inWord = false;
  };
  const newSegment = (op) => {
    pushWord();
    segments.push(newSeg(op));
  };

  for (let i = 0; i < cmd.length; i++) {
    const c = cmd[i];
    const n = cmd[i + 1];

    if (quote === "'") {
      if (c === "'") quote = null;
      else word += c;
      continue;
    }
    if (c === "\\" && n !== undefined) {
      word += n;
      inWord = true;
      i++;
      continue;
    }
    if (c === "`" || (c === "$" && n === "(")) throw deny("sustitución de comandos");
    if (c === "$" && n !== undefined && /[A-Za-z_{0-9@*#?$!-]/.test(n)) current().expands = true;
    if (quote === '"') {
      if (c === '"') quote = null;
      else word += c;
      continue;
    }
    if (c === "'" || c === '"') {
      quote = c;
      inWord = true;
      continue;
    }
    if ((c === "<" || c === ">") && n === "(") throw deny("process substitution");
    if (c === "<") current().stdin = true;
    if (c === "*" || c === "?" || c === "[") current().globs = true;

    if (c === ">" || (c === "&" && n === ">")) {
      // Un descriptor pegado al `>` (`2>`) no es una palabra del comando.
      if (/^\d+$/.test(word)) {
        word = "";
        inWord = false;
      }
      pushWord();
      let j = c === "&" ? i + 2 : i + 1;
      if (cmd[j] === ">") j++;
      if (cmd[j] === "&" && /\d/.test(cmd[j + 1] ?? "")) {
        i = j + 1; // `2>&1`: duplica un descriptor, no escribe archivos
        continue;
      }
      while (cmd[j] === " " || cmd[j] === "\t") j++;
      let target = "";
      while (j < cmd.length && !/[\s;&|<>]/.test(cmd[j])) target += cmd[j++];
      if (target !== "/dev/null") {
        if (!allowWrites) throw deny(`redirección que escribe a "${target || "?"}"`);
        current().redirects.push(target.replace(/^["']|["']$/g, ""));
      }
      i = j - 1;
      continue;
    }

    if (c === "|" || c === ";" || c === "\n" || c === "&") {
      let op = c;
      if ((c === "|" || c === "&") && n === c) {
        op = c + c;
        i++;
      } else if (c === "&") {
        current().background = true; // `cmd &` al final no deja segmento siguiente que lo marque
      }
      newSegment(op);
      continue;
    }
    if (c === " " || c === "\t" || c === "\r") {
      pushWord();
      continue;
    }
    word += c;
    inWord = true;
  }

  if (quote) throw deny("comillas sin cerrar");
  pushWord();
  return segments.filter((s) => s.words.length > 0);
}

/** Solo las palabras de cada segmento. */
export function parse(cmd) {
  return parseCommand(cmd).map((s) => s.words);
}

/** Comandos de lectura sin chequeo de argumentos (no escriben ni ejecutan otros). */
const PLAIN = new Set([
  "ls", "cat", "head", "tail", "wc", "grep", "egrep", "fgrep", "uniq", "cut",
  "tr", "diff", "file", "stat", "du", "df", "pwd", "echo", "printf", "which",
  "jq", "basename", "dirname", "realpath", "cd", "true",
]);

/** Subcomandos de git que solo leen. */
const GIT_READ = new Set([
  "status", "diff", "log", "show", "blame", "rev-parse", "ls-files", "ls-tree",
  "grep", "describe", "shortlog", "merge-base", "cat-file", "rev-list",
  "show-ref", "for-each-ref",
]);

const GIT_BRANCH_READ_FLAGS = new Set([
  "--show-current", "-a", "--all", "-r", "--remotes", "-v", "-vv", "--list",
  "--merged", "--no-merged", "--contains",
]);

function checkGit(args) {
  // Opciones globales antes del subcomando. `-c` queda afuera: puede setear un
  // pager o un hook que ejecute cualquier cosa.
  let k = 0;
  while (k < args.length && args[k].startsWith("-")) {
    if (args[k] === "--no-pager") k++;
    else if (args[k] === "-C") k += 2;
    else throw deny(`opción global de git no permitida: ${args[k]}`);
  }
  const sub = args[k];
  const rest = args.slice(k + 1);
  if (!sub) throw deny("git sin subcomando");
  if (rest.some((a) => a.startsWith("--output") || a === "-O" || a.startsWith("--open-files-in-pager"))) {
    throw deny(`git ${sub} con salida a archivo o pager externo`);
  }
  if (GIT_READ.has(sub)) return;
  if (sub === "branch" && rest.every((a) => GIT_BRANCH_READ_FLAGS.has(a))) return;
  if (sub === "stash" && rest[0] === "list") return;
  if (sub === "remote" && (rest.length === 0 || (rest.length === 1 && rest[0] === "-v"))) return;
  if (sub === "config" && rest.length > 0 && ["--get", "--get-all", "--list", "-l"].includes(rest[0])) return;
  throw deny(`git ${sub} puede modificar el repo`);
}

const PACKAGE_READ = {
  npm: ["audit", "ls", "list", "outdated", "view", "--version", "-v"],
  pnpm: ["audit", "ls", "list", "outdated", "--version"],
  yarn: ["audit", "list", "outdated", "--version"],
  pip: ["list", "show", "--version"],
  pip3: ["list", "show", "--version"],
  composer: ["audit", "show", "outdated", "--version"],
};

/** Valida un segmento (sus palabras) contra la allowlist de lectura. */
export function checkReadonlySegment(words) {
  const [name, ...args] = words;
  if (name.includes("/") || name.includes("\\")) throw deny(`ejecutar "${name}" por ruta`);
  if (name.includes("=")) throw deny("asignación de variable antes del comando");

  if (PLAIN.has(name)) return;
  if (name === "git") return checkGit(args);
  if (name === "find") {
    const bad = args.find((a) => /^-(delete|exec|execdir|ok|okdir|fprint|fprint0|fprintf|fls)$/.test(a));
    if (bad) throw deny(`find ${bad} modifica o ejecuta`);
    return;
  }
  if (name === "rg") {
    if (args.some((a) => a.startsWith("--pre"))) throw deny("rg --pre ejecuta un programa");
    return;
  }
  if (name === "sort" || name === "tree") {
    if (args.some((a) => a === "-o" || a.startsWith("--output"))) throw deny(`${name} -o escribe un archivo`);
    return;
  }
  if (name in PACKAGE_READ) {
    const sub = args[0];
    if (!PACKAGE_READ[name].includes(sub)) throw deny(`${name} ${sub ?? ""} no es de lectura`.trim());
    if (args.includes("fix") || args.includes("--fix")) throw deny(`${name} ${sub} fix modifica dependencias`);
    return;
  }
  if (name === "pip-audit" && !args.includes("--fix")) return;
  if ((name === "node" || name === "python" || name === "python3") && args.length === 1 && ["--version", "-V", "-v"].includes(args[0])) {
    return;
  }
  throw deny(`"${name}" no está en la lista de comandos de lectura`);
}

/** Valida un comando completo: cada segmento tiene que ser de lectura. */
export function checkReadonly(command) {
  for (const words of parse(command)) checkReadonlySegment(words);
}
