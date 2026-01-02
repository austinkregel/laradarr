<template>
  <div class="relative">
    <button @click="like" :class="[loading ? 'animate-wave': '', 'flex items-center gap-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 transition-colors']">
      <span v-if="liked">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"></path></svg>
      </span>
      <span v-else>
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path></svg>
      </span>

    </button>

    <!-- Success Message -->
    <div
      v-if="message && messageType === 'success'"
      class="absolute top-full left-0 mt-2 px-3 py-2 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-sm text-green-800 dark:text-green-200 whitespace-nowrap z-50"
    >
      <div class="flex items-center gap-2">
        <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span>{{ message }}</span>
      </div>
    </div>

    <!-- Error Message -->
    <div
      v-if="message && messageType === 'error'"
      class="absolute top-full left-0 mt-2 px-3 py-2 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-800 dark:text-red-200 whitespace-nowrap z-50"
    >
      <div class="flex items-center gap-2">
        <svg class="h-4 w-4 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        <span>{{ message }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

const page = usePage();
const loading = ref(false);
const message = ref('');
const messageType = ref('success');
let messageTimeout = null;

const { follow, type, redirect } = defineProps({
  follow: {
    type: Object,
    default: {
      id: null,
    }
  },
  type: {
    type: String,
    default: 'App\\Models\\Dispensary'
  },
  redirect: {
    type: String,
    default: '/login'
  }
});

// Use page.props reactively so it updates when Inertia updates the page
const liked = computed(() => {
  const user = page.props.auth?.user;
  if (!user?.favorites) {
    return false;
  }
  return user.favorites.some(favorite => 
    favorite.favoriteable_id == follow.id && 
    favorite.favoriteable_type == type
  );
});

// Watch for flash messages after redirect
watch(() => page.props.flash, (flash) => {
  if (flash?.success) {
    showMessage(flash.success, 'success');
  } else if (flash?.error) {
    showMessage(flash.error, 'error');
  }
}, { deep: true });

function showMessage(msg, type) {
  message.value = msg;
  messageType.value = type;
  
  // Clear message after 3 seconds
  if (messageTimeout) {
    clearTimeout(messageTimeout);
  }
  messageTimeout = setTimeout(() => {
    message.value = '';
  }, 3000);
}

async function like() {
  loading.value = true;
  message.value = ''; // Clear any existing message
  
  // Remember the current state to show the correct message
  const wasLiked = liked.value;
  
  // Preserve query parameters from current URL
  const currentUrl = new URL(window.location.href);
  const queryString = currentUrl.search;
  
  // Build redirect URL with query parameters
  const redirectUrl = redirect + (queryString ? queryString : '');
  
  await router.post('/favorite', {
    likeable_type: type,
    likeable_id: follow?.id,
    redirect: redirectUrl
  }, {
    preserveScroll: true,
    onSuccess: () => {
      // Show success message based on what action was performed
      showMessage(wasLiked ? 'Unliked successfully' : 'Liked successfully', 'success');
    },
    onError: (errors) => {
      // Handle validation errors or other errors
      const errorMessage = errors.message || errors.favorite || Object.values(errors)[0] || 'An error occurred';
      showMessage(errorMessage, 'error');
    },
    onFinish: () => {
      loading.value = false;
    }
  });
}
</script>