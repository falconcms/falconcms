@php $paginator = $paginator ?? $paginator; @endphp

@if($paginator->total() > 0)
    {{-- wp-pagination: wraps and stays aligned on small screens (see admin layout CSS). --}}
    <div class="wp-pagination flex flex-wrap items-center gap-x-2 gap-y-2 text-[13px] text-[#2c3338] max-[782px]:w-full">
        <span class="whitespace-nowrap">{{ $paginator->total() }} items</span>

        @if($paginator->hasPages())
            <div class="flex items-center flex-wrap gap-[3px]">
                @if ($paginator->onFirstPage())
                    <span class="px-2 py-[2px] border border-[#dcdcde] text-[#a7aaad] bg-[#f6f7f7] rounded-[2px] cursor-default text-[16px] leading-normal">&laquo;</span>
                    <span class="px-2 py-[2px] border border-[#dcdcde] text-[#a7aaad] bg-[#f6f7f7] rounded-[2px] cursor-default text-[16px] leading-normal">&lsaquo;</span>
                @else
                    <a href="{{ $paginator->url(1) }}" class="px-2 py-[2px] border border-[#c3c4c7] text-[#2271b1] bg-[#f6f7f7] hover:bg-white rounded-[2px] text-[16px] leading-normal">&laquo;</a>
                    <a href="{{ $paginator->previousPageUrl() }}" class="px-2 py-[2px] border border-[#c3c4c7] text-[#2271b1] bg-[#f6f7f7] hover:bg-white rounded-[2px] text-[16px] leading-normal">&lsaquo;</a>
                @endif

                <span class="inline-flex items-center gap-1 whitespace-nowrap mx-1">
                    <input type="text" class="wp-input w-9 h-[28px] text-center p-0 text-[13px]" value="{{ $paginator->currentPage() }}"
                           onkeypress="if(event.keyCode==13){ let url = '{{ $paginator->url(1) }}'; window.location.href=url.includes('?') ? url.replace('page=1','page='+this.value) : url + '?page='+this.value; return false; }">
                    <span class="text-[#2c3338]">of {{ $paginator->lastPage() }}</span>
                </span>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="px-2 py-[2px] border border-[#c3c4c7] text-[#2271b1] bg-[#f6f7f7] hover:bg-white rounded-[2px] text-[16px] leading-normal">&rsaquo;</a>
                    <a href="{{ $paginator->url($paginator->lastPage()) }}" class="px-2 py-[2px] border border-[#c3c4c7] text-[#2271b1] bg-[#f6f7f7] hover:bg-white rounded-[2px] text-[16px] leading-normal">&raquo;</a>
                @else
                    <span class="px-2 py-[2px] border border-[#dcdcde] text-[#a7aaad] bg-[#f6f7f7] rounded-[2px] cursor-default text-[16px] leading-normal">&rsaquo;</span>
                    <span class="px-2 py-[2px] border border-[#dcdcde] text-[#a7aaad] bg-[#f6f7f7] rounded-[2px] cursor-default text-[16px] leading-normal">&raquo;</span>
                @endif
            </div>
        @endif
    </div>
@endif
