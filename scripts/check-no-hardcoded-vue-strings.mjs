#!/usr/bin/env node
/**
 * Detect hardcoded accessibility strings in Vue SFCs.
 *
 * Per P0-05 acceptance and ENGINEERING_STANDARDS.md §7.2, every user-
 * facing string must come from a locale file. This script catches the
 * common bug where a contributor writes `aria-label="Open menu"`
 * instead of `:aria-label="t('shell.common.open_menu')"`. The Vue
 * templates in the shell use vue-i18n, so the fix is always:
 *
 *     :aria-label="t('your.key.here')"
 *
 * and add the corresponding key to resources/js/locales/<locale>.json
 * for every supported locale (the i18n parity check
 * `scripts/check-i18n-keys.mjs` will then fail if a key is added to
 * only one locale).
 *
 * Scope: aria-label and aria-labelledby with literal (non-binding)
 * string values. <label> text and other user-visible copy are
 * deliberately not flagged here; code review covers those because
 * false positives are too expensive (placeholder strings, version
 * numbers, brand names, and "—" all live as text nodes).
 *
 * Run: npm run lint:no-hardcoded
 */
import { readFile, readdir } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, resolve, relative, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, "..");
const target = resolve(root, "resources/js");

const ARIA_ATTRS = ["aria-label", "aria-labelledby"];
const RE = new RegExp(
    // The (?<!:) lookbehind excludes Vue bindings like :aria-label="t('...')"
    // — those are translated, only literal aria-label="..." is the bug.
    String.raw`(?<!:)\b(` + ARIA_ATTRS.join("|") + String.raw`)="([^"]*)"`,
    "g",
);

/** @type {{file: string, line: number, match: string}[]} */
const violations = [];

async function* walk(dir) {
    for (const entry of await readdir(dir, { withFileTypes: true })) {
        const p = join(dir, entry.name);
        if (entry.isDirectory()) {
            yield* walk(p);
        } else if (entry.isFile() && p.endsWith(".vue")) {
            yield p;
        }
    }
}

function lineOf(content, idx) {
    let line = 1;
    for (let i = 0; i < idx; i++) if (content[i] === "\n") line++;
    return line;
}

for await (const file of walk(target)) {
    const content = await readFile(file, "utf8");
    for (const m of content.matchAll(RE)) {
        const attr = m[1];
        const value = m[2];
        if (!value) continue;                       // aria-label=""
        if (!/^[A-Za-z]/.test(value)) continue;     // aria-label="1" or numeric ids
        violations.push({
            file: relative(root, file),
            line: lineOf(content, m.index),
            match: `${attr}="${value}"`,
        });
    }
}

if (violations.length > 0) {
    console.error("");
    console.error(
        `\u2716 hardcoded aria strings in Vue components (${violations.length}):`,
    );
    for (const v of violations) {
        console.error(`  ${v.file}:${v.line}  ${v.match}`);
    }
    console.error("");
    console.error("Move the string into resources/js/locales/<locale>.json");
    console.error("and reference it via :aria-label=\"t('your.key.here')\".");
    process.exit(1);
}

console.log(
    "\u2714 no hardcoded aria-label / aria-labelledby in Vue components",
);