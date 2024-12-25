<div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">


    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-8">
        @foreach($shows as $show)
            <a class="flex flex-col gap-4" href="/show/{{$show->id}}">
                <div class="justify-center flex w-full">
                    <div style="background: url('{{ $show->poster_image }}') no-repeat center center / cover; height: 340px; width:100%;" class="shadow-lg rounded-lg"></div>
                </div>

                <div class="text-white">
                    {{ $show->name }} ({{ $show->episodes_count }}/{{$show->episodes}})
                </div>
            </a>
        @endforeach
    </div>

    {{ $shows->links()  }}
</div>
