<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlayIcon, FilmIcon } from "@heroicons/vue/24/solid";
import { Link, usePage } from "@inertiajs/vue3";
import LikeButton from "@/Components/LikeButton.vue";
import { computed, ref } from "vue";
import { useForm } from '@inertiajs/vue3';

const { movie } = defineProps({
  'movie': {
    type: Object,
    required: true,
  },
})

const languages = computed(() => {
  const langs = [];
  // Use available_dub_languages if available, otherwise scan media
  if (movie.available_dub_languages && movie.available_dub_languages.length > 0) {
    return movie.available_dub_languages;
  }
  
  // Fallback: scan media files
  const mediaFiles = movie.media || [];
  mediaFiles.forEach(media => {
    (media.custom_properties?.languages || []).forEach(language => {
      if (!langs.includes(language)) {
        langs.push(language);
      }
    });
  });
  return langs;
})

const plexBaseUrl = computed(() => {
  const serverInfo = usePage().props.plexServerInfo;
  if (!serverInfo || !movie.plex_id) {
    return null;
  }
  return `${serverInfo.url}/web/index.html#!/server/${serverInfo.serverId}/details?key=${encodeURIComponent(movie.plex_id)}`;
});

const reSearchMessage = ref('');
const reSearchError = ref('');

const movieSearchForm = useForm({
  movieId: movie.id,
});

const triggerMissingSearch = () => {
  if (!movie.radarr_id) {
    reSearchError.value = 'Movie is not linked to Radarr';
    return;
  }

  reSearchError.value = '';
  reSearchMessage.value = '';

  movieSearchForm.post(route('radarr.movies.search', movie.id), {
    preserveScroll: true,
    onSuccess: () => {
      reSearchMessage.value = 'Requested Radarr to search for movie';
    },
    onError: () => {
      reSearchError.value = movieSearchForm.errors.message ?? 'Unable to request search';
    },
  });
};

const page = usePage();
const isAdmin = computed(() => (page.props.auth?.user?.role ?? 'default') === 'admin');
</script>

<template>
    <AppLayout title="Movie">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
              {{ movie.name }}
            </h2>
        </template>

        <div class="py-12 max-w-[100rem] mx-auto">
            <div class="sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg grid grid-cols-4">
                  <div class="col-span-4" v-if="movie.banner_image">
                    <img :src="movie.banner_image" alt="" class="mx-auto max-w-7xl w-full"/>
                  </div>
                    <div class="flex justify-center bg-gray-950">
                      <img :src="movie.poster_image?.replace('poster.jpg', 'poster-500.jpg') ?? movie.poster_image" alt="movie.name" class="max-h-96">
                    </div>
                    <div class="col-span-3 py-4">
                        <Link :href="`/movies/${movie.id}`" class="block text-lg font-semibold text-gray-800 dark:text-gray-200 leading-tight px-4 py-2">{{ movie.name }}</Link>
                      <div class="px-4 text-gray-700 dark:text-gray-200">{{ movie.description }}</div>

                      <div class="px-4 mt-4 flex flex-wrap gap-2" v-if="(movie.contentWarnings ?? []).length">
                        <div
                          v-for="w in movie.contentWarnings"
                          :key="w.id"
                          class="text-xs px-3 py-1 rounded-full bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200"
                        >
                          {{ w.slug === '18+' ? '18+ (Adult Content)' : w.name }}
                        </div>
                      </div>

                      <div class="px-4 text-gray-700 dark:text-gray-200 mt-4">{{ languages.join(', ') }}</div>

                      <div class="flex flex-wrap items-center gap-2 m-4">
                        <LikeButton
                          :follow="movie"
                          type="App\Models\Movie"
                          :redirect="`/movies/${movie.id}`"
                          class="rounded-lg py-2 px-4 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600"
                        />
                        <a v-if="plexBaseUrl" class="rounded-lg py-2 px-8 bg-amber-500 flex items-center gap-2" target="_blank" :href="plexBaseUrl">
                          <PlayIcon class="w-6 h-6" />
                          Play on Plex
                        </a>
                        <a v-if="movie.trakt_id" class="rounded-lg py-2 px-4 text-gray-700 dark:text-gray-200 flex items-center gap-2" target="_blank" :href="'https://trakt.tv/movies/'+movie.trakt_id">
                          <FilmIcon class="w-6 h-6" />
                          Open on Trakt
                        </a>
                        <a v-if="isAdmin && movie.radarr_id" class="rounded-lg py-2 px-4 text-gray-700 dark:text-gray-200 flex items-center gap-2" target="_blank" :href="'https://radarr.kregel.host/movie/'+movie.slug">
                          <img src="https://radarr.kregel.host/Content/Images/logo.svg" class="w-6 h-6" />
                          Open in Radarr
                        </a>
                        <button
                          v-if="isAdmin"
                          class="rounded-lg py-2 px-4 bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring focus:ring-indigo-500 disabled:opacity-50"
                          type="button"
                          :disabled="movieSearchForm.processing || !movie.radarr_id || movie.is_available"
                          @click="triggerMissingSearch"
                        >
                          <span v-if="!movieSearchForm.processing">Re-search movie</span>
                          <span v-else>Requesting…</span>
                        </button>
                      </div>
                      <div class="px-4 text-xs text-green-600 dark:text-green-400" v-if="reSearchMessage">
                        {{ reSearchMessage }}
                      </div>
                      <div class="px-4 text-xs text-red-600 dark:text-red-400" v-if="reSearchError">
                        {{ reSearchError }}
                      </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

