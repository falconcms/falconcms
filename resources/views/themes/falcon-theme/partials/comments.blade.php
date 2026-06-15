<div class="mt-20 pt-10 border-t border-gray-100">
    <h3 class="text-3xl font-black mb-10 tracking-tighter text-gray-900">
        Comments ({{ $post->comments->count() }})
    </h3>

    @if(session('success'))
        <div class="mb-8 p-3 bg-green-50 border border-green-200 text-green-700 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    <!-- Comment List -->
    <div class="space-y-10 mb-16">
        @forelse($post->comments as $comment)
            <div class="flex gap-6">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-primary font-black text-xl">
                    {{ substr($comment->name, 0, 1) }}
                </div>
                <div class="flex-grow">
                    <div class="flex items-center gap-3 mb-2">
                        <h4 class="font-bold text-gray-900 leading-none">{{ $comment->name }}</h4>
                        <span class="text-xs text-gray-400 font-medium leading-none">{{ $comment->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-gray-600 leading-relaxed mb-2">
                        {{ $comment->comment }}
                    </p>
                    <button type="button" onclick="document.getElementById('reply-form-{{ $comment->id }}').classList.toggle('hidden')" class="text-xs font-bold text-primary mb-6 hover:underline flex items-center gap-1">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        Reply
                    </button>

                    <!-- Inline Reply Form -->
                    <form id="reply-form-{{ $comment->id }}" action="{{ route('frontend.comment.store') }}" method="POST" class="hidden mb-8 border-l-2 border-gray-200 pl-4 space-y-3">
                        @csrf
                        <input type="hidden" name="post_id" value="{{ $post->id }}">
                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">

                        @guest
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <input type="text" name="name" required class="w-full border border-gray-300 px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500" placeholder="Your Name *">
                            <input type="email" name="email" required class="w-full border border-gray-300 px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500" placeholder="Email Address *">
                        </div>
                        @endguest

                        <textarea name="comment" rows="3" required class="w-full border border-gray-300 px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500 resize-none" placeholder="Write a reply..."></textarea>

                        <div class="flex items-center gap-3">
                            <button type="submit" class="bg-primary text-white px-6 py-2 text-xs font-bold uppercase tracking-widest hover:opacity-90 transition">
                                Post Reply
                            </button>
                            <button type="button" onclick="document.getElementById('reply-form-{{ $comment->id }}').classList.add('hidden')" class="text-xs text-gray-400 hover:text-gray-600 font-medium uppercase tracking-wide">
                                Cancel
                            </button>
                        </div>
                    </form>

                    @foreach($comment->replies as $reply)
                        <div class="mt-8 pl-8 border-l-2 border-gray-50 flex gap-6">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 font-black text-lg">
                                {{ substr($reply->name, 0, 1) }}
                            </div>
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <h4 class="font-bold text-gray-900 leading-none">{{ $reply->name }}</h4>
                                    <span class="text-xs text-gray-400 font-medium leading-none">{{ $reply->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-gray-600 leading-relaxed mb-2">
                                    {{ $reply->comment }}
                                </p>
                                <button type="button" onclick="document.getElementById('reply-form-{{ $reply->id }}').classList.toggle('hidden')" class="text-xs font-bold text-primary mb-2 hover:underline flex items-center gap-1">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                    Reply
                                </button>

                                <!-- Inline Reply Form for Reply -->
                                <form id="reply-form-{{ $reply->id }}" action="{{ route('frontend.comment.store') }}" method="POST" class="hidden mt-3 mb-4 border-l-2 border-gray-200 pl-4 space-y-3">
                                    @csrf
                                    <input type="hidden" name="post_id" value="{{ $post->id }}">
                                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">

                                    @guest
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <input type="text" name="name" required class="w-full border border-gray-300 px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500" placeholder="Your Name *">
                                        <input type="email" name="email" required class="w-full border border-gray-300 px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500" placeholder="Email Address *">
                                    </div>
                                    @endguest

                                    <textarea name="comment" rows="3" required class="w-full border border-gray-300 px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500 resize-none" placeholder="Reply to {{ $reply->name }}..."></textarea>

                                    <div class="flex items-center gap-3">
                                        <button type="submit" class="bg-primary text-white px-6 py-2 text-xs font-bold uppercase tracking-widest hover:opacity-90 transition">
                                            Post Reply
                                        </button>
                                        <button type="button" onclick="document.getElementById('reply-form-{{ $reply->id }}').classList.add('hidden')" class="text-xs text-gray-400 hover:text-gray-600 font-medium uppercase tracking-wide">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-gray-400 italic">No comments yet. Be the first to share your thoughts!</p>
        @endforelse
    </div>

    <!-- Comment Form -->
    <div>
        <h4 class="text-xl font-bold mb-6 text-gray-900">Leave a Reply</h4>
        <form action="{{ route('frontend.comment.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="post_id" value="{{ $post->id }}">

            @guest
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Your Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full border border-gray-300 px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required class="w-full border border-gray-300 px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500">
                </div>
            </div>
            @else
            <p class="text-sm text-gray-500">Logged in as <span class="font-bold text-gray-900">{{ auth()->user()->name }}</span></p>
            @endguest

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Your Review</label>
                <textarea name="comment" rows="6" required class="w-full border border-gray-300 px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500 resize-none"></textarea>
            </div>

            <button type="submit" class="bg-primary text-white px-8 py-3 text-sm font-bold uppercase tracking-widest hover:opacity-90 transition">
                Submit
            </button>
        </form>
    </div>
</div>
