<script setup>
/** Dialog modal generik: header, slot isi, slot footer. */
import { watch, onBeforeUnmount } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  size: { type: String, default: 'md' }, // sm | md | lg
})
const emit = defineEmits(['close'])

function onKey(e) {
  if (e.key === 'Escape' && props.open) emit('close')
}

watch(
  () => props.open,
  (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
    if (open) document.addEventListener('keydown', onKey)
    else document.removeEventListener('keydown', onKey)
  }
)

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open" class="modal-root" @click.self="emit('close')">
        <div
          class="modal"
          :class="`modal-${size}`"
          role="dialog"
          aria-modal="true"
        >
          <header class="modal-head">
            <div class="modal-head-text">
              <h2>{{ title }}</h2>
              <p v-if="subtitle" class="text-caption">{{ subtitle }}</p>
            </div>
            <button
              class="modal-close"
              type="button"
              aria-label="Tutup"
              @click="emit('close')"
            >
              <X :size="18" />
            </button>
          </header>

          <div class="modal-body scroll-area">
            <slot />
          </div>

          <footer v-if="$slots.footer" class="modal-foot">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-root {
  position: fixed;
  inset: 0;
  z-index: var(--z-modal);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--sp-4);
  background: var(--bg-overlay);
  backdrop-filter: blur(3px);
}

.modal {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-height: calc(100dvh - 2 * var(--sp-8));
  background: var(--bg-surface);
  border-radius: var(--r);
  box-shadow: var(--sh-pop);
  overflow: hidden;
}

.modal-sm {
  max-width: 420px;
}
.modal-md {
  max-width: 560px;
}
.modal-lg {
  max-width: 760px;
}

.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--sp-4);
  padding: var(--sp-5) var(--sp-6);
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}

.modal-head-text h2 {
  font-size: var(--fs-h3);
}
.modal-head-text p {
  margin-top: 2px;
}

.modal-close {
  padding: var(--sp-2);
  border-radius: var(--r-sm);
  color: var(--text-muted);
  flex-shrink: 0;
  transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
}
.modal-close:hover {
  background: var(--bg-hover);
  color: var(--text-heading);
}

.modal-body {
  padding: var(--sp-6);
  flex: 1;
  min-height: 0;
}

.modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--sp-3);
  padding: var(--sp-4) var(--sp-6);
  background: var(--bg-subtle);
  border-top: 1px solid var(--border);
  flex-shrink: 0;
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity var(--dur) var(--ease);
}
.modal-enter-active .modal,
.modal-leave-active .modal {
  transition: transform var(--dur) var(--ease);
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
.modal-enter-from .modal,
.modal-leave-to .modal {
  transform: translateY(12px) scale(0.98);
}

@media (max-width: 640px) {
  .modal-root {
    padding: 0;
    align-items: flex-end;
  }
  .modal {
    max-height: 92dvh;
    border-radius: var(--r) var(--r) 0 0;
  }
  .modal-foot {
    flex-direction: column-reverse;
  }
  .modal-foot :deep(.btn) {
    width: 100%;
  }
}
</style>
