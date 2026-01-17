<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";
import { useForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import DialogModal from "@/Components/DialogModal.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import LikeButton from "@/Components/LikeButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

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

const tagLabel = (tag) => {
  if (!tag) return '';
  if (typeof tag.name === 'string') return tag.name;
  if (tag.name && typeof tag.name === 'object') {
    return tag.name.en ?? Object.values(tag.name)[0] ?? '';
  }
  return '';
}

const episodeTitle = computed(() => episode.name ?? episode.title ?? 'Episode');

const showMagnetModal = ref(false);
const magnetMessage = ref('');
const magnetError = ref('');
const searchMessage = ref('');
const searchError = ref('');
const searchCommandId = ref(null);
const searchStatus = ref(null); // 'queued', 'started', 'completed', 'failed'
const searchProcessing = ref(false);
const pollInterval = ref(null);
const followUpStatus = ref(null); // 'added_to_queue', 'nothing_found', 'already_imported', 'error'
const followUpMessage = ref('');
const followUpPollInterval = ref(null);
const echoChannel = ref(null);
const echoSubscribed = ref(false);
const page = usePage();
const isAdmin = computed(() => (page.props.auth?.user?.role ?? 'default') === 'admin');

const magnetForm = useForm({
  title: '',
  magnetUrl: '',
  downloadUrl: '',
  publishDate: '',
});

const openMagnetModal = () => {
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

const searchErrorMessage = computed(() => {
  return searchError.value || '';
});

const isSearching = computed(() => {
  return searchStatus.value === 'queued' || searchStatus.value === 'started';
});

const stopPolling = () => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value);
    pollInterval.value = null;
  }
  if (followUpPollInterval.value) {
    clearInterval(followUpPollInterval.value);
    followUpPollInterval.value = null;
  }
};

const pollCommandStatus = (commandId) => {
  if (!commandId) return;

  const checkStatus = async () => {
    try {
      const response = await window.axios.get(route('sonarr.commands.status', { commandId }));
      const status = response.data.status;

      if (status === 'queued' || status === 'started') {
        searchStatus.value = status;
      } else if (status === 'completed') {
        searchStatus.value = 'completed';
        searchMessage.value = 'Search completed - checking results...';
        stopPolling();
        // Check for follow-up status (Echo will handle updates)
        setTimeout(() => {
          checkExistingSearchStatus();
        }, 2000);
      } else if (status === 'failed' || status === 'aborted') {
        searchStatus.value = 'failed';
        searchError.value = `Search ${status}`;
        stopPolling();
        // Clear status after 5 seconds
        setTimeout(() => {
          searchStatus.value = null;
        }, 5000);
      }
    } catch (error) {
      console.error('Error polling command status:', error);
      stopPolling();
    }
  };

  // Poll immediately, then every 2 seconds
  checkStatus();
  pollInterval.value = setInterval(checkStatus, 2000);
};

const pollFollowUpStatus = () => {
  if (!episode.id) return;

  const checkStatus = async () => {
    try {
      const response = await window.axios.get(route('sonarr.episodes.search-status', { episodeId: episode.id }));
      const data = response.data;

      if (data.result) {
        followUpStatus.value = data.result;
        followUpMessage.value = data.result_message || '';

        // Stop polling once we have a result
        if (followUpPollInterval.value) {
          clearInterval(followUpPollInterval.value);
          followUpPollInterval.value = null;
        }

        // Update search message based on result
        if (data.result === 'added_to_queue') {
          searchMessage.value = 'Episode added to download queue';
        } else if (data.result === 'already_imported') {
          searchMessage.value = 'Episode already imported';
        } else if (data.result === 'nothing_found') {
          searchMessage.value = 'No suitable release found';
        } else if (data.result === 'error') {
          searchError.value = data.result_message || 'Search encountered an error';
        }

        // Clear status after 10 seconds
        setTimeout(() => {
          searchStatus.value = null;
          searchMessage.value = '';
          followUpStatus.value = null;
          followUpMessage.value = '';
        }, 10000);
      } else if (data.status === 'completed' && !data.result) {
        // Still processing, keep polling
        return;
      }
    } catch (error) {
      if (error.response?.status === 404) {
        // No search request found yet, keep polling for a bit
        return;
      }
      console.error('Error polling follow-up status:', error);
      if (followUpPollInterval.value) {
        clearInterval(followUpPollInterval.value);
        followUpPollInterval.value = null;
      }
    }
  };

  // Poll immediately, then every 5 seconds
  checkStatus();
  followUpPollInterval.value = setInterval(checkStatus, 5000);

  // Stop polling after 2 minutes
  setTimeout(() => {
    if (followUpPollInterval.value) {
      clearInterval(followUpPollInterval.value);
      followUpPollInterval.value = null;
    }
  }, 120000);
};

