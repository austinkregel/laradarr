<script setup>
import { Disclosure, DisclosureButton, DisclosurePanel} from '@headlessui/vue';
import {computed} from "vue";
import { ArrowRightIcon,ChevronRightIcon } from "@heroicons/vue/24/solid";
import Episode from "@/Components/Episode.vue";

const { season } = defineProps({
  'season': {
    type: Object,
    required: true,
  },
});

const seasonWatched = computed(() => episodesWatched.value === season.episodes.length);
const episodesWatched = computed(() => season.episodes.filter(episode => episode.watchers.length > 0).length);
</script>

<template>
<Disclosure as="div"  v-slot="{ open }">
  <DisclosureButton class="w-full">
    <div class="w-full flex justify-between text-2xl mb-4 font-semibold px-4 pt-4 text-gray-800 dark:text-gray-200 leading-tight">
      <div class="flex items-start">
        <div>{{ season.name }}</div>
        <div :class="seasonWatched ? 'bg-green-800 text-white' : 'text-gray-500 dark:text-gray-400 bg-gray-600'"  class="text-xs mx-4 p-2 rounded-lg ">{{ season.episodes.length }} episodes</div>
        <div :class="seasonWatched ? 'bg-green-800 text-white' : 'text-gray-500 dark:text-gray-400 bg-gray-600'" class="text-xs mx-4 p-2 rounded-lg ">{{ episodesWatched }} watched</div>
      </div>

      <ChevronRightIcon :class="open && 'rotate-90 transform'" class="fill-current w-6 h-6" />
    </div>
  </DisclosureButton>
  <DisclosurePanel as="div" class="grid grid-cols-4 gap-4">
    <Episode v-for="episode in season.episodes" :key="episode.id" :episode="episode" :show_id="season.show_id"></Episode>
  </DisclosurePanel>
</Disclosure>
</template>

<style scoped>

</style>