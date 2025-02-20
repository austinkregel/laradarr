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
const { shows } = defineProps({
  'shows': {
    type: Object,
    required: true,
  }
})

</script>

<template>
    <AppLayout title="Watch History">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Watch History
            </h2>
        </template>

        <div class="pb-12 max-w-[100rem] mx-auto">
            <div class="sm:px-6 lg:px-8">
                <div class="my-8">
                  <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 leading-tight px-4">Watched</h3>
                </div>

                <div class="grid grid-cols-5 gap-4" v-if="shows.data.length > 0">
                  <div v-for="show in shows.data">
                    <WatchedEpisode :episode="show" />
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
