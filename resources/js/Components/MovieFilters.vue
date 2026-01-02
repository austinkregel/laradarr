<script setup>
import { computed, reactive, onMounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import FilterChip from '@/Components/FilterChip.vue'

const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  basePath: {
    type: String,
    required: true,
  },
})

const page = usePage()

const parseUrlState = () => {
  const url = new URL(window.location.href)
  const qs = url.searchParams

  const getFilterValues = (key) => {
    const arr = []
    qs.forEach((value, k) => {
      if (k === `filter[${key}]` || k === `filter[${key}][]`) arr.push(value)
    })
    return arr
  }

  return {
    q: qs.get('q') ?? '',
    rating_source: qs.get('rating_source') ?? 'imdb',
    min_rating: qs.get('filter[min_rating]') ?? '',
    dub_language: qs.get('filter[dub_language]') ?? '',
    liked_only: Boolean(qs.get('filter[liked-only]')),
    watch_status: (() => {
      if (qs.get('filter[unwatched-only]')) return 'unwatched'
      if (qs.get('filter[with-watched-progress]')) return 'in_progress'
      if (qs.get('filter[completed-only]')) return 'completed'
      return ''
    })(),
    category_ids: getFilterValues('category_ids'),
    content_warning_ids: getFilterValues('content_warning_ids'),
  }
}

const state = reactive(parseUrlState())

const updateStateFromUrl = () => {
  const urlState = parseUrlState()
  Object.assign(state, urlState)
}

watch(() => page.url, () => {
  updateStateFromUrl()
}, { immediate: false })

onMounted(() => {
  updateStateFromUrl()
})

const activeChips = computed(() => {
  const chips = []
  if (state.dub_language) chips.push({ key: 'dub_language', label: `Dub: ${state.dub_language}` })
  if (state.liked_only) chips.push({ key: 'liked_only', label: 'Liked only' })
  if (state.watch_status) chips.push({ key: 'watch_status', label: `Status: ${state.watch_status.replace('_', ' ')}` })
  if (state.min_rating) chips.push({ key: 'min_rating', label: `Min ${state.rating_source.toUpperCase()}: ${state.min_rating}` })

  for (const id of state.category_ids) {
    const cat = (props.filters.categories ?? []).find((c) => String(c.id) === String(id))
    chips.push({ key: `category:${id}`, label: `Category: ${cat?.name ?? id}` })
  }
  for (const id of state.content_warning_ids) {
    const cw = (props.filters.content_warnings ?? []).find((c) => String(c.id) === String(id))
    chips.push({ key: `cw:${id}`, label: `Warning: ${cw?.name ?? id}` })
  }

  return chips
})

const buildQuery = () => {
  const params = new URLSearchParams()

  if (state.q) params.set('q', state.q)
  if (state.rating_source) params.set('rating_source', state.rating_source)

  if (state.dub_language) params.set('filter[dub_language]', state.dub_language)
  if (state.min_rating) params.set('filter[min_rating]', String(state.min_rating))
  if (state.liked_only) params.set('filter[liked-only]', '1')

  if (state.watch_status === 'unwatched') params.set('filter[unwatched-only]', '1')
  if (state.watch_status === 'in_progress') params.set('filter[with-watched-progress]', '1')
  if (state.watch_status === 'completed') params.set('filter[completed-only]', '1')

  for (const id of state.category_ids) params.append('filter[category_ids][]', String(id))
  for (const id of state.content_warning_ids) params.append('filter[content_warning_ids][]', String(id))

  return params
}

const apply = () => {
  const params = buildQuery()
  router.get(`${props.basePath}?${params.toString()}`, {}, { preserveState: true, preserveScroll: true, replace: true })
}

const clearAll = () => {
  state.q = ''
  state.rating_source = 'imdb'
  state.min_rating = ''
  state.dub_language = ''
  state.liked_only = false
  state.watch_status = ''
  state.category_ids = []
  state.content_warning_ids = []
  apply()
}

