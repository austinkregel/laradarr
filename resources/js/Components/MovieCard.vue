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
  movie: {
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
    default: 'dashboard', // 'dashboard' or 'discover'
    validator: (value) => ['dashboard', 'discover'].includes(value),
  },
});

const lastWatched = (movie) => {
  // Check if movie has watchers with watched_at pivot
  if (movie.watchers && movie.watchers.length > 0 && movie.watchers[0].pivot?.watched_at) {
    return dayjs.utc(movie.watchers[0].pivot.watched_at).fromNow();
  }
  return 'Not watched';
};

const isWatched = (movie) => {
  return movie.watchers && movie.watchers.length > 0;
};
</script>
<template>
  <div class="relative bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
    <CheckCircleIcon
      v-if="showCompleted && movie.completed_count > 0"
      class="w-6 h-6 text-green-400 m-4 z-10 absolute right-0"
    />
    <div class="absolute top-2 left-2 z-10">
      <LikeButton
        :follow="movie"
        type="App\Models\Movie"
        :redirect="redirect"
        class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-lg px-2 py-1 text-xs dark:text-gray-200"
      />
    </div>
    <div class="bg-gray-950">
      <img
        :src="movie.poster_image?.replace('poster.jpg', 'poster-500.jpg') ?? movie.poster_image"
        :alt="movie.name"
        :class="variant === 'discover' ? 'h-64 w-full object-cover' : 'max-h-64 object-cover mx-auto'"
      />
    </div>
    <div :class="variant === 'discover' ? 'p-3' : ''">
      <Link
        :href="`/movies/${movie.id}`"
        :class="variant === 'discover' 
          ? 'block text-sm font-semibold text-gray-950 dark:text-gray-100 leading-tight truncate'
          : 'block text-lg font-semibold text-gray-950 dark:text-gray-200 leading-tight px-4 py-2 truncate'"
      >
        {{ movie.name }}
      </Link>
      <div
        :class="variant === 'discover'
          ? 'mt-1 text-xs text-gray-600 dark:text-gray-300'
          : 'text-sm text-white px-4'"
      >
        <div>
          {{ movie.runtime ?? '—' }} min
        </div>
        <div
          v-if="movie.release_year"
          :class="variant === 'discover'
            ? 'mt-0.5 text-[10px] text-gray-500 dark:text-gray-400'
            : 'text-xs text-gray-400 mt-0.5'"
        >
          {{ movie.release_year }}
        </div>
      </div>
      <div :class="variant === 'discover' ? 'mt-2 flex flex-wrap gap-1' : 'px-4 mt-2 flex flex-wrap gap-1'">
        <span
          v-for="cat in (movie.categories ?? []).slice(0, 3)"
          :key="cat.id"
          :class="variant === 'discover'
            ? 'text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200'
            : 'text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-white'"
        >
          {{ cat.name }}
        </span>
        <span
          v-if="(showCategoryCount || variant === 'discover') && (movie.categories ?? []).length > 3"
          :class="variant === 'discover'
            ? 'text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200'
            : 'text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-white'"
        >
          +{{ (movie.categories ?? []).length - 3 }}
        </span>
      </div>
      <div
        :class="variant === 'discover' ? 'mt-2 flex flex-wrap gap-1' : 'px-4 mt-2 flex flex-wrap gap-1'"
        v-if="(movie.content_warnings ?? movie.contentWarnings ?? []).length"
      >
        <span
          v-for="w in (movie.content_warnings ?? movie.contentWarnings ?? []).slice(0, 2)"
          :key="w.id"
          :class="variant === 'discover'
            ? 'text-[10px] px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200'
            : 'text-[10px] px-2 py-0.5 rounded-full bg-red-500/20 text-red-200'"
        >
          {{ w.slug === '18+' ? '18+' : w.name }}
        </span>
      </div>
      <div
        v-if="(movie.genres ?? []).length > 0"
        :class="variant === 'discover' ? 'mt-2 flex flex-wrap gap-1' : 'px-4 mt-2 flex flex-wrap gap-1'"
      >
        <span
          v-for="genre in (movie.genres ?? []).slice(0, 2)"
          :key="genre"
          :class="variant === 'discover'
            ? 'text-[10px] px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200'
            : 'text-[10px] px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-200'"
        >
          {{ genre }}
        </span>
      </div>
      <div
        :class="variant === 'discover'
          ? 'mt-2 flex items-center justify-between text-[10px] text-gray-600 dark:text-gray-300'
          : 'px-4 mt-2 text-[11px] text-gray-300 flex items-center justify-between'"
      >
        <div v-if="movie.tmdb_rating">TMDB {{ movie.tmdb_rating }}</div>
        <div v-else-if="movie.trakt_rating">Trakt {{ movie.trakt_rating }}</div>
        <div v-else-if="movie.imdb_rating">IMDb {{ movie.imdb_rating }}</div>
        <div v-else>—</div>

        <div
          v-if="(movie.available_dub_languages ?? []).length"
          class="truncate max-w-28 text-right"
        >
          {{ movie.available_dub_languages.slice(0, 2).join(', ') }}
        </div>
      </div>
      <div
        v-if="showWatchedInfo"
        :class="variant === 'discover' ? 'text-sm text-white p-3 pt-2' : 'text-sm text-white px-4 pb-4'"
      >
        {{ lastWatched(movie) }}<span v-if="isWatched(movie)"> · Watched</span>
      </div>
    </div>
  </div>
</template>

