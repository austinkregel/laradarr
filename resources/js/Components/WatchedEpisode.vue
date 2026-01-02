<script setup>
import {computed} from "vue";
import dayjs from "dayjs";
import relativeTime from 'dayjs/plugin/relativeTime';
import utc from 'dayjs/plugin/utc';
import { Link } from "@inertiajs/vue3";

dayjs.extend(relativeTime);
dayjs.extend(utc);

const { episode } = defineProps({
  'episode': {
    type: Object,
    required: true,
  },
});

const watched = computed(() => episode.watchers.length > 0);
const show = computed(() => episode.show?.[0] || episode.season?.show);
const season = computed(() => episode.season);

const date = (pivot) => {
  if (!pivot?.watched_at) return 'Unknown';
  return dayjs.utc(pivot.watched_at).fromNow();
};

const formatSeasonEpisode = computed(() => {
  const seasonNum = season.value?.season?.toString().padStart(2, '0') || '—';
  const episodeNum = episode.episode_number?.toString().padStart(2, '0') || '—';
  return `S${seasonNum}E${episodeNum}`;
});

const formatRuntime = computed(() => {
  if (!episode.runtime) return null;
  const hours = Math.floor(episode.runtime / 60);
  const minutes = episode.runtime % 60;
  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  }
  return `${minutes}m`;
});
</script>

<template>
  <div class="relative bg-white dark:bg-gray-950 overflow-hidden shadow-xl sm:rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-2xl transition-shadow">
    <div class="flex flex-col h-full">
      <!-- Header with season/episode number and show name -->
      <div class="px-4 pt-4 pb-2">
        <div class="flex items-start justify-between gap-2 mb-2">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs font-mono font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                {{ formatSeasonEpisode }}
              </span>
              <span v-if="formatRuntime" class="text-xs text-gray-500 dark:text-gray-400">
                {{ formatRuntime }}
              </span>
            </div>
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 leading-tight line-clamp-2">
              {{ episode.name || 'Untitled Episode' }}
            </h3>
          </div>
          <span
            v-if="watched"
            class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 flex-shrink-0"
            title="Watched"
          >
            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
          </span>
        </div>
        
        <Link 
          v-if="show?.id" 
          :href="route('show', show.id)" 
          class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:underline transition-colors"
        >
          {{ show?.name }}
        </Link>
        <div v-else class="text-sm text-gray-500 dark:text-gray-400">
          Unknown Show
        </div>
      </div>

      <!-- Footer with watched date -->
      <div class="px-4 pb-4 mt-auto pt-3 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
          <div class="text-xs text-gray-500 dark:text-gray-400">
            Watched {{ date(episode.pivot) }}
          </div>
          <div v-if="episode.aired_at" class="text-xs text-gray-400 dark:text-gray-500">
            Aired {{ dayjs.utc(episode.aired_at).format('MMM D, YYYY') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>

</style>