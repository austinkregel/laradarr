<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);

const props = defineProps({
  flags: {
    type: Object,
    required: true,
  },
  unresolvedCount: {
    type: Number,
    default: 0,
  },
});

const markResolved = (flagId) => {
  router.post(route('manual-imports.resolve', { flag: flagId }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      // Flag will be removed from the list automatically via Inertia
    },
  });
};

const triggerSonarrImport = (flagId) => {
  router.post(route('manual-imports.trigger-sonarr-import', { flag: flagId }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      // Flag will be removed from the list automatically via Inertia
    },
  });
};
</script>

<template>
  <AppLayout title="Manual Imports">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Manual Imports
        </h2>
        <Link href="/dashboard/shows" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
          Back to Shows Dashboard
        </Link>
      </div>
    </template>

    <div class="pb-12 max-w-[100rem] mx-auto">
      <div class="sm:px-6 lg:px-8">
        <!-- Success Message -->
        <div
          v-if="$page.props.flash?.success"
          class="mb-4 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4"
        >
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800 dark:text-green-200">
                {{ $page.props.flash.success }}
              </p>
            </div>
          </div>
        </div>

        <div v-if="flags.data.length === 0" class="text-center py-12">
          <p class="text-gray-500 dark:text-gray-400 text-lg">
            No manual imports needed. All completed downloads have been imported by Sonarr.
          </p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="flag in flags.data"
            :key="flag.id"
            class="bg-white dark:bg-gray-800 rounded-lg shadow p-6"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                  {{ flag.torrent_name }}
                </h3>
                
                <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                  <div>
                    <span class="font-medium">Torrent Hash:</span>
                    <code class="ml-2 px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">
                      {{ flag.torrent_hash }}
                    </code>
                  </div>

                  <div v-if="flag.content_path">
                    <span class="font-medium">Content Path:</span>
                    <code class="ml-2 px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs break-all">
                      {{ flag.content_path }}
                    </code>
                  </div>

                  <div v-if="flag.file_paths && flag.file_paths.length > 0">
                    <span class="font-medium">Files ({{ flag.file_paths.length }}):</span>
                    <ul class="mt-1 ml-4 list-disc space-y-1">
                      <li
                        v-for="(filePath, index) in flag.file_paths.slice(0, 5)"
                        :key="index"
                        class="text-xs"
                      >
                        <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">
                          {{ filePath }}
                        </code>
                      </li>
                      <li v-if="flag.file_paths.length > 5" class="text-xs text-gray-500">
                        ... and {{ flag.file_paths.length - 5 }} more
                      </li>
                    </ul>
                  </div>

                  <div v-if="flag.reason">
                    <span class="font-medium">Reason:</span>
                    <span class="ml-2">{{ flag.reason }}</span>
                  </div>

                  <div>
                    <span class="font-medium">Flagged:</span>
                    <span class="ml-2">{{ dayjs(flag.created_at).fromNow() }}</span>
                  </div>
                </div>
              </div>

              <div class="ml-4 flex gap-2">
                <PrimaryButton
                  v-if="flag.content_path"
                  @click="triggerSonarrImport(flag.id)"
                  class="bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500"
                >
                  Import via Sonarr
                </PrimaryButton>
                <PrimaryButton
                  @click="markResolved(flag.id)"
                  class="bg-green-600 hover:bg-green-700 focus:ring-green-500"
                >
                  Mark Resolved
                </PrimaryButton>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="flags.links && flags.links.length > 3" class="mt-8 flex items-center justify-center">
            <Link
              v-for="link in flags.links"
              :key="link.label"
              :href="link.url || '#'"
              :class="[
                'px-4 py-2 text-sm rounded-md transition',
                link.active
                  ? 'bg-indigo-600 text-white'
                  : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                !link.url && 'opacity-50 cursor-not-allowed'
              ]"
            >
              <span v-html="link.label"></span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