const updateSearchStatus = (data) => {
  console.log('updateSearchStatus called with:', data, 'for episode:', episode.id);
  
  // Handle both direct event data and nested payload
  // The event might come as the data directly, or nested under a 'data' key
  const eventData = data.episode_id !== undefined ? data : (data.data || data);
  const episodeId = eventData.episode_id;
  
  if (!episodeId) {
    console.warn('Event missing episode_id, data:', eventData);
    return;
  }
  
  if (episodeId !== episode.id) {
    console.log('Event is for different episode (episode_id:', episodeId, 'vs current:', episode.id, '), ignoring');
    return;
  }
  
  // Use the extracted eventData for the rest of the function
  data = eventData;

  console.log('Updating search status:', data);
  searchStatus.value = data.status;
  followUpStatus.value = data.result;
  followUpMessage.value = data.result_message || '';

  if (data.status === 'completed' && data.result) {
    if (data.result === 'added_to_queue') {
      searchMessage.value = 'Episode added to download queue';
    } else if (data.result === 'already_imported') {
      searchMessage.value = 'Episode already imported';
    } else if (data.result === 'nothing_found') {
      searchMessage.value = 'No suitable release found';
    } else if (data.result === 'error') {
      searchError.value = data.result_message || 'Search encountered an error';
    }

    // Clear status after 10 seconds
    setTimeout(() => {
      if (data.result !== 'added_to_queue') {
        searchStatus.value = null;
        searchMessage.value = '';
        followUpStatus.value = null;
        followUpMessage.value = '';
      }
    }, 10000);
  } else if (['queued', 'started'].includes(data.status)) {
    // Still searching
    searchStatus.value = data.status;
  }
};

const checkExistingSearchStatus = async () => {
  if (!episode.id) return;

  try {
    const response = await window.axios.get(route('sonarr.episodes.search-status', { episodeId: episode.id }));
    const data = response.data;

    if (data.status && (data.status === 'queued' || data.status === 'started' || !data.completed_at)) {
      // Search is still in progress
      updateSearchStatus(data);
      // Start polling for updates if not completed
      if (!data.completed_at) {
        pollFollowUpStatus();
      }
    } else if (data.result) {
      // Search completed, show result
      updateSearchStatus(data);
    }
  } catch (error) {
    // 404 is expected if there's no search request for this episode - that's fine
    // Only log other errors
    if (error.response?.status && error.response.status !== 404) {
      console.error('Error checking search status:', error);
    }
  }
};

