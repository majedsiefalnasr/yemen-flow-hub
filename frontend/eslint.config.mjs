import withNuxt from './.nuxt/eslint.config.mjs'
import unusedImports from 'eslint-plugin-unused-imports'

export default withNuxt(
  {
    ignores: [
      '.nuxt/**',
      '.output/**',
      '**/.playwright-cli/**',
      'coverage/**',
      'node_modules/**',
      'playwright-report/**',
      'test-results/**',
    ],
    plugins: {
      'unused-imports': unusedImports,
    },
    rules: {
      '@typescript-eslint/ban-ts-comment': 'error',
      // Nuxt/Vue auto-imports and generated types make this noisy; enable after import style is normalized.
      '@typescript-eslint/consistent-type-imports': 'off',
      '@typescript-eslint/no-dynamic-delete': 'error',
      // Existing API/table adapter code still has typed-refactor debt; enable by folder after replacing loose response shapes.
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/no-invalid-void-type': 'error',
      // Replaced by eslint-plugin-unused-imports so imports can be auto-fixed while unused locals remain errors.
      '@typescript-eslint/no-unused-vars': 'off',
      'unused-imports/no-unused-imports': 'error',
      'unused-imports/no-unused-vars': [
        'error',
        {
          argsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
        },
      ],
      'import/first': 'error',
      'import/no-duplicates': 'error',
      'no-constant-binary-expression': 'error',
      'no-empty': 'error',
      'nuxt/prefer-import-meta': 'warn',
      'prefer-const': 'error',
      // Prettier owns void-element formatting and emits <input /> / <img />; avoid formatter-vs-linter churn.
      'vue/html-self-closing': 'off',
      // Vue 3 supports fragments, and several Nuxt/shadcn patterns intentionally use multiple roots.
      'vue/no-multiple-template-root': 'off',
      'vue/no-template-shadow': 'error',
      'vue/no-unused-vars': 'error',
      'vue/no-v-html': 'error',
      // TypeScript optional props and withDefaults already express defaults; this is noisy in shadcn/reka wrappers.
      'vue/require-default-prop': 'off',
      'vue/valid-template-root': 'error',
    },
  },
  {
    files: ['app/components/ui/**', 'app/tests/**', 'tests/**', 'app/plugins/00.visual-bypass*.ts'],
    rules: {
      // UI wrappers, tests, and visual-bypass adapters intentionally use broad mock/component payloads.
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
)
