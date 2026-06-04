import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt({
  ignores: [
    '.nuxt/**',
    '.output/**',
    '**/.playwright-cli/**',
    'coverage/**',
    'node_modules/**',
    'playwright-report/**',
    'test-results/**',
  ],
  rules: {
    '@typescript-eslint/ban-ts-comment': 'warn',
    '@typescript-eslint/consistent-type-imports': 'off',
    '@typescript-eslint/no-dynamic-delete': 'warn',
    '@typescript-eslint/no-explicit-any': 'warn',
    '@typescript-eslint/no-invalid-void-type': 'warn',
    '@typescript-eslint/no-unused-vars': [
      'warn',
      {
        argsIgnorePattern: '^_',
        caughtErrorsIgnorePattern: '^_',
        varsIgnorePattern: '^_',
      },
    ],
    'import/first': 'warn',
    'import/no-duplicates': 'warn',
    'no-constant-binary-expression': 'warn',
    'no-empty': 'warn',
    'nuxt/prefer-import-meta': 'warn',
    'prefer-const': 'warn',
    'vue/html-self-closing': 'warn',
    'vue/no-multiple-template-root': 'warn',
    'vue/no-template-shadow': 'warn',
    'vue/no-unused-vars': 'warn',
    'vue/no-v-html': 'warn',
    'vue/require-default-prop': 'warn',
    'vue/valid-template-root': 'warn',
  },
})
