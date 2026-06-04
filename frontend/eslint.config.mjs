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
      '@typescript-eslint/consistent-type-imports': 'off',
      '@typescript-eslint/no-dynamic-delete': 'error',
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/no-invalid-void-type': 'error',
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
      'vue/html-self-closing': 'off',
      'vue/no-multiple-template-root': 'off',
      'vue/no-template-shadow': 'error',
      'vue/no-unused-vars': 'error',
      'vue/no-v-html': 'error',
      'vue/require-default-prop': 'off',
      'vue/valid-template-root': 'error',
    },
  },
  {
    files: ['app/components/ui/**', 'app/tests/**', 'tests/**', 'app/plugins/00.visual-bypass*.ts'],
    rules: {
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
)
