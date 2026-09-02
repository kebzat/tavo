import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { readFileSync, writeFileSync, readdirSync, rmSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const BUILD_DIR = 'public/build';

/**
 * Vyhodí z vygenerovaného písma variantu WOFF.
 *
 * Bunny vrací ke každému řezu dvě @font-face pravidla — WOFF2 a WOFF. Obě mají
 * stejnou rodinu, váhu, řez i unicode-range, takže podle kaskády vyhrává to
 * poslední, tedy WOFF. Prohlížeč pak stáhne WOFF (o třetinu větší) a předem
 * načtené WOFF2 zahodí — tedy dvakrát tolik dat, než je potřeba.
 *
 * WOFF2 umí každý prohlížeč od roku 2016 a i ty starší tady stejně nedojedou,
 * protože web stojí na `color-mix(in oklab)` a `aspect-ratio`. Zálohu proto
 * po sestavení zahodíme.
 */
function woff2Only() {
    const dropWoffFaces = (css) =>
        css.replace(/@font-face\s*\{[^}]*format\("woff"\)[^}]*\}\n*/g, '');

    return {
        name: 'tavo-woff2-only',
        enforce: 'post',
        apply: 'build',
        closeBundle() {
            const manifestPath = join(BUILD_DIR, 'fonts-manifest.json');

            if (!existsSync(manifestPath)) {
                return;
            }

            const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
            const style = manifest.style ?? {};

            if (style.file) {
                const cssPath = join(BUILD_DIR, style.file);
                writeFileSync(cssPath, dropWoffFaces(readFileSync(cssPath, 'utf8')));
            }

            if (style.inline) {
                style.inline = dropWoffFaces(style.inline);
            }

            for (const [family, css] of Object.entries(style.familyStyles ?? {})) {
                style.familyStyles[family] = dropWoffFaces(css);
            }

            writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));

            // Osiřelé soubory i jejich záznamy v hlavním manifestu.
            const assets = join(BUILD_DIR, 'assets');
            const removed = readdirSync(assets).filter((file) => file.endsWith('.woff'));

            for (const file of removed) {
                rmSync(join(assets, file));
            }

            const viteManifestPath = join(BUILD_DIR, 'manifest.json');

            if (existsSync(viteManifestPath)) {
                const viteManifest = JSON.parse(readFileSync(viteManifestPath, 'utf8'));

                for (const key of Object.keys(viteManifest)) {
                    if (key.endsWith('.woff')) {
                        delete viteManifest[key];
                    }
                }

                writeFileSync(viteManifestPath, JSON.stringify(viteManifest, null, 2));
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/tools/theme.css'],
            refresh: true,
            fonts: [
                // Self-hostováno (Bunny Fonts, EU, bez trackingu) — nikdy Google Fonts CDN.
                //
                // `subsets` musí obsahovat latin-ext, jinak by se č, ď, ě, ň, ř, š,
                // ť, ů a ž vysázely náhradním systémovým písmem (v latin subsetu
                // nejsou) a slova by se rozpadla do dvou fontů.
                //
                // Váhy odpovídají tomu, co šablony opravdu používají (font-light až
                // font-extrabold). 900 se nikde nepoužívá, tak ji nestahujeme.
                //
                // Předem načítáme jen dva řezy, které jsou na první obrazovce:
                // 400 pro běžný text a 800 pro nadpisy. Zbytek si prohlížeč vyžádá
                // sám, až na něj narazí — font-display: swap drží text čitelný.
                bunny('Montserrat', {
                    weights: [300, 400, 500, 600, 700, 800],
                    styles: ['normal', 'italic'],
                    subsets: ['latin', 'latin-ext'],
                    preload: [{ weight: 400 }, { weight: 800 }],
                }),
            ],
        }),
        tailwindcss(),
        woff2Only(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
