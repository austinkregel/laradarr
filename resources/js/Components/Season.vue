<script setup>
import { Disclosure, DisclosureButton, DisclosurePanel} from '@headlessui/vue';
import {computed, ref} from "vue";
import { ArrowRightIcon,ChevronRightIcon } from "@heroicons/vue/24/solid";
import { useForm, usePage } from '@inertiajs/vue3';
import Episode from "@/Components/Episode.vue";
import DialogModal from "@/Components/DialogModal.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

const { season } = defineProps({
  'season': {
    type: Object,
    required: true,
  },
});

const seasonWatched = computed(() => episodesWatched.value === season.episodes.length);
const episodesWatched = computed(() => season.episodes.filter(episode => episode.watchers.length > 0).length);

const searchProcessing = ref(false);
const searchMessage = ref('');
const searchError = ref('');

const showMagnetModal = ref(false);
const magnetMessage = ref('');
const magnetError = ref('');

const page = usePage();
const isAdmin = computed(() => (page.props.auth?.user?.role ?? 'default') === 'admin');

const magnetForm = useForm({
  title: '',
  magnetUrl: '',
  downloadUrl: '',
  publishDate: '',
});

const hasEpisodesWithSonarrId = computed(() => {
  return season.episodes.some(episode => episode.sonarr_episode_id);
});

const hasShowWithSonarrId = computed(() => {
  return season.show?.sonarr_id !== null && season.show?.sonarr_id !== undefined;
});

const seasonTitle = computed(() => {
  return season.name || `Season ${season.season}`;
});

const openMagnetModal = () => {
  // Don't pre-fill title - user must provide the actual release title
  magnetForm.title = '';
  magnetForm.magnetUrl = '';
  magnetForm.downloadUrl = '';
  magnetForm.publishDate = '';
  magnetError.value = '';
  magnetMessage.value = '';
  showMagnetModal.value = true;
};

const closeMagnetModal = () => {
  showMagnetModal.value = false;
};

const magnetErrorMessage = computed(() => {
  return magnetError.value
    || magnetForm.errors.magnetUrl
    || magnetForm.errors.downloadUrl
    || magnetForm.errors.publishDate
    || magnetForm.errors.message
    || '';
});

const extractTitleFromMagnet = (magnetUrl) => {
  if (!magnetUrl || !magnetUrl.startsWith('magnet:')) {
    return null;
  }

  try {
    // Parse the magnet URL to extract the 'dn' (display name) parameter
    const url = new URL(magnetUrl);
    const dnParam = url.searchParams.get('dn');
    
    if (dnParam) {
      // URL decode the display name
      return decodeURIComponent(dnParam);
    }
  } catch (error) {
    console.warn('Failed to parse magnet URL:', error);
  }

  return null;
};

const onMagnetUrlChange = () => {
  // Only auto-fill if title is currently empty
  if (!magnetForm.title && magnetForm.magnetUrl) {
    const extractedTitle = extractTitleFromMagnet(magnetForm.magnetUrl);
    if (extractedTitle) {
      magnetForm.title = extractedTitle;
    }
  }
};

const submitMagnet = () => {
  magnetForm.post(route('sonarr.seasons.release.push', { season: season.id }), {
    preserveScroll: true,
    onSuccess: () => {
      magnetMessage.value = 'Season release submitted to Sonarr successfully';
      magnetForm.reset('magnetUrl', 'downloadUrl', 'publishDate');
      closeMagnetModal();
      // Clear message after 5 seconds
      setTimeout(() => {
        magnetMessage.value = '';
      }, 5000);
    },
    onError: () => {
      magnetError.value = magnetErrorMessage.value || 'Unable to submit release';
    },
  });
};

const triggerSeasonSearch = async () => {
  if (!hasEpisodesWithSonarrId.value) {
    searchError.value = 'No episodes with Sonarr IDs found for this season';
    return;
  }

  searchProcessing.value = true;
  searchError.value = '';
  searchMessage.value = '';

  try {
    const response = await window.axios.post(route('sonarr.seasons.search', { season: season.id }));
    searchMessage.value = `Search initiated for ${response.data.episodes_count || season.episodes.length} episodes`;
    
    // Clear message after 5 seconds
    setTimeout(() => {
      searchMessage.value = '';
    }, 5000);
  } catch (error) {
    console.error('Error searching season:', error);
    searchError.value = error.response?.data?.message || 'Unable to search season';
    
    // Clear error after 5 seconds
    setTimeout(() => {
      searchError.value = '';
    }, 5000);
  } finally {
    searchProcessing.value = false;
  }
};
</script>

