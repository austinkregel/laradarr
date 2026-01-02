<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

const props = defineProps({
  credentials: {
    type: Array,
    required: true,
  },
})

const form = reactive({
  service: '',
  key: '',
  value: '',
  is_enabled: true,
  expires_at: '',
})

const saving = ref(false)

const createCredential = async () => {
  saving.value = true
  try {
    await router.post(route('credentials.store'), form, { preserveScroll: true })
    form.service = ''
    form.key = ''
    form.value = ''
    form.is_enabled = true
    form.expires_at = ''
  } finally {
    saving.value = false
  }
}

const updateValue = (credId, value) => {
  router.put(route('credentials.update', credId), { value }, { preserveScroll: true })
}

const toggleEnabled = (cred) => {
  router.put(route('credentials.update', cred.id), { is_enabled: !cred.is_enabled }, { preserveScroll: true })
}

const updateExpiry = (credId, expires_at) => {
  router.put(route('credentials.update', credId), { expires_at: expires_at || null }, { preserveScroll: true })
}

const deleteCredential = (credId) => {
  if (!confirm('Delete this credential?')) return
  router.delete(route('credentials.destroy', credId), { preserveScroll: true })
}

const grouped = computed(() => {
  const byService = {}
  for (const c of props.credentials) {
    const s = c.service ?? 'unknown'
    if (!byService[s]) byService[s] = []
    byService[s].push(c)
  }
  return Object.entries(byService).sort(([a], [b]) => a.localeCompare(b))
})
</script>

<template>
  <AppLayout title="Credentials">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Credentials
      </h2>
    </template>

    <div class="py-8 max-w-6xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
        <div class="text-sm text-gray-600 dark:text-gray-300">
          Values are write-only (we never display stored secrets). You can replace a value by typing a new one and saving.
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <div>
            <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Service</label>
            <input v-model="form.service" class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm" placeholder="trakt" />
          </div>
          <div>
            <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Key</label>
            <input v-model="form.key" class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm" placeholder="access_token" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Value</label>
            <input v-model="form.value" type="password" class="w-full rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm" placeholder="(paste secret)" />
          </div>
          <div class="flex items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
              <input type="checkbox" v-model="form.is_enabled" class="rounded border-gray-300 dark:border-gray-700" />
              Enabled
            </label>
            <button
              type="button"
              class="ml-auto rounded-md bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 px-4 py-2 text-sm font-semibold disabled:opacity-60"
              :disabled="saving || !form.service || !form.key"
              @click="createCredential"
            >
              Save
            </button>
          </div>
        </div>

        <div class="mt-10 space-y-10">
          <div v-for="[service, creds] in grouped" :key="service">
            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
              {{ service }}
            </div>

            <div class="overflow-x-auto rounded-lg ring-1 ring-gray-200 dark:ring-gray-700">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Key</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Enabled</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Expires</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Last used</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Update value</th>
                    <th class="px-4 py-2"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                  <tr v-for="c in creds" :key="c.id">
                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ c.key }}</td>
                    <td class="px-4 py-3">
                      <button
                        type="button"
                        class="text-xs rounded-full px-3 py-1"
                        :class="c.is_enabled ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-900/40 dark:text-gray-300'"
                        @click="toggleEnabled(c)"
                      >
                        {{ c.is_enabled ? 'Enabled' : 'Disabled' }}
                      </button>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                      <input
                        type="datetime-local"
                        class="rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-2 py-1 text-xs"
                        :value="c.expires_at ? c.expires_at.replace('Z','').slice(0,16) : ''"
                        @change="updateExpiry(c.id, $event.target.value)"
                      />
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                      {{ c.last_used_at ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                      <div class="flex items-center gap-2">
                        <input
                          type="password"
                          class="w-64 rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-2 py-1 text-xs"
                          placeholder="new value…"
                          @keyup.enter="updateValue(c.id, $event.target.value)"
                        />
                        <button
                          type="button"
                          class="rounded-md bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 px-3 py-1 text-xs font-semibold"
                          @click="updateValue(c.id, $event.target.previousElementSibling.value)"
                        >
                          Update
                        </button>
                      </div>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <button type="button" class="text-xs text-red-600 hover:underline" @click="deleteCredential(c.id)">
                        Delete
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>





