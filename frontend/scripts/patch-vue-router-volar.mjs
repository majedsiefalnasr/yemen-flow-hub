import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const rootDir = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const targetPackageJsonPath = join(rootDir, 'node_modules', 'vue-router', 'package.json')
const targetVolarDir = join(rootDir, 'node_modules', 'vue-router', 'dist', 'volar')
const sourceVolarDir = join(rootDir, 'node_modules', 'nuxt', 'node_modules', 'vue-router', 'dist', 'volar')

const volarEntrypoints = [
  {
    exportKey: './volar/sfc-route-blocks',
    scriptFile: 'sfc-route-blocks.cjs',
    typesFile: 'sfc-route-blocks.d.cts',
  },
  {
    exportKey: './volar/sfc-typed-router',
    scriptFile: 'sfc-typed-router.cjs',
    typesFile: 'sfc-typed-router.d.cts',
  },
]

const packageJson = JSON.parse(await readFile(targetPackageJsonPath, 'utf8'))

if (packageJson.exports?.['./volar/sfc-route-blocks']) {
  process.exit(0)
}

await mkdir(targetVolarDir, { recursive: true })

for (const { exportKey, scriptFile, typesFile } of volarEntrypoints) {
  await copyFile(join(sourceVolarDir, scriptFile), join(targetVolarDir, scriptFile))
  await copyFile(join(sourceVolarDir, typesFile), join(targetVolarDir, typesFile))

  packageJson.exports[exportKey] = {
    types: `./dist/volar/${typesFile}`,
    default: `./dist/volar/${scriptFile}`,
  }
}

packageJson.typesVersions ??= {}
packageJson.typesVersions['*'] ??= {}
packageJson.typesVersions['*']['volar/*'] ??= ['./dist/volar/*.d.cts']

await writeFile(targetPackageJsonPath, `${JSON.stringify(packageJson, null, 2)}\n`)
