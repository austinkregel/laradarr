<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlayIcon, FilmIcon } from "@heroicons/vue/24/solid";
import { Link, usePage } from "@inertiajs/vue3";
import Season from "@/Components/Season.vue";
import LikeButton from "@/Components/LikeButton.vue";
import { computed, ref } from "vue";
import { useForm } from '@inertiajs/vue3';

const { show } = defineProps({
  'show': {
    type: Object,
    required: true,
  },
})

const languages = computed(() => {
  return show.seasons.reduce((acc, season) => {
    season.episodes.forEach(episode => {
      episode.media.forEach(media => {
        media.custom_properties.languages.forEach(language => {
          if (!acc.includes(language)) {
            acc.push(language);
          }
        });
      });
    });
    return acc;
  }, []);
})

const plexBaseUrl = computed(() => {
  const serverInfo = usePage().props.plexServerInfo;
  if (!serverInfo || !show.plex_id) {
    return null;
  }
  return `${serverInfo.url}/web/index.html#!/server/${serverInfo.serverId}/details?key=${encodeURIComponent(show.plex_id)}`;
});

const reSearchMessage = ref('');
const reSearchError = ref('');

const showSearchForm = useForm({
  showId: show.id,
});

const triggerMissingSearch = () => {
  if (!show.sonarr_id) {
    reSearchError.value = 'Show is not linked to Sonarr';
    return;
  }

  reSearchError.value = '';
  reSearchMessage.value = '';
  showSearchForm.showId = show.id;

  showSearchForm.post(route('sonarr.episodes.search'), {
    preserveScroll: true,
    onSuccess: () => {
      reSearchMessage.value = 'Requested Sonarr to search missing episodes';
    },
    onError: () => {
      reSearchError.value = showSearchForm.errors.message ?? 'Unable to request search';
    },
  });
};

const user = usePage().props.auth.user;
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
              {{ show.name }}
            </h2>
        </template>

        <div class="py-12 max-w-[100rem] mx-auto">
            <div class="sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg grid grid-cols-4">
                  <div class="col-span-4">
                    <img :src="show.banner_image" alt="" class="mx-auto max-w-7xl w-full"/>
                  </div>
                    <div class="flex justify-center bg-gray-950">
                      <img :src="show.poster_image" alt="show.title" class="max-h-96">
                    </div>
                    <div class="col-span-3 py-4">
                        <Link :href="`/shows/${show.id}`" class="block text-lg font-semibold text-gray-800 dark:text-gray-200 leading-tight px-4 py-2">{{ show.name }}</Link>
                      <div class="px-4 text-gray-700 dark:text-gray-200">{{ show.description }}</div>

                      <div class="px-4 mt-4 flex flex-wrap gap-2" v-if="(show.contentWarnings ?? []).length">
                        <div
                          v-for="w in show.contentWarnings"
                          :key="w.id"
                          class="text-xs px-3 py-1 rounded-full bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200"
                        >
                          {{ w.slug === '18+' ? '18+ (Adult Content)' : w.name }}
                        </div>
                      </div>

                      <div class="px-4 text-gray-700 dark:text-gray-200 mt-4">{{ languages.join(', ') }}</div>

                      <div class="flex flex-wrap items-center gap-2 m-4">
                        <LikeButton
                          :follow="show"
                          type="App\Models\Show"
                          :redirect="`/shows/${show.id}`"
                          class="rounded-lg py-2 px-4 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600"
                        />
                        <a v-if="plexBaseUrl" class="rounded-lg py-2 px-8 bg-amber-500 flex items-center gap-2" target="_blank" :href="plexBaseUrl">
                          <PlayIcon class="w-6 h-6" />
                          Play on Plex
                        </a>
                        <a v-if="show.trakt_id" class="rounded-lg py-2 px-4 text-gray-700 dark:text-gray-200 flex items-center gap-2" target="_blank" :href="'https://trakt.tv/shows/'+show.trakt_id">
                          <FilmIcon class="w-6 h-6" />
                          Open on Trakt
                        </a>
                        <a v-if="show.sonarr_id" class="rounded-lg py-2 px-4 text-gray-700 dark:text-gray-200 flex items-center gap-2" target="_blank" :href="'https://sonarr.kregel.host/series/'+show.slug">
                          <img src="https://sonarr.kregel.host/Content/Images/logo.svg" class="w-6 h-6" />
                          Open in Sonarr
                        </a>
                        <button
                          class="rounded-lg py-2 px-4 bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring focus:ring-indigo-500 disabled:opacity-50"
                          type="button"
                          :disabled="showSearchForm.processing || !show.sonarr_id"
                          @click="triggerMissingSearch"
                        >
                          <span v-if="!showSearchForm.processing">Re-search missing episodes</span>
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
              <div class="flex flex-col gap-4 mt-4">
                <Season v-for="season in show.seasons" :key="season.id" class="bg-gray-800 p-4 rounded-lg overflow-hidden" :season="season"></Season>
              </div>
            </div>
        </div>
    </AppLayout>
</template>
