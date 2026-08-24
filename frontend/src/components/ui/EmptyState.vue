<script setup>
import { Inbox } from 'lucide-vue-next'

defineProps({
  icon: { type: [Object, Function], default: () => Inbox },
  title: { type: String, default: 'Belum ada data' },
  description: { type: String, default: '' },
  tone: { type: String, default: 'tone-blue' },
})
</script>

<template>
  <div class="empty" :class="tone">
    <span class="icon-chip empty-icon">
      <component :is="icon" :size="24" :stroke-width="1.9" />
    </span>
    <h3>{{ title }}</h3>
    <p v-if="description" class="text-caption">{{ description }}</p>
    <div v-if="$slots.action" class="empty-action">
      <slot name="action" />
    </div>
  </div>
</template>

<style scoped>
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-12) var(--sp-6);
  text-align: center;
}

.empty-icon {
  width: 56px;
  height: 56px;
  border-radius: var(--r-lg);
  margin-bottom: var(--sp-2);
}

.empty h3 {
  font-size: var(--fs-h3);
}

.empty p {
  max-width: 42ch;
}

.empty-action {
  margin-top: var(--sp-4);
}
</style>
