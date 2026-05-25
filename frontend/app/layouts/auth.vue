<template>
  <div class="auth-shell">
    <main class="auth-main">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  background-color: var(--background);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.auth-main {
  width: 100%;
  max-width: 400px;
}
</style>
