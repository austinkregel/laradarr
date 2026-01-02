<script setup>
import { Link } from "@inertiajs/vue3";

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  recommendations: {
    type: Array,
    required: true,
  },
  viewAllHref: {
    type: String,
    default: null,
  },
  entryKey: {
    type: String,
    default: 'show',
  },
  linkPrefix: {
    type: String,
    default: 'shows',
  },
});

const formatScore = (score) => {
  if (typeof score !== 'number') {
    return '—';
  }

  return `${Math.round(Math.min(Math.max(score, 0), 1) * 100)}%`;
};

const entryData = (entry) => entry[props.entryKey];
</script>

<template>
  <section class="bg-white/80 dark:bg-gray-900/80 shadow rounded-lg py-6 px-6">
    <header class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ title }}</h3>
      <Link
        v-if="viewAllHref"
        :href="viewAllHref"
        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
      >
        View all
      </Link>
    </header>

    <div v-if="recommendations.length > 0" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
      <div
        v-for="entry in recommendations"
        :key="entryData(entry)?.id ?? entryData(entry)?.name ?? entry.score"
        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden border border-gray-200 dark:border-gray-700"
      >
        <div class="h-40 bg-gray-950">
          <img
            v-if="entryData(entry)?.poster_image"
            :src="props.entryKey === 'movie' ? (entryData(entry).poster_image?.replace('poster.jpg', 'poster-500.jpg') ?? entryData(entry).poster_image) : entryData(entry).poster_image"
            :alt="entryData(entry)?.name ?? 'Recommendation artwork'"
            class="h-full w-full object-cover"
          />
        </div>
        <div class="px-4 py-3">
          <div class="flex items-center justify-between">
            <Link
              v-if="entryData(entry)?.id"
              :href="props.linkPrefix ? `/${props.linkPrefix}/${entryData(entry).id}` : `/movies/${entryData(entry).id}`"
              class="text-base font-semibold text-gray-900 dark:text-gray-100 hover:underline"
            >
              {{ entryData(entry)?.name ?? (props.entryKey === 'movie' ? 'Movie' : 'Show') }}
            </Link>
            <div
              v-else
              class="text-base font-semibold text-gray-900 dark:text-gray-100"
            >
              {{ entryData(entry)?.name ?? 'Recommendation' }}
            </div>
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
              {{ formatScore(entry.score) }}
            </span>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ (entryData(entry)?.genres ?? []).slice(0, 2).join(', ') }}
          </p>
          <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">
            {{ entryData(entry)?.release_year ?? 'Unknown year' }}
          </div>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-12">
      <div class="text-gray-400 dark:text-gray-500">
        <svg class="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
        </svg>
        <p class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No recommendations available</p>
        <p class="text-sm text-gray-500 dark:text-gray-400">We need more information about your preferences to generate recommendations</p>
      </div>
    </div>
  </section>
</template>