const removeChip = (chipKey) => {
  if (chipKey === 'dub_language') state.dub_language = ''
  else if (chipKey === 'liked_only') state.liked_only = false
  else if (chipKey === 'watch_status') state.watch_status = ''
  else if (chipKey === 'min_rating') state.min_rating = ''
  else if (chipKey.startsWith('category:')) state.category_ids = state.category_ids.filter((v) => `category:${v}` !== chipKey)
  else if (chipKey.startsWith('cw:')) state.content_warning_ids = state.content_warning_ids.filter((v) => `cw:${v}` !== chipKey)
  apply()
}
</script>

<template>
  <div class="rounded-lg bg-white dark:bg-gray-950 ring-1 ring-gray-200 dark:ring-gray-800 p-4">
    <div class="flex items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-3">
      <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Filters</div>
      <div class="flex items-center gap-2">
        <button type="button" class="text-xs text-gray-600 dark:text-gray-300 hover:underline" @click="clearAll">
          Clear
        </button>
        <button
          type="button"
          class="rounded-md bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 px-3 py-1.5 text-xs font-semibold"
          @click="apply"
        >
          Apply
        </button>
      </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-2" v-if="activeChips.length">
      <FilterChip v-for="chip in activeChips" :key="chip.key" :label="chip.label" @remove="removeChip(chip.key)" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4">
      <div>
        <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Search</label>
        <input
          v-model="state.q"
          type="text"
          class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400/40 dark:focus:ring-gray-500/40"
          placeholder="Name…"
          @keyup.enter="apply"
        />
      </div>

      <div>
        <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Dub language</label>
        <select
          v-model="state.dub_language"
          class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400/40 dark:focus:ring-gray-500/40"
        >
          <option value="">Any</option>
          <option v-for="l in (filters.dub_languages ?? [])" :key="l" :value="l">{{ l }}</option>
        </select>
      </div>

      <div>
        <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Watch status</label>
        <select
          v-model="state.watch_status"
          class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400/40 dark:focus:ring-gray-500/40"
        >
          <option value="">Any</option>
          <option value="unwatched">Unwatched</option>
          <option value="in_progress">In progress</option>
          <option value="completed">Completed</option>
        </select>
      </div>

      <div>
        <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Likes</label>
        <label
          for="liked-only"
          class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 flex items-center justify-between gap-3 text-sm text-gray-900 dark:text-gray-100 select-none cursor-pointer"
        >
          <span class="text-sm text-gray-900 dark:text-gray-100">Show liked only</span>
          <span class="relative inline-flex h-5 w-9 items-center rounded-full bg-gray-300 dark:bg-gray-700 transition-colors">
            <input
              id="liked-only"
              v-model="state.liked_only"
              type="checkbox"
              class="sr-only peer"
            />
            <span
              class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform translate-x-0.5 peer-checked:translate-x-4"
            />
          </span>
        </label>
      </div>

      <div>
        <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Rating source</label>
        <select
          v-model="state.rating_source"
          class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400/40 dark:focus:ring-gray-500/40"
        >
          <option v-for="s in (filters.rating_sources ?? [])" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>

      <div>
        <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Min rating</label>
        <input
          v-model="state.min_rating"
          type="number"
          step="0.1"
          min="0"
          max="10"
          class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400/40 dark:focus:ring-gray-500/40"
          placeholder="e.g. 7.5"
        />
      </div>

      <div>
        <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Categories</label>
        <select
          v-model="state.category_ids"
          multiple
          class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 h-28 focus:outline-none focus:ring-2 focus:ring-gray-400/40 dark:focus:ring-gray-500/40"
        >
          <option v-for="c in (filters.categories ?? [])" :key="c.id" :value="String(c.id)">
            {{ c.name }} <span v-if="c.type">({{ c.type }})</span>
          </option>
        </select>
      </div>

      <div>
        <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Content warnings</label>
        <select
          v-model="state.content_warning_ids"
          multiple
          class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 h-28 focus:outline-none focus:ring-2 focus:ring-gray-400/40 dark:focus:ring-gray-500/40"
        >
          <option v-for="c in (filters.content_warnings ?? [])" :key="c.id" :value="String(c.id)">
            {{ c.name }}
          </option>
        </select>
      </div>
    </div>
  </div>
</template>

