<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from "@inertiajs/vue3";
import dayjs from "dayjs";
import relativeTime from 'dayjs/plugin/relativeTime';
import utc from 'dayjs/plugin/utc';
import WatchedEpisode from "@/Components/WatchedEpisode.vue";
import ShowFilters from "@/Components/ShowFilters.vue";
import RecommendationSection from "@/Components/RecommendationSection.vue";
import ShowCard from "@/Components/ShowCard.vue";

dayjs.extend(relativeTime)
dayjs.extend(utc);
const { shows, recently_watched, filters, show_recommendations } = defineProps({
  'shows': {
    type: Object,
    required: true,
  },
  recently_watched: {
    type: Array,
    required: true,
  },
  filters: {
    type: Object,
    required: true,
  },
  show_recommendations: {
    type: Array,
    required: true,
  },
})
</script>

<template>
    <AppLayout title="Shows Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Shows Dashboard
            </h2>
        </template>

        <div class="pb-12 max-w-[100rem] mx-auto">
            <div class="sm:px-6 lg:px-8">
                <div class="my-8">
                  <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 leading-tight px-4">Recently Watched</h3>
                </div>

                <div class="grid grid-cols-5 gap-4" v-if="recently_watched.length > 0">
                  <div v-for="show in recently_watched" :key="show.id ?? show.episode_id ?? show.pivot?.id ?? JSON.stringify(show)">
                    <WatchedEpisode :episode="show" />
                  </div>
                </div>

                <div>
                  <Link href="/watched-shows" class="text-sm text-gray-600 dark:text-gray-400 leading-tight px-4">View all watched shows</Link>
                </div>

                <div v-if="show_recommendations.length" class="my-8">
                  <RecommendationSection
                    title="Recommended Shows"
                    view-all-href="/recommendations/shows"
                    :recommendations="show_recommendations"
                    entry-key="show"
                    link-prefix="shows"
                  />
                </div>

                <div class="border-t border-gray-700 my-6"></div>

                <div class="mb-2">
                  <div class="flex items-center justify-between px-4">
                    <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 leading-tight">Shows</h3>
                    <Link href="/browse/shows" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">Browse</Link>
                  </div>
                </div>

                <div class="py-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
                  <aside class="lg:col-span-4 xl:col-span-3">
                    <ShowFilters :filters="filters" basePath="/dashboard/shows" />
                  </aside>

                  <main class="lg:col-span-8 xl:col-span-9">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-6">
                      <ShowCard
                        v-for="show in shows.data"
                        :key="show.id"
                        :show="show"
                        redirect="/dashboard/shows"
                        :show-completed="true"
                        :show-watched-info="true"
                      />
                    </div>
                  </main>
                </div>

                <div class="mt-8 flex items-center justify-center">
                  <div v-for="link in shows.links" :key="link.url ?? link.label">
                    <Link
                      :href="link.url"
                      class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 leading-5 rounded-md focus:outline-none focus:shadow-outline-blue active:bg-gray-100 dark:active:bg-gray-700 transition ease-in-out duration-150"
                      :class="{ 'bg-gray-100 dark:bg-gray-700': link.active }"
                    >
                      <span v-html="link.label"></span>
                    </Link>

                  </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

