<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50 flex mx-4 p-4 rounded-xl overflow-hidden">
            <div class="w-1/4">
                <img src="{{ $show->poster_image }}" alt="{{ $show->name }}" />

                <div class="mt-4">
                    <div class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $show->name }}</div>
                    @if(!empty(config('services.sonarr.url')))
                        <a href="{{ config('services.sonarr.url') }}/series/{{ $show->slug }}" target="_blank" class="text-sm text-blue-500 dark:text-blue-400">Sonarr</a>
                    @endif
                </div>
            </div>
            <div class="w-3/4 px-4">

                <x-single-show-table
                    :data="$show->seasons()->get()"
                    :header="$show->name"
                    :description="$show->description"
                />
            </div>

        </div>
        </div>
    </div>
</x-app-layout>
