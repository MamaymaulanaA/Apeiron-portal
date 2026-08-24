<script setup>
import { CircleCheck, CircleX, Info, TriangleAlert, X } from 'lucide-vue-next'
import { toasts, dismiss } from '@/stores/toast'

const ICONS = {
  success: CircleCheck,
  error: CircleX,
  warning: TriangleAlert,
  info: Info,
}
const TONES = {
  success: 'tone-ok',
  error: 'tone-err',
  warning: 'tone-warn',
  info: 'tone-brand',
}
</script>

<template>
  <div class="toast-host" role="status" aria-live="polite">
    <TransitionGroup name="toast">
      <div v-for="t in toasts" :key="t.id" class="toast" :class="TONES[t.type]">
        <component :is="ICONS[t.type]" class="toast-icon" :size="19" />
        <p class="toast-msg">{{ t.message }}</p>
        <button
          class="toast-close"
          type="button"
          aria-label="Tutup"
          @click="dismiss(t.id)"
        >
          <X :size="15" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-host {
  position: fixed;
  top: var(--sp-5);
  right: var(--sp-5);
  z-index: var(--z-toast);
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  width: min(380px, calc(100vw - 32px));
  pointer-events: none;
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
  padding: var(--sp-4);
  background: var(--bg-surface);
  border: 1px solid var(--tone-border);
  border-left: 3px solid var(--tone-fg);
  border-radius: var(--r);
  box-shadow: var(--sh-pop);
  pointer-events: auto;
}

.toast-icon {
  color: var(--tone-fg);
  flex-shrink: 0;
  margin-top: 1px;
}

.toast-msg {
  flex: 1;
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
  line-height: var(--lh-snug);
}

.toast-close {
  color: var(--text-muted);
  flex-shrink: 0;
  padding: 2px;
  border-radius: var(--r-sm);
}
.toast-close:hover {
  color: var(--text-heading);
  background: var(--bg-hover);
}

.toast-enter-active,
.toast-leave-active {
  transition: all var(--dur) var(--ease);
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(24px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(24px);
}
.toast-leave-active {
  position: absolute;
}

@media (max-width: 640px) {
  .toast-host {
    top: auto;
    bottom: var(--sp-4);
    left: var(--sp-4);
    right: var(--sp-4);
    width: auto;
  }
}
</style>
