<script setup>
import {computed} from "vue";
import LikeButton from "@/Components/LikeButton.vue";

const { episode, show_id } = defineProps({
  'episode': {
    type: Object,
    required: true,
  },
  show_id: {
    type: Number,
    required: true,
  }
});

const watched = computed(() => episode.watchers.length > 0);
</script>

<template>
  <div class="relative bg-white dark:bg-gray-950 overflow-hidden shadow-xl sm:rounded-lg py-4 flex flex-col justify-between">
    <div class="flex flex-col">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 leading-tight px-4">{{ episode.name }}</h3>
      <p class="text-gray-500 dark:text-gray-400 px-4 py-2">{{ episode.description }}</p>

    </div>
    <div class="flex flex-wrap">
      <div class="mx-4 flex-grow">
        <div class="text-xs uppercase text-gray-300 tracking-widest">files:</div>
        <div v-for="media in episode.media" class="dark:text-gray-200 text-xs">
          <div class="truncate">{{media.name}}</div>
          <div>{{media?.custom_properties?.languages.join(', ')}}</div>
        </div>
      </div>

      <LikeButton
          :follow="episode"
          type="App\Models\Episode"
          class=" shadow bg-black/10 right-0 text-white ml-4 mt-4"
          :redirect="'/shows/' + show_id+'?season='+episode.season_id"
      ></LikeButton>

      <button v-if="watched" class="text-xs  text-white p-1 rounded-lg mx-4 mt-4 bg-green-500 dark:bg-green-800">
        watched
      </button>
    </div>
  </div>

</template>

<style scoped>

</style>