const setupEchoListener = () => {
  const userId = page.props.auth?.user?.id;
  if (!userId) {
    console.warn('No user ID available for Echo listener');
    if (episode.id) {
      pollFollowUpStatus();
    }
    return;
  }

  if (!window.Echo) {
    console.warn('Echo not available, falling back to polling');
    if (episode.id) {
      pollFollowUpStatus();
    }
    return;
  }

  // Listen to private channel for this user (only subscribe once per user)
  try {
    const channelName = `user.${userId}`;
    
    // Check if we're already subscribed to this channel (shared across all Episode components)
    // Use a global flag to track subscription
    if (!window.__echoSubscribedChannels) {
      window.__echoSubscribedChannels = new Set();
    }
    
    if (window.Echo && !window.__echoSubscribedChannels.has(channelName)) {
      // Not subscribed yet, set up listener
      console.log('Setting up Echo listener for channel:', channelName);
      console.log('Echo connection state:', window.Echo.connector.pusher.connection.state);
      
      // Wait for connection if not already connected
      const subscribeToChannel = () => {
        if (echoSubscribed.value) {
          console.log('Already subscribed to channel, skipping');
          return;
        }

        const channel = window.Echo.private(channelName);
        echoChannel.value = channel;
        
        channel.listen('.episode-search-status-updated', (event) => {
          console.log('Received episode-search-status-updated event:', event);
          // Event data might be nested, extract it
          const eventData = event.episode_id ? event : (event.data || event);
          updateSearchStatus(eventData);
        });

        channel.error((error) => {
          // Auth errors might be false positives if subscription still works
          if (error?.type === 'AuthError') {
            console.warn('Echo channel auth error (subscription may still work):', error);
          } else {
            console.error('Echo channel error:', error);
          }
        });

        // Mark as subscribed globally
        echoSubscribed.value = true;
        window.__echoSubscribedChannels.add(channelName);
      };

      // If already connected, subscribe immediately
      if (window.Echo.connector.pusher.connection.state === 'connected') {
        subscribeToChannel();
      } else {
        // Wait for connection (only bind once)
        const connectionHandler = () => {
          console.log('Echo connected, now subscribing to:', channelName);
          subscribeToChannel();
          window.Echo.connector.pusher.connection.unbind('connected', connectionHandler);
        };
        window.Echo.connector.pusher.connection.bind('connected', connectionHandler);
      }
    } else {
      // Already subscribed, just add our listener
      const channel = window.Echo.private(channelName);
      channel.listen('.episode-search-status-updated', (event) => {
        console.log('Received episode-search-status-updated event (existing subscription):', event);
        // Event data might be nested, extract it
        const eventData = event.episode_id ? event : (event.data || event);
        updateSearchStatus(eventData);
      });
    }

  } catch (error) {
    console.error('Error setting up Echo listener:', error);
    // Fall back to polling if Echo fails
    if (episode.id) {
      pollFollowUpStatus();
    }
  }
};

onMounted(() => {
  checkExistingSearchStatus();
  setupEchoListener();
});

onUnmounted(() => {
  stopPolling();
  if (echoChannel.value) {
    window.Echo?.leave(`user.${page.props.auth?.user?.id}`);
    echoChannel.value = null;
  }
});

