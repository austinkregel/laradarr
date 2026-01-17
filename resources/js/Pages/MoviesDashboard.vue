<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from "@inertiajs/vue3";
import dayjs from "dayjs";
import relativeTime from 'dayjs/plugin/relativeTime';
import utc from 'dayjs/plugin/utc';
import MovieFilters from "@/Components/MovieFilters.vue";
import RecommendationSection from "@/Components/RecommendationSection.vue";
import MovieCard from "@/Components/MovieCard.vue";

dayjs.extend(relativeTime)
dayjs.extend(utc);
const { movies, recently_watched_movies, movie_filters, movie_recommendations } = defineProps({
  movies: {
    type: Object,
    required: true,
  },
  recently_watched_movies: {
    type: Array,
    required: true,
  },
  movie_filters: {
    type: Object,
    required: true,
  },
  movie_recommendations: {
    type: Array,
    required: true,
  },
})
</script>

<template>
    <AppLayout title="Movies Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Movies Dashboard
            </h2>
        </template>

        <div class="pb-12 max-w-[100rem] mx-auto">
            <div class="sm:px-6 lg:px-8">
                <div class="my-8">
                  <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 leading-tight px-4">Recently Watched</h3>
                </div>

                <div class="grid grid-cols-5 gap-4" v-if="recently_watched_movies.length > 0">
                  <div v-for="watchedMovie in recently_watched_movies" :key="watchedMovie.id ?? watchedMovie.pivot?.id">
                    <MovieCard :movie="watchedMovie.movie" redirect="/dashboard/movies" />
                  </div>
                </div>

                <div>
                  <Link href="/watched-movies" class="text-sm text-gray-600 dark:text-gray-400 leading-tight px-4">View all watched movies</Link>
                </div>

                <div v-if="movie_recommendations.length" class="my-8">
                  <RecommendationSection
                    title="Recommended Movies"
                    view-all-href="/recommendations/movies"
                    :recommendations="movie_recommendations"
                    entry-key="movie"
                    link-prefix=""
                  />
                </div>

                <div class="border-t border-gray-700 my-6"></div>

                <div class="mb-2">
                  <div class="flex items-center justify-between px-4">
                    <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 leading-tight">Movies</h3>
                    <Link href="/browse/movies" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">Browse Movies</Link>
                  </div>
                </div>

                <div class="py-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
                  <aside class="lg:col-span-4 xl:col-span-3">
                    <MovieFilters :filters="movie_filters" basePath="/dashboard/movies" />
                  </aside>

                  <main class="lg:col-span-8 xl:col-span-9">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-6">
                      <MovieCard
                        v-for="movie in movies.data"
                        :key="movie.id"
                        :movie="movie"
                        redirect="/dashboard/movies"
                        :show-completed="true"
                        :show-watched-info="true"
                      />
                    </div>
                  </main>
                </div>

                <div class="mt-8 flex items-center justify-center">
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

