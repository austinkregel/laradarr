<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecommendationSection from '@/Components/RecommendationSection.vue';

const props = defineProps({
  showRecommendations: {
    type: Array,
    required: true,
  },
});

const showSort = ref('score');

const sortedShows = computed(() => sortEntries(props.showRecommendations, showSort.value, 'show'));

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
  <AppLayout title="Show Recommendations">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Personalized Shows</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            Shows matched from your watch history, completed shows, and favorites. Adjust the focus to slice your recommendations differently.
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 self-center">
            Focus
          </span>
          <button
            type="button"
            :class="buttonClass(showSort === 'score')"
            @click="showSort = 'score'"
          >
            Best match
          </button>
          <button
            type="button"
            :class="buttonClass(showSort === 'year')"
            @click="showSort = 'year'"
          >
            Newest first
          </button>
        </div>
      </div>
    </template>

    <div class="pb-8 max-w-[100rem] mx-auto">
      <div class="sm:px-6 lg:px-8 space-y-10">
        <section class="space-y-4">
          <RecommendationSection
            title="Recommended Shows"
            :recommendations="sortedShows"
            entry-key="show"
            link-prefix="shows"
          />
        </section>
      </div>
    </div>
  </AppLayout>
</template>



