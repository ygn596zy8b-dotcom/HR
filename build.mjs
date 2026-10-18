// Bundles every assets/src/<name>.mjs in the theme and the plugin into assets/js/<name>.js (minified IIFE).
// Files starting with "_" are shared modules, not entry points. Run: npm run build   (add --check in CI)
import { build } from 'esbuild';
import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const roots = ['wp-content/themes/headlinerec-child/assets', 'wp-content/plugins/hr-platform/assets'];
const check = process.argv.includes('--check');
let stale = 0;

for (const root of roots) {
  const src = join(root, 'src');
  if (!existsSync(src)) continue;
  for (const file of readdirSync(src).filter((f) => f.endsWith('.mjs') && !f.startsWith('_'))) {
    const outfile = join(root, 'js', file.replace(/\.mjs$/, '.js'));
    const result = await build({
      entryPoints: [join(src, file)],
      bundle: true,
      format: 'iife',
      target: 'es2020',
      minify: true,
      legalComments: 'none',
      write: !check,
      outfile,
      logLevel: 'warning',
    });
    if (check) {
      const fresh = result.outputFiles[0].text;
      const current = existsSync(outfile) ? readFileSync(outfile, 'utf8') : '';
      if (fresh !== current) {
        console.error(`stale: ${outfile} (run npm run build)`);
        stale++;
      }
    } else {
      console.log(`built ${outfile}`);
    }
  }
}
if (stale) process.exit(1);
