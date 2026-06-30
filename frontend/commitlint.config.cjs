module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'scope-enum': [
      2,
      'always',
      ['auth', 'backend', 'docs', 'frontend', 'repo', 'settings', 'testing', 'ui', 'workflow'],
    ],
    'scope-empty': [2, 'never'],
    'subject-case': [0],
  },
}
