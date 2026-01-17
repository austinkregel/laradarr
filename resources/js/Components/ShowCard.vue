<script setup>
import { Link } from '@inertiajs/vue3';
import { CheckCircleIcon } from '@heroicons/vue/16/solid';
import LikeButton from '@/Components/LikeButton.vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import utc from 'dayjs/plugin/utc';

dayjs.extend(relativeTime);
dayjs.extend(utc);

const props = defineProps({
  show: {
    type: Object,
    required: true,
  },
  redirect: {
    type: String,
    required: true,
  },
  showCompleted: {
    type: Boolean,
    default: false,
  },
  showWatchedInfo: {
    type: Boolean,
    default: false,
  },
  showCategoryCount: {
    type: Boolean,
    default: false,
  },
  variant: {
    type: String,
    default: 'dashboard', // 'dashboard', 'browse', or 'discover'
    validator: (value) => ['dashboard', 'browse', 'discover'].includes(value),
  },
});

const lastWatched = (show) => {
  if (!show.last_watched_at) {
    return 'Not watched';
  }
  return dayjs.utc(show.last_watched_at).fromNow();
};

const countWatchedEpisodes = (show) => {
  return parseInt(show.watchers_count);
};
</script>

<template>
  <div class="relative bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
    <CheckCircleIcon
      v-if="showCompleted && show.completed_count > 0"
      class="w-6 h-6 text-green-400 m-4 z-10 absolute right-0"
    />
    <div class="absolute top-2 left-2 z-10">
      <LikeButton
        :follow="show"
        type="App\Models\Show"
        :redirect="redirect"
        class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-lg px-2 py-1 text-xs dark:text-gray-200"
      />
    </div>
    <div class="bg-gray-950">
      <img
        v-if="show.poster_image"
        :src="show.poster_image"
        :alt="show.name"
        :class="variant === 'discover' || variant === 'browse' ? 'h-64 w-full object-cover' : 'max-h-64 object-cover mx-auto'"
      />
      <div
        v-else
        :class="variant === 'discover' || variant === 'browse'
          ? 'h-64 w-full flex items-center justify-center bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900'
          : 'h-64 flex items-center justify-center bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900'"
      >
        <div class="px-3 text-center">
          <div class="text-sm font-semibold text-gray-100 line-clamp-2">{{ show.name }}</div>
          <div v-if="show.release_year" class="mt-1 text-[10px] text-gray-300">{{ show.release_year }}</div>
        </div>
      </div>
    </div>
    <div :class="variant === 'discover' || variant === 'browse' ? 'p-3' : ''">
      <Link
        :href="`/shows/${show.id}`"
        :class="variant === 'discover' || variant === 'browse' 
          ? 'block text-sm font-semibold text-gray-950 dark:text-gray-100 leading-tight truncate'
          : 'block text-lg font-semibold text-gray-950 dark:text-gray-200 leading-tight px-4 py-2 truncate'"
      >
        {{ show.name }}
      </Link>
      <div
        :class="variant === 'discover' || variant === 'browse'
          ? 'mt-1 text-xs text-gray-600 dark:text-gray-300'
          : 'text-sm text-white px-4'"
      >
        <div>
          {{ show.season_count ?? '—' }} {{ variant === 'discover' || variant === 'browse' ? 'seasons ·' : 'seasons /' }} {{ show.episode_count ?? '—' }} episodes
        </div>
        <div
          v-if="show.episodes_with_media_count !== undefined"
          :class="variant === 'discover' || variant === 'browse'
            ? 'mt-0.5 text-[10px] text-gray-500 dark:text-gray-400'
            : 'text-xs text-gray-400 mt-0.5'"
        >
          {{ show.episodes_with_media_count }} {{ show.episodes_with_media_count === 1 ? 'episode' : 'episodes' }} available
        </div>
      </div>
      <div :class="variant === 'discover' || variant === 'browse' ? 'mt-2 flex flex-wrap gap-1' : 'px-4 mt-2 flex flex-wrap gap-1'">
        <span
          v-for="cat in (show.categories ?? []).slice(0, 3)"
          :key="cat.id"
          :class="variant === 'discover' || variant === 'browse'
            ? 'text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200'
            : 'text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-white'"
        >
          {{ cat.name }}
        </span>
        <span
          v-if="(showCategoryCount || variant === 'discover' || variant === 'browse') && (show.categories ?? []).length > 3"
          :class="variant === 'discover' || variant === 'browse'
            ? 'text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200'
            : 'text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-white'"
        >
          +{{ (show.categories ?? []).length - 3 }}
        </span>
      </div>
      <div
        :class="variant === 'discover' || variant === 'browse' ? 'mt-2 flex flex-wrap gap-1' : 'px-4 mt-2 flex flex-wrap gap-1'"
        v-if="(show.content_warnings ?? show.contentWarnings ?? []).length"
      >
        <span
          v-for="w in (show.content_warnings ?? show.contentWarnings ?? []).slice(0, 2)"
          :key="w.id"
          :class="variant === 'discover' || variant === 'browse'
            ? 'text-[10px] px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200'
            : 'text-[10px] px-2 py-0.5 rounded-full bg-red-500/20 text-red-200'"
        >
          {{ w.slug === '18+' ? '18+' : w.name }}
        </span>
      </div>
      <div
        :class="variant === 'discover' || variant === 'browse'
          ? 'mt-2 flex items-center justify-between text-[10px] text-gray-600 dark:text-gray-300'
          : 'px-4 mt-2 text-[11px] text-gray-300 flex items-center justify-between'"
      >
        <div v-if="show.tmdb_rating">TMDB {{ show.tmdb_rating }}</div>
        <div v-else-if="show.trakt_rating">Trakt {{ show.trakt_rating }}</div>
        <div v-else-if="show.imdb_rating">IMDb {{ show.imdb_rating }}</div>
        <div v-else>—</div>

        <div
          v-if="(show.available_dub_languages ?? []).length"
          class="truncate max-w-28 text-right"
        >
          {{ show.available_dub_languages.slice(0, 2).join(', ') }}
        </div>
      </div>
      <div
        v-if="showWatchedInfo"
        :class="variant === 'discover' || variant === 'browse' ? 'text-sm text-white p-3 pt-2' : 'text-sm text-white px-4 pb-4'"
      >
        {{ lastWatched(show) }} / {{ countWatchedEpisodes(show) }} episodes watched
      </div>
    </div>
  </div>
</template>

