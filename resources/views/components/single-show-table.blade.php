@props(['header', 'description', 'data'])
<div class="px-4 lg:px-4 w-full">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $header }}</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $description }}</p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <button type="button" class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white dark:text-gray-950 shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Add user</button>
        </div>
    </div>
    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-2">
                @foreach($data as $item)
                <div class="overflow-hidden shadow ring-1 ring-black/5 sm:rounded-lg">
                    <div class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                        <div class="bg-gray-50 dark:bg-gray-700 cursor-pointer" onclick="() => {
                            const element = document.getElementById('{{ Str::slug($item->name) }}');
                            element.classList.toggle('hidden');
                        }">
                            <div class="flex justify-between">
                                <div scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-6">Name</div>
                                <div scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-6">File Present</div>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-200 bg-white dark:bg-gray-950 dark:divide-gray-700 flex flex-col " id="{{ \Illuminate\Support\Str::slug($item->name) }}">
                        @foreach($item->episodes as $episode)
                            <div class="flex justify-between">
                                <div class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-gray-200">{{ $episode->name }}</div>
                            </div>

                            <div class="flex flex-col mx-8">
                                @foreach(($episode->media ?? []) as $file)
                                    <div>{{ $file->name }}</div>
                                @endforeach
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

