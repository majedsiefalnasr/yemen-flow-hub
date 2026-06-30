import { mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { execFileSync } from 'node:child_process'
import { describe, expect, it } from 'vitest'
import { NOT_ELIGIBLE_LABEL } from '../../../constants/workflow'

const script = join(process.cwd(), 'scripts/check-not-eligible-copy.mjs')

function withFixture(files: Record<string, string>, run: (dir: string) => void) {
  const dir = mkdtempSync(join(tmpdir(), 'not-eligible-copy-'))
  try {
    for (const [name, contents] of Object.entries(files)) {
      writeFileSync(join(dir, name), contents)
    }
    run(dir)
  } finally {
    rmSync(dir, { recursive: true, force: true })
  }
}

describe('check-not-eligible-copy guard', () => {
  it('fails when user-facing copy contains banned rejection synonyms', () => {
    withFixture(
      {
        'Bad.vue': '<template><p>مرفوض</p></template>',
      },
      (dir) => {
        expect(() => execFileSync('node', [script, dir], { stdio: 'pipe' })).toThrow()
      },
    )
  })

  it('fails when a static v-bound attribute contains banned rejection synonyms', () => {
    withFixture(
      {
        'BadBound.vue': `<template><input :placeholder="'مرفوض'" /></template>`,
      },
      (dir) => {
        expect(() => execFileSync('node', [script, dir], { stdio: 'pipe' })).toThrow()
      },
    )
  })

  it('passes when user-facing copy uses the canonical Not Eligible label', () => {
    withFixture(
      {
        'Good.vue': `<template><p>${NOT_ELIGIBLE_LABEL}</p></template>`,
      },
      (dir) => {
        expect(() => execFileSync('node', [script, dir], { stdio: 'pipe' })).not.toThrow()
      },
    )
  })

  it('passes against the current app source tree', () => {
    expect(() => execFileSync('node', [script, 'app'], { stdio: 'pipe' })).not.toThrow()
  })
})
