<script setup>
/**
 * Badge status transaksi.
 * Label & warna diambil dari peta yang mencerminkan getStatusLabel() backend.
 *
 * Ikon di-import eksplisit (bukan namespace import) supaya bundler hanya
 * memasukkan empat ikon ini, bukan seluruh pustaka Lucide.
 */
import { computed } from 'vue'
import { Clock, CircleCheck, CircleX, ServerCrash, Circle } from 'lucide-vue-next'
import { statusMeta } from '@/lib/format'

const props = defineProps({
  status: { type: String, required: true },
  /** Endpoint user mengirim status_label; kalau ada, pakai itu. */
  label: { type: String, default: '' },
})

const ICONS = { Clock, CircleCheck, CircleX, ServerCrash, Circle }

const meta = computed(() => statusMeta(props.status))
const text = computed(() => props.label || meta.value.label)
const IconComp = computed(() => ICONS[meta.value.icon] || Circle)
</script>

<template>
  <span class="badge" :class="meta.tone">
    <component :is="IconComp" :size="13" :stroke-width="2.4" />
    {{ text }}
  </span>
</template>
