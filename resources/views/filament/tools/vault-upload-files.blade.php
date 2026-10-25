<ul class="divide-y divide-gray-100 text-sm dark:divide-white/10">
    @foreach ($upload->files as $file)
        <li class="flex items-center justify-between gap-4 py-2">
            <span class="font-mono break-all">{{ $file['path'] }}</span>
            <span class="shrink-0 text-gray-500 dark:text-gray-400">
                @if ($file['existed'] === false)
                    nový ·
                @elseif ($file['existed'] === true)
                    změněný ·
                @endif
                {{ \Illuminate\Support\Number::fileSize($file['size']) }}
            </span>
        </li>
    @endforeach
</ul>
