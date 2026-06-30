#!/usr/bin/env node
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { extname, join, relative } from 'node:path'
import process from 'node:process'

// Arabic forms are matched after normalization (tatweel/diacritics stripped,
// alef/taa unified) so orthographic variants like "رُفِض", "رفـض", "رافض"
// cannot slip past. Latin synonyms stay case-sensitive (Title-case prose) so
// snake_case / SCREAMING_CASE internal identifiers are not false-positives
// (code-review 17-F).
const banned = /مرفوض|رفض|رافض|Rejected|Declined|Disapproved|Not Approved/
const roots = process.argv.slice(2)
const scanRoots = roots.length > 0 ? roots : ['app']
const extensions = new Set(['.ts', '.vue'])
const skipSegments = new Set(['node_modules', '.nuxt', '.output', 'dist', 'coverage', 'tests'])
// Lines carrying this marker are intentional legacy-history literals (e.g. v1
// vote records that must keep "رفض" per the preserve-history decision).
const ALLOW_MARKER = 'not-eligible-copy-allow'
const findings = []

function normalizeArabic(text) {
  return text
    .replace(/[ـ]/g, '') // tatweel
    .replace(/[ً-ْٰ]/g, '') // harakat / diacritics
    .replace(/[آأإ]/g, 'ا') // alef variants -> bare alef
    .replace(/ة/g, 'ه') // taa marbuta -> haa
}

function walk(path) {
  const stat = statSync(path)
  if (stat.isDirectory()) {
    const parts = path.split(/[\\/]/)
    if (parts.some((part) => skipSegments.has(part))) return
    for (const entry of readdirSync(path)) walk(join(path, entry))
    return
  }

  if (!extensions.has(extname(path))) return
  inspect(path)
}

function inspect(path) {
  const source = readFileSync(path, 'utf8')
  const lines = source.split('\n')
  const chunks = extname(path) === '.vue' ? vueChunks(source) : tsChunks(source)

  for (const chunk of chunks) {
    // Scan interpolation contents too (do not blindly strip them).
    const renderedText = normalizeArabic(chunk.text)
    if (!banned.test(renderedText)) continue
    const line = lineNumber(source, chunk.index)
    // Respect an explicit per-line allow marker for legitimate legacy literals.
    if (lines[line - 1]?.includes(ALLOW_MARKER)) continue
    findings.push({ path, line, text: chunk.text.trim() })
  }
}

function vueChunks(source) {
  const chunks = []
  for (const match of source.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)) {
    chunks.push(...tsChunks(match[1] ?? '', (match.index ?? 0) + match[0].indexOf(match[1] ?? '')))
  }
  const template = source.match(/<template[^>]*>([\s\S]*?)<\/template>/)
  if (template?.index == null) return chunks

  const templateStart = template.index
  const body = template[1] ?? ''
  for (const match of body.matchAll(/>[^<]+</g)) {
    const text = match[0]
      .slice(1, -1)
      .replace(/\{\{[\s\S]*?\}\}/g, '')
      .trim()
    if (text) chunks.push({ text, index: templateStart + match.index + 1 })
  }
  for (const match of body.matchAll(
    /\s(?:aria-label|title|placeholder|label|description)="([^"]+)"/g,
  )) {
    chunks.push({ text: match[1], index: templateStart + match.index })
  }
  for (const match of body.matchAll(
    /\s:(?:aria-label|title|placeholder|label|description)="([^"]+)"/g,
  )) {
    chunks.push(...tsChunks(match[1] ?? '', templateStart + (match.index ?? 0)))
  }
  return chunks
}

function tsChunks(source, baseIndex = 0) {
  const chunks = []
  for (const match of source.matchAll(
    /'([^'\\\n]*(?:\\.[^'\\\n]*)*)'|"([^"\\\n]*(?:\\.[^"\\\n]*)*)"|`((?:\\.|(?!`)[\s\S])*?)`/g,
  )) {
    chunks.push({
      text: match[1] ?? match[2] ?? match[3] ?? '',
      index: baseIndex + (match.index ?? 0),
    })
  }
  return chunks
}

function lineNumber(source, index) {
  return source.slice(0, index).split('\n').length
}

for (const root of scanRoots) walk(root)

if (findings.length > 0) {
  console.error('Banned rejection synonym(s) found in user-facing frontend copy:')
  for (const finding of findings) {
    console.error(`- ${relative(process.cwd(), finding.path)}:${finding.line}: ${finding.text}`)
  }
  process.exit(1)
}
