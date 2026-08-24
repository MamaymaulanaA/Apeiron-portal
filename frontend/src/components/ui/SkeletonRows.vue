<script setup>
/** Placeholder saat data sedang dimuat. */
defineProps({
  rows: { type: Number, default: 5 },
  height: { type: Number, default: 48 },
})
</script>

<template>
  <div class="skeleton-stack" aria-hidden="true">
    <div
      v-for="i in rows"
      :key="i"
      class="skeleton"
      :style="{ height: height + 'px' }"
    />
  </div>
</template>

<style scoped>
.skeleton-stack {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-5) var(--sp-6);
}

.skeleton {
  border-radius: var(--r-md);
  background: linear-gradient(
    90deg,
    var(--bg-subtle) 25%,
    var(--bg-hover) 50%,
    var(--bg-subtle) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s var(--ease) infinite;
}
</style>
