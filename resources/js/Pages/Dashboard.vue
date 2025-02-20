<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';
import { Link } from "@inertiajs/vue3";
import dayjs from "dayjs";
import relativeTime from 'dayjs/plugin/relativeTime';
import utc from 'dayjs/plugin/utc';
import {Battery0Icon, Battery100Icon, Battery50Icon, FunnelIcon, LanguageIcon} from "@heroicons/vue/24/solid";
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';
import { Listbox, ListboxButton, ListboxLabel, ListboxOption, ListboxOptions } from '@headlessui/vue'
import { ChevronUpDownIcon, CheckCircleIcon, ChevronDownIcon } from '@heroicons/vue/16/solid'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import {CheckBadgeIcon, CheckIcon} from '@heroicons/vue/20/solid'
import {computed, ref} from "vue";
import { buildUrl} from '@kbco/query-builder'
import Episode from "@/Components/Episode.vue";
import WatchedEpisode from "@/Components/WatchedEpisode.vue";

dayjs.extend(relativeTime)
dayjs.extend(utc);
const { shows, recently_watched } = defineProps({
  'shows': {
    type: Object,
    required: true,
  },
  recently_watched: {
    type: Array,
    required: true,
  }
})

const lastWatched = (show) => {
  if (!show.last_watched_at) {
    return 'Not watched'
  }

  return dayjs.utc(show.last_watched_at).fromNow()
}

const countWatchedEpisodes = (show) => {
  return parseInt(show.watchers_count)
}

const filters = [
  {
    name: 'Unwatched Only',
    description: 'All series where you have not watched any episodes',
    href: buildUrl('/dashboard', {
      filter: {
        'unwatched-only': true
      }
    }),
    icon: Battery0Icon
  },
  {
    name: 'In Progress',
    description: 'All series where you have watched at least one episode, but have not completed the series',
    href: buildUrl('/dashboard', {
      filter: {
        'with-watched-progress': true
      }
    }),
    icon: Battery50Icon
  },
  {
    name: 'Complete Collections',
    description: 'All series where you possess all episodes',
    href: buildUrl('/dashboard', {
      filter: {
        'complete-collection': true
      }
    }),
    icon: CheckBadgeIcon,
  },
  {
    name: 'English Dubbed',
    description: 'All series with English listed as a language',
    href: buildUrl('/dashboard', {
      filter: {
        'english-only': true
      }
    }),
    icon: LanguageIcon
  },
];

const activeFilter = computed(() => {
  // Need to url decode the search params

  return filters.find(filter => (window.location.protocol+'//'+window.location.host+filter.href) === window.location.href.replace('%5B', '[').replace('%5D', ']'))
})
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="pb-12 max-w-[100rem] mx-auto">
            <div class="sm:px-6 lg:px-8">
                <div class="my-8">
                  <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 leading-tight px-4">Recently Watched</h3>
                </div>

                <div class="grid grid-cols-5 gap-4" v-if="recently_watched.length > 0">
                  <div v-for="show in recently_watched">
                    <WatchedEpisode :episode="show" />
                  </div>
                </div>

                <div>
                  <Link href="/watched-shows" class="text-sm text-gray-600 dark:text-gray-400 leading-tight px-4">View all watched shows</Link>
                </div>

                <div class="border-t border-gray-700 my-6"></div>

                <div class="mb-2">
                  <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 leading-tight px-4">Shows</h3>
                  <div class="text-sm text-gray-600 dark:text-gray-400 leading-tight px-4">{{ activeFilter?.description }}</div>
                </div>

                <div class="relative max-w-sm px-2 z-50 mb-8">
                  <Menu as="div" class="relative inline-block text-left">
                    <div>
                      <MenuButton class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-white dark:bg-gray-950 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-50 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:ring-gray-700 dark:hover:bg-gray-800">
                        {{ activeFilter?.name ?? 'Filters' }}
                        <ChevronDownIcon class="-mr-1 size-5 text-gray-400" aria-hidden="true" />
                      </MenuButton>
                    </div>

                    <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                      <MenuItems class="absolute left-0 z-10 mt-2 w-56 origin-top-left divide-y divide-gray-100 dark:divide-gray-700 rounded-md bg-white dark:bg-gray-950 shadow-lg ring-1 ring-black/5 focus:outline-none">
                        <div class="py-1" v-for="filter in filters">
                          <MenuItem v-slot="{ active }">
                            <Link :href="filter.href" :class="[active ? 'bg-gray-100 text-gray-900 outline-none dark:bg-gray-800 dark:text-gray-50' : 'text-gray-700 dark:text-gray-200', 'group flex items-center px-4 py-2 text-sm']">
                              <Component :is="filter.icon" :class="[active ? 'text-gray-500 dark:text-gray-50' : '', 'mr-3 size-5 text-gray-400 dark:text-gray-400']" aria-hidden="true" />
                              {{ filter.name }}
                            </Link>
                          </MenuItem>
                        </div>
                      </MenuItems>
                    </transition>
                  </Menu>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-5 items-center gap-4">
                  <div v-for="show in shows.data" :key="show.id" class="relative min-w-64 bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">

                    <CheckCircleIcon v-if="show.completed_count > 0" class="w-6 h-6 text-green-400 m-4 z-10 absolute right-0" />
                    <div class="bg-gray-950 ">
                      <img :src="show.poster_image" :alt="show.title" class="max-h-64 object-cover mx-auto">
                    </div>
                    <div>
                      <Link :href="`/shows/${show.id}`" class="block text-lg font-semibold text-gray-950 dark:text-gray-200 leading-tight px-4 py-2 truncate">{{ show.name }}</Link>
                      <div class="text-sm text-white px-4">
                        {{show.season_count }} seasons / {{ show.episode_count }} episodes
                      </div>
                      <div class="text-sm text-white px-4 pb-4">
                        {{ lastWatched(show) }} / {{ countWatchedEpisodes(show) }} episodes watched
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-8 flex items-center justify-center">
                  <div v-for="link in shows.links" :key="link">
                    <Link
                      :href="link.url"
                      class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 leading-5 rounded-md focus:outline-none focus:shadow-outline-blue active:bg-gray-100 dark:active:bg-gray-700 transition ease-in-out duration-150"
                      :class="{ 'bg-gray-100 dark:bg-gray-700': link.active }"
                      v-html="link.label"
                    >
                    </Link>

                  </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
