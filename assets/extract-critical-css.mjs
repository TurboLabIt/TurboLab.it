#!/usr/bin/env node

import { generate } from 'critical';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// ES modules don't have __dirname, so recreate it
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Get base URL from command line argument
const BASE_URL = process.argv[2];

if (!BASE_URL) {
    console.error('❌ Error: Base URL is required!');
    process.exit(1);
}

// Validate URL format
try {
    new URL(BASE_URL);
} catch (error) {
    console.error(`❌ Error: Invalid URL "${BASE_URL}"\n`);
    console.log('Please provide a valid URL (e.g., https://turbolab.it)');
    process.exit(1);
}

const OUTPUT_DIR = path.join(__dirname, '../public/build/critical');

console.log(`🌐 Using base URL: ${BASE_URL}\n`);

const pages = [
    {
        name: 'home',
        url: `${BASE_URL}/`,
        template: 'home/index.html.twig',
        dimensions: [
            { width: 1920, height: 1080 },
            { width: 1366, height: 768 },
            { width: 375, height: 667 }
        ]
    },
    {
        name: 'article',
        url: `${BASE_URL}/windows-update-280/supporto-windows-10-terminato-cosa-significa-devo-abbandonare-windows-10-video-spiegazione-4265`,
        template: 'article/index.html.twig',
        dimensions: [
            { width: 1920, height: 1080 },
            { width: 1366, height: 768 },
            { width: 375, height: 667 }
        ]
    }
];

if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

async function extractCriticalCSS() {
    console.log('🔍 Starting Critical CSS extraction...\n');

    for (const page of pages) {
        try {
            console.log(`📄 Processing: ${page.name} (${page.url})`);

            const { css } = await generate({
                base: path.join(__dirname, '../public/'),
                src: page.url,
                target: {
                    css: `${OUTPUT_DIR}/${page.name}.css`,
                    uncritical: `${OUTPUT_DIR}/${page.name}.full.css`
                },
                inline: false,
                dimensions: page.dimensions,
                penthouse: {
                    timeout: 60000,
                    forceInclude: [
                        '.container',
                        '.header',
                        '.nav',
                        '.main-content',
                        '.article-title',
                        '.article-meta'
                    ]
                },
                ignore: {
                    atrule: ['@font-face', '@media print'],
                    rule: [/\.hidden/, /\.mobile-only/],
                    decl: (node, value) => /url\(/.test(value)
                }
            });

            const outputPath = path.join(OUTPUT_DIR, `${page.name}.css`);
            fs.writeFileSync(outputPath, css);

            console.log(`✅ Generated: ${outputPath} (${(css.length / 1024).toFixed(2)} KB)\n`);
        } catch (error) {
            console.error(`❌ Error processing ${page.name}:`, error.message);
            process.exit(1);
        }
    }

    console.log('✨ Critical CSS extraction completed successfully!');
}

extractCriticalCSS().catch(error => {
    console.error('💥 Fatal error:', error);
    process.exit(1);
});
