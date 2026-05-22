<template>
  <div class="print-shell" dir="rtl">
    <slot />
  </div>
</template>

<style>
.print-shell {
  min-height: 100vh;
  background-color: #f5f5f7;
}

@media print {
  .print-shell {
    background: transparent;
    min-height: unset;
  }
}
</style>
