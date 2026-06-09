# CLAUDE.md — Sistema EcoMadelleine

## Tooling: context-mode (ahorro de contexto)

Hay un MCP server `context-mode` activo. Para reducir tokens, **prefiere sus tools** en estos casos:

- **Análisis / agregación multi-archivo** → usa `ctx_execute` (escribe un script que procese y haga `console.log()` SOLO del resultado) en lugar de leer muchos archivos con `Read`.
  Ej: contar líneas o funciones, buscar un patrón en muchos archivos, resumir la estructura de un directorio.
- **Buscar en contenido ya indexado** → `ctx_search` (e `ctx_index` para indexar un archivo/directorio en la base FTS5) en lugar de `grep` + `read` repetidos sobre lo mismo.
- **Salidas grandes de comandos o web** → `ctx_execute` / `ctx_fetch_and_index` para mantener los datos crudos fuera del contexto.

**Mantén `Read` / `Edit` / `Grep` normales** para trabajo puntual de 1 archivo o ediciones precisas. No fuerces `ctx_*` donde una lectura simple es más clara o más barata.

> Nota: `RTK` (binario `rtk`) sigue activo vía hook de `settings.json` y comprime salidas de comandos (`git`, `ls`, `cargo`, `npm test`, etc.). context-mode y RTK son complementarios: RTK actúa sobre comandos Bash; context-mode sobre análisis/indexado.

## Reglas (claude-token-efficient)

- Lee el archivo antes de editarlo; nunca a ciegas.
- No inventes APIs, versiones, flags, SHAs ni nombres de paquetes: verifica en código o docs.
- Solución más simple que funcione; sin sobre-ingeniería ni features especulativas.
- No toques código que no estás cambiando (nada de docstrings/tipos/manejo de errores extra).
