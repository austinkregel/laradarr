<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ActionSection from '@/Components/ActionSection.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const page = usePage();

const traktConnected = computed(() => page.props.traktConnected);

const isConnecting = ref(false);
const isDisconnecting = ref(false);
const deviceCode = ref(null);
const userCode = ref(null);
const verificationUrl = ref(null);
const pollInterval = ref(null);
const statusMessage = ref('');
const errorMessage = ref('');

const startTraktAuth = async () => {
  isConnecting.value = true;
  errorMessage.value = '';
  statusMessage.value = '';

  try {
    const response = await window.axios.post(route('trakt.device'));
    
    userCode.value = response.data.user_code;
    verificationUrl.value = response.data.verification_url;
    deviceCode.value = true; // We don't need to store the actual code, server keeps it
    
    statusMessage.value = 'Waiting for authorization...';
    
    // Start polling
    const interval = (response.data.interval || 5) * 1000;
    pollInterval.value = setInterval(pollForToken, Math.max(interval, 5000));
  } catch (error) {
    errorMessage.value = error.response?.data?.error || 'Failed to start Trakt authorization';
    isConnecting.value = false;
  }
};

const pollForToken = async () => {
  try {
    const response = await window.axios.post(route('trakt.poll'));
    
    if (response.data.status === 'success') {
      stopPolling();
      statusMessage.value = 'Connected successfully!';
      // Reload the page to update the shared props
      window.location.reload();
    } else if (response.data.status === 'pending') {
      statusMessage.value = 'Waiting for authorization...';
    } else if (response.data.status === 'slow_down') {
      statusMessage.value = 'Please wait...';
    }
  } catch (error) {
    if (error.response?.data?.status === 'expired') {
      stopPolling();
      errorMessage.value = 'Authorization expired. Please try again.';
      resetAuthState();
    } else if (error.response?.status !== 400) {
      // Don't stop on pending/slow_down errors
      stopPolling();
      errorMessage.value = error.response?.data?.message || 'Authorization failed';
      resetAuthState();
    }
  }
};

const stopPolling = () => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value);
    pollInterval.value = null;
  }
};

const cancelAuth = () => {
  stopPolling();
  resetAuthState();
};

const resetAuthState = () => {
  isConnecting.value = false;
  deviceCode.value = null;
  userCode.value = null;
  verificationUrl.value = null;
};

const disconnectTrakt = async () => {
  if (!confirm('Are you sure you want to disconnect your Trakt account? Your watch history will remain, but syncing will stop.')) {
    return;
  }

  isDisconnecting.value = true;
  errorMessage.value = '';

  try {
    await window.axios.delete(route('trakt.disconnect'));
    window.location.reload();
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to disconnect Trakt';
    isDisconnecting.value = false;
  }
};

onUnmounted(() => {
  stopPolling();
});
</script>

<template>
  <ActionSection>
    <template #title>
      Trakt.tv Integration
    </template>

    <template #description>
      Connect your Trakt.tv account to automatically sync your watch history.
    </template>

    <template #content>
      <!-- Connected State -->
      <div v-if="traktConnected" class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0">
            <svg class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
              Trakt account connected
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Your watch history is syncing automatically.
            </p>
          </div>
        </div>

        <DangerButton
          :disabled="isDisconnecting"
          @click="disconnectTrakt"
        >
          {{ isDisconnecting ? 'Disconnecting...' : 'Disconnect' }}
        </DangerButton>
      </div>

      <!-- Connecting State (Device Auth Flow) -->
      <div v-else-if="deviceCode" class="space-y-4">
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6 text-center">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            To connect your Trakt account:
          </p>
          
          <div class="space-y-4">
            <div>
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                1. Visit this URL:
              </p>
              <a
                :href="verificationUrl"
                target="_blank"
                class="text-blue-600 dark:text-blue-400 hover:underline font-mono text-sm"
              >
                {{ verificationUrl }}
              </a>
            </div>

            <div>
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                2. Enter this code:
              </p>
              <p class="text-3xl font-bold font-mono text-gray-900 dark:text-gray-100 tracking-widest mt-1">
                {{ userCode }}
              </p>
            </div>
          </div>

          <div class="mt-6 flex items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ statusMessage }}</span>
          </div>
        </div>

        <div class="flex justify-end">
          <SecondaryButton @click="cancelAuth">
            Cancel
          </SecondaryButton>
        </div>
      </div>

      <!-- Not Connected State -->
      <div v-else class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0">
            <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
              Trakt account not connected
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Connect to sync your watch history from Trakt.tv.
            </p>
          </div>
        </div>

        <PrimaryButton
          :disabled="isConnecting"
          @click="startTraktAuth"
        >
          {{ isConnecting ? 'Connecting...' : 'Connect Trakt' }}
        </PrimaryButton>
      </div>

      <!-- Error Message -->
      <div v-if="errorMessage" class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <p class="text-sm text-red-700 dark:text-red-300">
          {{ errorMessage }}
        </p>
      </div>
    </template>
  </ActionSection>
</template>