const submitMagnet = () => {
  magnetForm.title = episodeTitle.value;

  magnetForm.post(route('sonarr.release.push'), {
    preserveScroll: true,
    onSuccess: () => {
      magnetMessage.value = 'Release submitted to Sonarr successfully';
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

const triggerSearch = () => {
  if (!episode.sonarr_episode_id) {
    searchError.value = 'Episode is not linked to Sonarr';
    return;
  }

  searchError.value = '';
  searchMessage.value = '';
  searchStatus.value = null;
  stopPolling();

  // Use axios directly to get the command ID from the response
  searchProcessing.value = true;
  window.axios.post(route('sonarr.episodes.search'), {
    episodeIds: [episode.sonarr_episode_id]
  }).then((response) => {
    searchProcessing.value = false;
    const commandId = response.data?.id;
    
    if (commandId) {
      searchCommandId.value = commandId;
      searchStatus.value = 'queued';
      pollCommandStatus(commandId);
    } else {
      searchMessage.value = 'Search requested';
      setTimeout(() => {
        searchMessage.value = '';
      }, 5000);
    }
  }).catch((error) => {
    searchProcessing.value = false;
    if (error.response?.status === 422) {
      searchError.value = error.response.data?.message || 'Unable to trigger search';
    } else {
      searchError.value = 'Unable to trigger search';
    }
  });
};
</script>

<template>
  <div class="relative bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-shadow">
    <!-- Header with episode number and status badges -->
    <div class="px-4 pt-4 pb-3 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-sm font-mono font-semibold text-gray-500 dark:text-gray-400">
              E{{ episode.episode_number?.toString().padStart(2, '0') || '—' }}
            </span>
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 truncate">
              {{ episode.name || 'Untitled Episode' }}
            </h3>
          </div>
          <p v-if="episode.description" class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2 mt-2 leading-relaxed">
            {{ episode.description }}
          </p>
        </div>
        <div class="flex items-center gap-1.5 flex-shrink-0">
          <LikeButton
            :follow="episode"
            type="App\Models\Episode"
            class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700"
            :redirect="'/shows/' + show_id+'?season='+episode.season_id"
          />
          <span
            v-if="watched"
            class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300"
            title="Watched"
          >
            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
          </span>
        </div>
      </div>
      
      <!-- Status badges row -->
      <div class="flex items-center gap-1.5 mt-2 flex-wrap">
        <span
          v-if="isSearching"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300"
        >
          <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>{{ searchStatus === 'queued' ? 'Queued' : 'Searching' }}</span>
        </span>
        <span
          v-else-if="followUpStatus === 'added_to_queue'"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300"
        >
          <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>In Queue</span>
        </span>
        <span
          v-else-if="followUpStatus === 'nothing_found'"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300"
        >
          <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span>Not Found</span>
        </span>
        <span
          v-else-if="followUpStatus === 'already_imported'"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300"
        >
          <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>Imported</span>
        </span>
        <span
          v-if="episode.media.length === 0"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400"
        >
          <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>No Media</span>
        </span>
      </div>
    </div>

    <!-- Media files section -->
    <div v-if="episode.media.length > 0" class="px-4 py-3 space-y-2">
      <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
        Media Files
      </div>
      <div v-for="(media, index) in episode.media" :key="media.id ?? index" class="bg-gray-50 dark:bg-gray-900/50 rounded-md p-2.5 space-y-1.5">
        <div class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate" :title="media.name">
          {{ media.name }}
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <span v-if="media?.custom_properties?.languages?.length" class="text-xs text-gray-600 dark:text-gray-400">
            {{ media.custom_properties.languages.join(', ') }}
          </span>
          <div v-if="media.tags && media.tags.length" class="flex flex-wrap gap-1">
            <span
              v-for="t in media.tags"
              :key="t.id"
              class="text-[10px] px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300"
            >
              {{ tagLabel(t) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions section -->
    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-700">
      <div v-if="isAdmin" class="flex flex-wrap gap-2">
        <button
          class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          type="button"
          :disabled="magnetForm.processing"
          @click="openMagnetModal"
        >
          <svg v-if="!magnetForm.processing" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          <svg v-else class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>{{ magnetForm.processing ? 'Submitting…' : 'Submit Magnet' }}</span>
        </button>

        <button
          class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          type="button"
          :disabled="searchProcessing || isSearching"
          @click="triggerSearch"
        >
          <svg v-if="!searchProcessing && !isSearching" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <svg v-else class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>{{ searchProcessing ? 'Requesting…' : isSearching ? 'Searching…' : 'Re-search' }}</span>
        </button>
      </div>

      <!-- Messages -->
      <div v-if="magnetMessage || magnetError || searchMessage || searchError" class="mt-2 space-y-1.5">
        <div v-if="magnetMessage" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
          <svg class="h-3.5 w-3.5 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <p class="text-xs font-medium text-green-800 dark:text-green-300">{{ magnetMessage }}</p>
        </div>
        <div v-if="magnetError" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
          <svg class="h-3.5 w-3.5 text-red-600 dark:text-red-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <p class="text-xs font-medium text-red-800 dark:text-red-300">{{ magnetError }}</p>
        </div>
        <div v-if="searchMessage" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
          <svg class="h-3.5 w-3.5 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <p class="text-xs font-medium text-green-800 dark:text-green-300">{{ searchMessage }}</p>
        </div>
        <div v-if="searchError" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
          <svg class="h-3.5 w-3.5 text-red-600 dark:text-red-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <p class="text-xs font-medium text-red-800 dark:text-red-300">{{ searchError }}</p>
        </div>
      </div>
    </div>
  </div>

  <DialogModal v-if="isAdmin" :show="showMagnetModal" @close="closeMagnetModal">
    <template #title>
      Submit Magnet or Torrent
    </template>

    <template #content>
      <div class="space-y-4">
        <div>
          <InputLabel value="Magnet URL" />
          <TextInput v-model="magnetForm.magnetUrl" placeholder="magnet:?xt=urn:btih:..." />
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