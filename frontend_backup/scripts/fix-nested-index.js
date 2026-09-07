// Next static export writes "route.html" next to a "route/" directory
// whenever "route" has nested children (e.g. admin.html + admin/login.html).
// nginx's `try_files $uri $uri/ /index.php` matches the directory before
// falling through to Laravel, and 404s directly if it has no index file of
// its own -- the PHP fallback in routes/web.php never gets a chance to run.
// Copy the sibling .html into <dir>/index.html so the directory is
// self-contained.
const fs = require("fs");
const path = require("path");

const outDir = path.join(__dirname, "..", "out");

function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (!entry.isDirectory()) continue;

    const full = path.join(dir, entry.name);
    const siblingHtml = `${full}.html`;
    const indexHtml = path.join(full, "index.html");

    if (fs.existsSync(siblingHtml) && !fs.existsSync(indexHtml)) {
      fs.copyFileSync(siblingHtml, indexHtml);
      console.log(
        `fix-nested-index: ${path.relative(outDir, siblingHtml)} -> ${path.relative(outDir, indexHtml)}`,
      );
    }

    walk(full);
  }
}

walk(outDir);
