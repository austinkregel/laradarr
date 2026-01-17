<script setup>
defineProps({
  label: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['remove']);
</script>

<template>
  <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-3 py-1 text-xs">
    <span class="truncate max-w-48">{{ label }}</span>
    <button
      type="button"
      class="rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 px-1"
      @click="emit('remove')"
      aria-label="Remove filter"
    >
      ×
    </button>
  </span>
</template>







