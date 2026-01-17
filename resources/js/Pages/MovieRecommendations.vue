<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecommendationSection from '@/Components/RecommendationSection.vue';

const props = defineProps({
  movieRecommendations: {
    type: Array,
    required: true,
  },
});

const movieSort = ref('score');

const sortedMovies = computed(() => sortEntries(props.movieRecommendations, movieSort.value, 'movie'));

const sortEntries = (entries, mode, entryKey) => {
  const sorted = [...entries];

  if (mode === 'year') {
    sorted.sort((a, b) => {
      const aYear = a[entryKey]?.release_year ?? 0;
      const bYear = b[entryKey]?.release_year ?? 0;
      return bYear - aYear;
    });
    return sorted;
  }

  if (mode === 'recent') {
    sorted.sort((a, b) => {
      const aStamp = toTimestamp(a[entryKey]?.added_at);
      const bStamp = toTimestamp(b[entryKey]?.added_at);
      return bStamp - aStamp;
    });
    return sorted;
  }

  sorted.sort((a, b) => (b.score ?? 0) - (a.score ?? 0));
  return sorted;
};

const toTimestamp = (value) => {
  if (!value) {
    return 0;
  }

  return new Date(value).getTime();
};

const buttonClass = (active) => [
  'px-3',
  'py-1.5',
  'text-sm',
  'font-semibold',
  'rounded-md',
  active ? 'bg-blue-600 text-white shadow' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
];
</script>

<template>
  <AppLayout title="Movie Recommendations">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Personalized Movies</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            Movies curated from your preferences with a balance between user score and recent arrivals.
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 self-center">
            Focus
          </span>
          <button
            type="button"
            :class="buttonClass(movieSort === 'score')"
            @click="movieSort = 'score'"
          >
            Best match
          </button>
          <button
            type="button"
            :class="buttonClass(movieSort === 'recent')"
            @click="movieSort = 'recent'"
          >
            Latest
          </button>
        </div>
      </div>
    </template>

    <div class="pb-8 max-w-[100rem] mx-auto">
      <div class="sm:px-6 lg:px-8 space-y-10">
        <section class="space-y-4">
          <RecommendationSection
            title="Recommended Movies"
            :recommendations="sortedMovies"
            entry-key="movie"
            link-prefix=""
          />
        </section>
      </div>
    </div>
  </AppLayout>
</template>





