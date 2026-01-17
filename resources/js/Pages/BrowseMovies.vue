<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import MovieFilters from '@/Components/MovieFilters.vue'
import MovieCard from '@/Components/MovieCard.vue'

const props = defineProps({
  movies: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    required: true,
  },
})
</script>

<template>
  <AppLayout title="Browse Movies">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Browse Movies
        </h2>
        <Link href="/dashboard/movies" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">Back to Movies Dashboard</Link>
      </div>
    </template>

    <div class="pb-12 max-w-[100rem] mx-auto">
      <div class="sm:px-6 lg:px-8">
        <div class="my-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
          <aside class="lg:col-span-4 xl:col-span-3">
            <MovieFilters :filters="filters" basePath="/browse/movies" />
          </aside>

          <main class="lg:col-span-8 xl:col-span-9">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-6">
              <MovieCard
                v-for="movie in movies.data"
                :key="movie.id"
                :movie="movie"
                redirect="/browse/movies"
                variant="browse"
                :show-category-count="true"
              />
            </div>
          </main>
        </div>

        <div class="mt-8 flex items-center justify-center">
          <div v-for="link in movies.links" :key="link.url ?? link.label">
            <Link
              :href="link.url"
              class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 leading-5 rounded-md focus:outline-none transition ease-in-out duration-150"
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

