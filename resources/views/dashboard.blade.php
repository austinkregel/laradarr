<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Library') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="w-full text-white -mt-6 mb-6">
                <div class="text-right">
                    <div class="relative inline-block text-left">
                        <div class="flex justify-end">
                            <div>
                                @php
                                $filter = request()->has('filter') ? collect($filters)->filter(fn ($filter) => request()->fullUrl() == $filter['link'])->first() : collect($filters)->first();
                                @endphp

                                <a href="{{ $filter['link'] }}" class="group flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200" role="menuitem" tabindex="-1" id="menu-item-2">
                                    <!-- Active: "text-gray-500", Not Active: "" -->
                                    {!! $filter['icon'] !!}
                                    {{ $filter['name'] }}
                                </a>

                            </div>

                            <div>
                                <button type="button" class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-white dark:bg-gray-950 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50  dark:hover:bg-gray-900" id="menu-button" aria-expanded="true" aria-haspopup="true">
                                    Options
                                    <svg class="-mr-1 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <!--
                          Dropdown menu, show/hide based on menu state.

                          Entering: "transition ease-out duration-100"
                            From: "transform opacity-0 scale-95"
                            To: "transform opacity-100 scale-100"
                          Leaving: "transition ease-in duration-75"
                            From: "transform opacity-100 scale-100"
                            To: "transform opacity-0 scale-95"
                        -->

                        <div class="{{ request()->has('filter') ? 'hidden' : '' }} absolute right-0 z-10 mt-2 w-56 origin-top-right divide-y divide-gray-100 dark:divide-gray-700 rounded-md bg-white dark:bg-gray-950 shadow-lg ring-1 ring-black/5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1">
                            <div class="py-1" role="none">
                            @foreach(($filters?? []) as $filter)
                                <a href="{{ $filter['link'] }}" class="group flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200" role="menuitem" tabindex="-1" id="menu-item-2">
                                    <!-- Active: "text-gray-500", Not Active: "" -->
                                    {!! $filter['icon'] !!}
                                    {{ $filter['name'] }}
                                </a>
                            @endforeach
                            </div>

                            <div class="py-1" role="none">
                                <a href="#" class="group flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200" role="menuitem" tabindex="-1" id="menu-item-4">
                                    <!-- Active: "text-gray-500", Not Active: "" -->
                                    <svg class="mr-3 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                                        <path d="M10 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM1.615 16.428a1.224 1.224 0 0 1-.569-1.175 6.002 6.002 0 0 1 11.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 0 1 7 18a9.953 9.953 0 0 1-5.385-1.572ZM16.25 5.75a.75.75 0 0 0-1.5 0v2h-2a.75.75 0 0 0 0 1.5h2v2a.75.75 0 0 0 1.5 0v-2h2a.75.75 0 0 0 0-1.5h-2v-2Z" />
                                    </svg>
                                    Share
                                </a>
                                <a href="#" class="group flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200" role="menuitem" tabindex="-1" id="menu-item-5">
                                    <!-- Active: "text-gray-500", Not Active: "" -->
                                    <svg class="mr-3 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                                        <path d="m9.653 16.915-.005-.003-.019-.01a20.759 20.759 0 0 1-1.162-.682 22.045 22.045 0 0 1-2.582-1.9C4.045 12.733 2 10.352 2 7.5a4.5 4.5 0 0 1 8-2.828A4.5 4.5 0 0 1 18 7.5c0 2.852-2.044 5.233-3.885 6.82a22.049 22.049 0 0 1-3.744 2.582l-.019.01-.005.003h-.002a.739.739 0 0 1-.69.001l-.002-.001Z" />
                                    </svg>
                                    Add to favorites
                                </a>
                            </div>
                            <div class="py-1" role="none">
                                <a href="#" class="group flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200" role="menuitem" tabindex="-1" id="menu-item-6">
                                    <!-- Active: "text-gray-500", Not Active: "" -->
                                    <svg class="mr-3 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                    </svg>
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="relative w-full max-w-2xl p-6 lg:max-w-7xl">
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-8">
                        @foreach($shows as $show)
                            <a class="flex flex-col gap-4" href="/show/{{$show->id}}">
                                <div class="justify-center flex w-full">
                                    <div style="background: url('{{ $show->poster_image }}') no-repeat center center / cover; height: 340px; width:100%;" class="shadow-lg rounded-lg"></div>
                                </div>

                                <div class="text-white">
                                    {{ $show->name }}
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="pb-4 pt-8">
                        {{ $shows->links()  }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
