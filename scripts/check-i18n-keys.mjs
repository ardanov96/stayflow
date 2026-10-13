#!/usr/bin/env node
/**
 * Verify i18n key parity across every supported locale in two places:
 *   - resources/js/locales/<locale>.json  (Vue I18n messages, loaded at runtime)
 *   - lang/<locale>.json                  (Laravel PHP translations)
 * For each set, every locale must have the same set of keys. If a
 * contributor adds a new translation key to, say, en.json but forgets
 * to add it to id.json, this script exits 1 so CI blocks the merge.
 * The script also cross-checks that the two sets agree on which keys
 * exist at all, so we do not end up with a Vue key that has no PHP
 * counterpart or vice versa.
 * Run via `npm run lint:i18n`.
 */
import { readFile, readdir } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, "..");

const SETS = [
    { name: "resources/js/locales", dir: resolve(root, "resources/js/locales") },
    { name: "lang",                 dir: resolve(root, "lang") },
];

function flattenKeys(obj, prefix = "") {
    const out = [];
    for (const [k, v] of Object.entries(obj ?? {})) {
        const path = prefix ? prefix + "." + k : k;
        if (v && typeof v === "object" && !Array.isArray(v)) {
            out.push(...flattenKeys(v, path));
        } else {
            out.push(path);
        }
    }
    return out;
}

async function loadSet(set) {
    const entries = await readdir(set.dir);
    const locales = {};
    for (const file of entries) {
        if (!file.endsWith(".json")) continue;
        const locale = file.replace(/\.json$/, "");
        const raw = await readFile(resolve(set.dir, file), "utf8");
        try {
            locales[locale] = JSON.parse(raw);
        } catch (e) {
            throw new Error("[i18n] " + set.name + "/" + file + ": invalid JSON: " + e.message);
        }
    }
    return { name: set.name, locales };
}

const errors = [];
function fail(msg) { errors.push(msg); }

for (const set of await Promise.all(SETS.map(loadSet))) {
    const names = Object.keys(set.locales).sort();
    if (names.length === 0) {
        console.log("[i18n] " + set.name + ": no locales, skipping");
        continue;
    }
    const union = new Set();
    const perLocale = {};
    for (const locale of names) {
        const keys = flattenKeys(set.locales[locale]).sort();
        perLocale[locale] = new Set(keys);
        for (const k of keys) union.add(k);
    }
    for (const locale of names) {
        const missing = [...union].filter((k) => !perLocale[locale].has(k));
        if (missing.length > 0) {
            fail("[i18n] " + set.name + "/" + locale + ".json is missing " + missing.length + " key(s): " + missing.join(", "));
        }
    }
    console.log("[i18n] " + set.name + ": " + names.length + " locale(s), " + union.size + " key(s) per locale");
}

if (SETS.length === 2) {
    const [a, b] = await Promise.all(SETS.map(loadSet));
    const aUnion = new Set();
    for (const locale of Object.keys(a.locales)) {
        for (const k of flattenKeys(a.locales[locale])) aUnion.add(k);
    }
    const bUnion = new Set();
    for (const locale of Object.keys(b.locales)) {
        for (const k of flattenKeys(b.locales[locale])) bUnion.add(k);
    }
    const onlyInA = [...aUnion].filter((k) => !bUnion.has(k)).sort();
    const onlyInB = [...bUnion].filter((k) => !aUnion.has(k)).sort();
    if (onlyInA.length > 0) {
        fail("[i18n] " + a.name + " has " + onlyInA.length + " key(s) not present in " + b.name + ": " + onlyInA.join(", "));
    }
    if (onlyInB.length > 0) {
        fail("[i18n] " + b.name + " has " + onlyInB.length + " key(s) not present in " + a.name + ": " + onlyInB.join(", "));
    }
}

if (errors.length > 0) {
    console.error("");
    console.error("\u2716 i18n parity check failed (" + errors.length + " issue(s)):");
    for (const e of errors) console.error("  " + e);
    process.exit(1);
}
console.log("");
console.log("\u2714 i18n parity OK");