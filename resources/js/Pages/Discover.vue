<script setup>
import { computed, ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  activeTab: {
    type: String,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ genres: [], formats: [] }),
  },
  shows: {
    type: Object,
    default: null,
  },
  movies: {
    type: Object,
    default: null,
  },
  q: {
    type: String,
    default: '',
  },
  sort: {
    type: String,
    default: 'recommendation',
  },
  genre: {
    type: String,
    default: null,
  },
  format: {
    type: String,
    default: null,
  },
})

const search = ref(props.q ?? '')
const sort = ref(props.sort ?? 'recommendation')
const genre = ref(props.genre ?? null)
const format = ref(props.format ?? null)

watch(
  () => props.q,
  (next) => {
    search.value = next ?? ''
  }
)

watch(
  () => props.sort,
  (next) => {
    sort.value = next ?? 'recommendation'
  }
)

watch(
  () => props.genre,
  (next) => {
    genre.value = next ?? null
  }
)

watch(
  () => props.format,
  (next) => {
    format.value = next ?? null
  }
)

const activeRoute = computed(() => (props.activeTab === 'movies' ? route('discover.movies') : route('discover.shows')))

const navigate = (overrides = {}) => {
  router.get(
    activeRoute.value,
    {
      q: search.value || undefined,
      sort: sort.value || undefined,
      genre: genre.value || undefined,
      format: format.value || undefined,
      ...overrides,
    },
    { preserveScroll: true, preserveState: true, replace: true }
  )
}

let searchTimer = null
watch(search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => navigate({ page: 1 }), 300)
})

const setSort = (mode) => {
  sort.value = mode
  navigate({ sort: mode, page: 1 })
}

const pagePayload = computed(() => (props.activeTab === 'movies' ? props.movies : props.shows))

const items = computed(() => pagePayload.value?.data ?? [])

const unwrap = (entry) => entry?.show ?? entry?.movie ?? entry
const score = (entry) => entry?.score ?? null

const tabQuery = computed(() => ({
  q: search.value || undefined,
  sort: sort.value || undefined,
  genre: genre.value || undefined,
  format: format.value || undefined,
}))

const buttonClass = (active) => [
  'px-3',
  'py-1.5',
  'text-sm',
  'font-semibold',
  'rounded-md',
  active ? 'bg-blue-600 text-white shadow' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
]

const genreLabel = (g) => {
  if (!g) return ''
  const words = String(g).split('-').filter(Boolean)
  return words.map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
}
</script>

<template>
  <AppLayout title="Discover">
    <template #header>
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Discover</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            Fresh picks from the last 30 years that aren’t in your library yet — ranked by your watch history and likes.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <Link
            :href="route('discover.shows', tabQuery)"
            :class="buttonClass(activeTab === 'shows')"
          >
            Shows
          </Link>
          <Link
            :href="route('discover.movies', tabQuery)"
            :class="buttonClass(activeTab === 'movies')"
          >
            Movies
          </Link>
        </div>
      </div>
    </template>

    <div class="pb-10 max-w-[100rem] mx-auto">
      <div class="sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div class="flex-1 flex flex-col gap-3 md:flex-row md:items-center md:gap-3">
            <input
              v-model="search"
              type="search"
              placeholder="Search…"
              class="w-full max-w-xl rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500"
            />

            <select
              v-model="genre"
              class="w-full md:w-56 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500"
              @change="navigate({ page: 1 })"
            >
              <option :value="null">All genres</option>
              <option v-for="g in (props.filters?.genres ?? [])" :key="g" :value="g">{{ genreLabel(g) }}</option>
            </select>

            <select
              v-model="format"
              class="w-full md:w-44 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500"
              @change="navigate({ page: 1 })"
            >
              <option :value="null">All</option>
              <option value="live_action">Live action</option>
              <option value="animated">Animated</option>
            </select>
          </div>

          <div class="flex flex-wrap gap-2">
            <button type="button" :class="buttonClass(sort === 'recommendation')" @click="setSort('recommendation')">
              Best match
            </button>
            <button type="button" :class="buttonClass(sort === 'year')" @click="setSort('year')">
              Newest
            </button>
            <button type="button" :class="buttonClass(sort === 'rating')" @click="setSort('rating')">
              Highest rated
            </button>
          </div>
        </div>

        <div v-if="items.length === 0" class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center">
          <div class="text-gray-700 dark:text-gray-200 font-semibold">No results</div>
          <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Try a different search, or adjust the sort.
          </div>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-6">
          <div
            v-for="entry in items"
            :key="(unwrap(entry)?.tmdb_id ?? unwrap(entry)?.trakt_id ?? unwrap(entry)?.id) + '-' + activeTab"
            class="relative bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg"
          >
            <div class="bg-gray-950">
              <img
                v-if="unwrap(entry)?.poster_image"
                :src="unwrap(entry).poster_image"
                :alt="unwrap(entry)?.name"
                class="h-64 w-full object-cover"
                loading="lazy"
              />
              <div v-else class="h-64 w-full bg-gradient-to-br from-gray-700 to-gray-900" />
            </div>

            <div class="p-3">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-sm font-semibold text-gray-950 dark:text-gray-100 leading-tight truncate">
                    {{ unwrap(entry)?.name }}
                  </div>
                  <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                    <span v-if="unwrap(entry)?.release_year">{{ unwrap(entry).release_year }}</span>
                    <span v-else>—</span>
                    <span v-if="(unwrap(entry)?.genres ?? []).length" class="mx-2 text-gray-400">·</span>
                    <span v-if="(unwrap(entry)?.genres ?? []).length" class="truncate">
                      {{ (unwrap(entry).genres ?? []).slice(0, 2).join(', ') }}
                    </span>
                  </div>
                </div>

                <div class="shrink-0 text-right text-[10px] text-gray-600 dark:text-gray-300">
                  <div v-if="unwrap(entry)?.vote_average">★ {{ unwrap(entry).vote_average }}</div>
                  <div v-else>—</div>
                  <div v-if="score(entry) !== null && sort === 'recommendation'" class="text-gray-500 dark:text-gray-400">
                    match {{ Number(score(entry)).toFixed(2) }}
                  </div>
                </div>
              </div>

              <div class="mt-2 flex items-center justify-between text-[10px] text-gray-600 dark:text-gray-300">
                <div class="truncate max-w-[70%]">
                  <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200">
                    {{ unwrap(entry)?.source ?? 'unknown' }}
                  </span>
                </div>
                <div class="text-gray-500 dark:text-gray-400">
                  <span v-if="unwrap(entry)?.vote_count">{{ unwrap(entry).vote_count }} votes</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="pagePayload?.links?.length" class="mt-8 flex items-center justify-center">
          <div v-for="link in pagePayload.links" :key="link.url ?? link.label">
            <Link
              :href="link.url"
              class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 leading-5 rounded-md focus:outline-none transition ease-in-out duration-150"
              :class="{ 'bg-gray-100 dark:bg-gray-700': link.active, 'opacity-50 pointer-events-none': !link.url }"
            >
              <span v-html="link.label"></span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