<template>
<Disclosure as="div"  v-slot="{ open }">
  <DisclosureButton class="w-full">
    <div class="w-full flex justify-between text-2xl mb-4 font-semibold px-4 pt-4 text-gray-800 dark:text-gray-200 leading-tight">
      <div class="flex items-start flex-wrap gap-2">
        <div>{{ season.name }}</div>
        <div :class="seasonWatched ? 'bg-green-800 text-white' : 'text-gray-500 dark:text-gray-400 bg-gray-600'"  class="text-xs mx-4 p-2 rounded-lg ">{{ season.episodes.length }} episodes</div>
        <div :class="seasonWatched ? 'bg-green-800 text-white' : 'text-gray-500 dark:text-gray-400 bg-gray-600'" class="text-xs mx-4 p-2 rounded-lg ">{{ episodesWatched }} watched</div>
        <button
          v-if="isAdmin && hasEpisodesWithSonarrId"
          @click.stop="triggerSeasonSearch"
          :disabled="searchProcessing"
          class="text-xs px-3 py-1 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          type="button"
        >
          <span v-if="!searchProcessing">Search Season</span>
          <span v-else class="flex items-center gap-1">
            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Searching...
          </span>
        </button>
        <button
          v-if="isAdmin && hasShowWithSonarrId"
          @click.stop="openMagnetModal"
          :disabled="magnetForm.processing"
          class="text-xs px-3 py-1 rounded-lg bg-purple-600 text-white hover:bg-purple-700 focus:outline-none focus:ring focus:ring-purple-500 disabled:opacity-50 disabled:cursor-not-allowed"
          type="button"
        >
          <span v-if="!magnetForm.processing">Submit Season Magnet</span>
          <span v-else class="flex items-center gap-1">
            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Submitting...
          </span>
        </button>
      </div>

      <ChevronRightIcon :class="open && 'rotate-90 transform'" class="fill-current w-6 h-6" />
    </div>
    <div v-if="searchMessage || searchError || magnetMessage || magnetError" class="px-4 pb-2">
      <div v-if="searchMessage" class="text-xs text-green-600 dark:text-green-400">
        {{ searchMessage }}
      </div>
      <div v-if="searchError" class="text-xs text-red-600 dark:text-red-400">
        {{ searchError }}
      </div>
      <div v-if="magnetMessage" class="text-xs text-green-600 dark:text-green-400">
        {{ magnetMessage }}
      </div>
      <div v-if="magnetError" class="text-xs text-red-600 dark:text-red-400">
        {{ magnetError }}
      </div>
    </div>
  </DisclosureButton>
  <DisclosurePanel as="div" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-4">
    <Episode v-for="episode in season.episodes" :key="episode.id" :episode="episode" :show_id="season.show_id"></Episode>
  </DisclosurePanel>
</Disclosure>

  <DialogModal v-if="isAdmin" :show="showMagnetModal" @close="closeMagnetModal">
    <template #title>
      Submit Season Magnet or Torrent
    </template>

    <template #content>
      <div class="space-y-4">
        <div>
          <InputLabel value="Release Title (required)" />
          <TextInput v-model="magnetForm.title" placeholder="e.g., Show.Name.S01.1080p.BluRay.x264-GROUP" />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Enter the exact release title from the torrent. Sonarr needs this to parse and match the season pack.
            <br>
            <span v-if="magnetForm.magnetUrl">Title will be auto-filled from magnet URL if available.</span>
            <span v-else>Example format: <code class="text-xs">Show.Name.S01.1080p.BluRay.x264-GROUP</code></span>
          </p>
        </div>

        <div>
          <InputLabel value="Magnet URL" />
          <TextInput 
            v-model="magnetForm.magnetUrl" 
            placeholder="magnet:?xt=urn:btih:..." 
            @input="onMagnetUrlChange"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Title will be automatically extracted from the magnet URL if available
          </p>
        </div>

        <div>
          <InputLabel value="Download URL (optional)" />
          <TextInput v-model="magnetForm.downloadUrl" placeholder="https://example.com/file.torrent" />
        </div>

        <div>
          <InputLabel value="Publish Date (optional)" />
          <input
            type="datetime-local"
            v-model="magnetForm.publishDate"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 focus:ring-opacity-50 shadow-sm"
          />
        </div>

        <InputError :message="magnetErrorMessage" />
      </div>
    </template>

    <template #footer>
      <SecondaryButton type="button" @click="closeMagnetModal">
        Cancel
      </SecondaryButton>

      <PrimaryButton type="button" :disabled="magnetForm.processing" @click="submitMagnet">
        <span v-if="!magnetForm.processing">Submit to Sonarr</span>
        <span v-else>Submitting…</span>
      </PrimaryButton>
    </template>
  </DialogModal>
</template>

<style scoped>

</style>