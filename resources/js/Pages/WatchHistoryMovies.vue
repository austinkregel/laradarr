<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from "@inertiajs/vue3";
import MovieCard from "@/Components/MovieCard.vue";

const { movies } = defineProps({
  'movies': {
    type: Object,
    required: true,
  }
})
</script>

<template>
    <AppLayout title="Watch History - Movies">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Watch History - Movies
            </h2>
        </template>

        <div class="pb-12 max-w-[100rem] mx-auto">
            <div class="sm:px-6 lg:px-8">
                <div class="my-8">
                  <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 leading-tight px-4">Watched Movies</h3>
                </div>

                <div v-if="movies.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                  <div v-for="watchedMovie in movies.data" :key="watchedMovie.id ?? watchedMovie.pivot?.id">
                    <MovieCard :movie="watchedMovie.movie" redirect="/watched-movies" />
                  </div>
                </div>

                <div v-else class="text-center py-12">
                  <div class="text-gray-400 dark:text-gray-500">
                    <svg class="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No watch history yet</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Start watching movies to see them here</p>
                  </div>
                </div>

                <div v-if="movies.data.length > 0" class="mt-8 flex items-center justify-center">
                  <div v-for="link in movies.links" :key="link.url ?? link.label">
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





