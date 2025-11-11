const fs = require("fs");
const path = require("path");

// Caminho do arquivo _redirects na origem
const sourceFile = path.join(__dirname, "src", "_redirects");
// Caminho de destino no build output
const destFile = path.join(
  __dirname,
  "dist",
  "frontend",
  "browser",
  "_redirects"
);

// Verificar se o diretório de destino existe
const destDir = path.dirname(destFile);
if (!fs.existsSync(destDir)) {
  console.error("Diretório de build não encontrado:", destDir);
  process.exit(1);
}

// Copiar o arquivo
try {
  fs.copyFileSync(sourceFile, destFile);
  console.log("✓ Arquivo _redirects copiado para:", destFile);
} catch (error) {
  console.error("Erro ao copiar _redirects:", error);
  process.exit(1);
}
