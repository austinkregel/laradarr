<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';
import { PlayIcon, FilmIcon } from "@heroicons/vue/24/solid";
import { Link } from "@inertiajs/vue3";
import Season from "@/Components/Season.vue";
import {computed} from "vue";

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
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
              {{ show.name }}
            </h2>
        </template>

        <div class="mx-8 py-12">
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
                      <div class="px-4 text-gray-700 dark:text-gray-200 mt-4">{{ languages.join(', ') }}</div>

                      <div class="flex m-4">
                        <a v-if="show.plex_id" class="rounded-lg py-2 px-8 bg-amber-500 flex items-center gap-2" target="_blank" :href="'http://192.168.2.134:32400/web/index.html#!/server/db51c7be315d6028e90862e52c6391bb21c762a5/details?key='+show.plex_id">
                          <PlayIcon class="w-6 h-6" />
                          Play on Plex
                        </a>
                        <a v-if="show.trakt_id" class="rounded-lg py-2 px-4 text-gray-700 dark:text-gray-200 flex items-center gap-2" target="_blank" :href="'https://trakt.tv/shows/'+show.trakt_id">
                          <FilmIcon class="w-6 h-6" />
                          Open on Trakt
                        </a>
                        <a v-if="show.sonarr_id" class="rounded-lg py-2 px-4 text-gray-700 dark:text-gray-200 flex items-center gap-2" target="_blank" :href="'http://192.168.3.17:8989/series/'+show.sonarr_id">
                          <img src="http://192.168.3.17:8989/Content/Images/logo.svg" class="w-6 h-6" />
                          Open in Sonarr
                        </a>
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
