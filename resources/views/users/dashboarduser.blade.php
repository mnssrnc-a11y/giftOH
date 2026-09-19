@extends('layouts.dashboard')

@section('title', 'Dashboard - Gift of Hope')

@section('content')
<div class="hope-preview" style="margin:18px 24px 0">Community feed preview · Posts, photos, reactions, and comments are examples or last for this visit only.</div>
@if(session('status'))<div class="hope-preview" role="status" style="margin:18px 24px">{{ session('status') }}</div>@endif

{{-- ============================================================ --}}
{{-- CUSTOM STYLES                                                 --}}
{{-- ============================================================ --}}
<style>
    /* ---------- base resets & scrollbar ---------- */
    .feed-scroll::-webkit-scrollbar { width: 6px; }
    .feed-scroll::-webkit-scrollbar-track { background: transparent; }
    .feed-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    .feed-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* ---------- animations ---------- */
    @keyframes slideDown { from { opacity: 0; transform: translateY(-18px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes slideUp   { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes fadeIn    { from { opacity: 0; } to { opacity: 1; } }
    @keyframes popIn     { 0% { transform: scale(0); } 70% { transform: scale(1.15); } 100% { transform: scale(1); } }
    @keyframes bounce    { 0%,100% { transform: scale(1); } 50% { transform: scale(1.35); } }
    @keyframes shimmer   { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }

    .anim-slide-down { animation: slideDown .35s ease-out; }
    .anim-slide-up   { animation: slideUp .3s ease-out; }
    .anim-fade-in    { animation: fadeIn .25s ease-out; }
    .anim-pop-in     { animation: popIn .3s ease-out; }

    /* ---------- reaction picker ---------- */
    .reaction-picker {
        position: absolute; bottom: 100%; left: 0;
        display: flex; gap: 4px; padding: 8px 12px;
        background: rgba(255,255,255,.92);
        backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
        border-radius: 28px; box-shadow: 0 4px 24px rgba(0,0,0,.14);
        opacity: 0; visibility: hidden; transform: translateY(6px) scale(.92);
        transition: all .22s cubic-bezier(.4,0,.2,1); z-index: 50;
        pointer-events: none;
    }
    .reaction-btn-wrap:hover .reaction-picker,
    .reaction-picker:hover {
        opacity: 1; visibility: visible; transform: translateY(-4px) scale(1);
        pointer-events: auto;
    }
    .reaction-picker .emoji {
        font-size: 1.55rem; cursor: pointer;
        transition: transform .18s cubic-bezier(.4,0,.2,1);
        user-select: none;
    }
    .reaction-picker .emoji:hover { transform: scale(1.45) translateY(-4px); }

    /* ---------- skeleton loader ---------- */
    .skeleton {
        background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
        background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px;
    }

    /* ---------- misc ---------- */
    .post-image { max-height: 420px; object-fit: cover; }
    .privacy-dropdown { position: relative; }
    .privacy-dropdown .dropdown-menu {
        position: absolute; top: 100%; left: 0; z-index: 40;
        background: #fff; border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,.12);
        min-width: 180px; overflow: hidden;
        display: none;
    }
    .privacy-dropdown.open .dropdown-menu { display: block; animation: fadeIn .15s ease-out; }

    /* ---------- responsive ---------- */
    @media (max-width: 1023px) {
        .fb-left-sidebar { display: none !important; }
    }

</style>

{{-- ============================================================ --}}
{{-- FEED LAYOUT                                                   --}}
{{-- ============================================================ --}}
<div class="flex h-full" id="fb-app">

    {{-- ======== LEFT SIDEBAR ======== --}}
    <aside class="fb-left-sidebar w-[260px] shrink-0 p-4 overflow-y-auto feed-scroll hidden lg:block">
        {{-- Profile Card --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <div class="h-20 bg-gradient-to-r from-blue-500 to-blue-600"></div>
            <div class="px-4 pb-4 -mt-8">
                <div class="w-16 h-16 rounded-full border-4 border-white bg-blue-100 flex items-center justify-center text-2xl font-bold text-blue-600 shadow-md">
                    {{ substr(auth()->user()->fname ?? 'U', 0, 1) }}
                </div>
                <h3 class="font-bold text-gray-900 mt-2 text-base">{{ auth()->user()->fname ?? 'User' }} {{ auth()->user()->lname ?? '' }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">@<span>{{ strtolower(auth()->user()->fname ?? 'user') }}</span> · <span class="text-blue-500">{{ auth()->user()->role ?? 'Member' }}</span></p>
            </div>
        </div>

        {{-- Shortcuts --}}
        <nav class="space-y-1">

            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 hover:bg-green-50 transition-colors group">
                <span class="w-8 h-8 rounded-lg bg-green-100 text-green-600 flex items-center justify-center text-sm group-hover:bg-green-200 transition-colors">👫</span>
                <span class="text-sm font-medium">Groups</span>
            </a>

            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 hover:bg-orange-50 transition-colors group">
                <span class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center text-sm group-hover:bg-orange-200 transition-colors">📅</span>
                <span class="text-sm font-medium">Events</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 hover:bg-red-50 transition-colors group">
                <span class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center text-sm group-hover:bg-red-200 transition-colors">❤️</span>
                <span class="text-sm font-medium">Fundraisers</span>
            </a>



        </nav>

        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-[10px] text-gray-400 px-3">Gift of Hope © 2026 · Privacy · Terms</p>
        </div>
    </aside>

    {{-- ======== CENTER FEED ======== --}}
    <main class="flex-1 overflow-y-auto feed-scroll py-4 px-2 sm:px-4 lg:px-6" id="feed-area">
        <div class="max-w-[590px] mx-auto">

            {{-- Post Composer --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 anim-fade-in" id="post-composer">
                <div class="p-3 flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm shrink-0 shadow-sm">
                        {{ substr(auth()->user()->fname ?? 'U', 0, 1) }}
                    </div>
                    <div class="flex-1">
                        <div
                            id="post-input"
                            contenteditable="true"
                            class="w-full bg-gray-100 rounded-2xl px-4 py-2.5 text-sm text-gray-700 outline-none focus:bg-gray-50 focus:ring-2 focus:ring-blue-200 transition-all min-h-[40px] max-h-[200px] overflow-y-auto empty:before:content-['What\'s_on_your_mind,_{{ auth()->user()->fname ?? "User" }}?'] empty:before:text-gray-400"
                            data-placeholder="What's on your mind, {{ auth()->user()->fname ?? 'User' }}?"
                        ></div>
                    </div>
                </div>
                <div class="border-t border-gray-100 px-3 py-2 flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <input type="file" id="feed-photo" accept=".jpg,.jpeg,.png" hidden>
                        <button type="button" onclick="document.getElementById('feed-photo').click()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-green-50 hover:text-green-600 transition-colors" title="Add photo" aria-label="Add photo">
                            <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            <span class="hidden sm:inline">Photo</span>
                        </button>
                        <button class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-yellow-50 hover:text-yellow-600 transition-colors" title="Feeling/Activity">
                            <span class="text-lg">😊</span>
                            <span class="hidden sm:inline">Feeling</span>
                        </button>
                        <button class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-red-50 hover:text-red-600 transition-colors" title="Check In">
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                            <span class="hidden sm:inline">Check In</span>
                        </button>
                    </div>

                    {{-- Privacy + Post --}}
                    <div class="flex items-center gap-2">
                        <div class="privacy-dropdown" id="privacy-dropdown">
                            <button onclick="togglePrivacy()" class="flex items-center gap-1 px-2 py-1 rounded-md text-xs text-gray-500 hover:bg-gray-100 transition-colors">
                                <span id="privacy-icon">🌐</span>
                                <span id="privacy-label" class="hidden sm:inline">Public</span>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                            </button>
                            <div class="dropdown-menu">
                                <button onclick="setPrivacy('🌐','Public')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 flex items-center gap-2"><span>🌐</span> Public</button>
                                <button onclick="setPrivacy('👥','Friends')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 flex items-center gap-2"><span>👥</span> Friends</button>
                                <button onclick="setPrivacy('🔒','Only Me')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 flex items-center gap-2"><span>🔒</span> Only Me</button>
                            </div>
                        </div>
                        <button id="post-btn" onclick="createPost()" class="px-4 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                            Post
                        </button>
                    </div>
                </div>
            </div>

            {{-- Feed Container --}}
            <div id="feed-photo-status" role="status" class="text-sm text-blue-700 mb-3"></div>
            <div id="feed-photo-preview" hidden class="mb-3"><img id="feed-selected-image" alt="Selected post photo" class="w-full rounded-xl" style="max-height:220px;object-fit:contain"><button type="button" onclick="clearFeedPhoto()" class="hope-text-button">Remove photo</button></div>
            <div id="feed-container">
                {{-- Posts rendered by JS --}}
            </div>
        </div>
    </main>

</div>

{{-- ============================================================ --}}
{{-- JAVASCRIPT                                                    --}}
{{-- ============================================================ --}}
<script>
document.addEventListener('DOMContentLoaded', () => {

    // ─── MOCK DATA ───────────────────────────────────────────────
    const currentUser = {
        id: 0,
        name: @json(auth()->user()->name ?? 'User'),
        initial: @json(mb_substr(auth()->user()->fname ?? 'U', 0, 1)),
        avatar: null
    };

    const REACTIONS = [
        { emoji: '👍', label: 'Like',  color: '#1877F2' },
        { emoji: '❤️', label: 'Love',  color: '#E0245E' },
        { emoji: '😂', label: 'Haha',  color: '#F7B928' },
        { emoji: '😮', label: 'Wow',   color: '#F7B928' },
        { emoji: '😢', label: 'Sad',   color: '#F7B928' },
        { emoji: '😡', label: 'Angry', color: '#E9710F' },
    ];

    let posts = [
        {
            id: 1,
            author: { name: 'Maria Santos', initial: 'M', color: 'from-pink-500 to-rose-500' },
            text: 'Just finished organizing our community outreach program! 🎉 So grateful for all the volunteers who came out today. Together we made a real difference in the lives of 50+ families. #GiftOfHope #Community',
            image: null,
            privacy: '🌐',
            time: '2 hours ago',
            reactions: { '👍': 24, '❤️': 18, '😂': 3 },
            myReaction: null,
            comments: [
                { id: 101, author: { name: 'Juan Dela Cruz', initial: 'J', color: 'from-blue-500 to-cyan-500' }, text: 'This is incredible! So proud of everyone! 👏', time: '1h', likes: 5, liked: false, replies: [
                    { id: 102, author: { name: 'Maria Santos', initial: 'M', color: 'from-pink-500 to-rose-500' }, text: 'Thank you Juan! Couldn\'t have done it without you! ❤️', time: '45m', likes: 2, liked: false },
                ]},
                { id: 103, author: { name: 'Ana Reyes', initial: 'A', color: 'from-violet-500 to-purple-500' }, text: 'Count me in for the next one! 🙋‍♀️', time: '30m', likes: 3, liked: false, replies: [] },
            ],
            showComments: false,
        },
        {
            id: 2,
            author: { name: 'Juan Dela Cruz', initial: 'J', color: 'from-blue-500 to-cyan-500' },
            text: 'The new donation boxes are now installed at 5 different locations across the city! 📦 Thank you to our partners for making this possible. Every peso counts!',
            image: 'https://images.unsplash.com/photo-1532629345422-7515f3d16bb6?w=600&h=350&fit=crop',
            privacy: '👥',
            time: '4 hours ago',
            reactions: { '👍': 42, '❤️': 31, '😮': 7 },
            myReaction: null,
            comments: [
                { id: 201, author: { name: 'Pedro Garcia', initial: 'P', color: 'from-emerald-500 to-green-500' }, text: 'Great work! Where are the locations? 🗺️', time: '3h', likes: 1, liked: false, replies: [
                    { id: 202, author: { name: 'Juan Dela Cruz', initial: 'J', color: 'from-blue-500 to-cyan-500' }, text: 'I\'ll post the full list soon! Stay tuned 📋', time: '2h', likes: 0, liked: false },
                ]},
            ],
            showComments: false,
        },
        {
            id: 3,
            author: { name: 'Ana Reyes', initial: 'A', color: 'from-violet-500 to-purple-500' },
            text: '💡 Reminder: Our monthly charity meeting is this Saturday at 2PM. We\'ll be discussing the Q3 goals and new partnership opportunities. See you all there!\n\n📍 Community Center, Room 201\n🕐 2:00 PM - 4:00 PM',
            image: null,
            privacy: '🌐',
            time: '6 hours ago',
            reactions: { '👍': 15, '❤️': 8 },
            myReaction: null,
            comments: [
                { id: 301, author: { name: 'Sofia Mendoza', initial: 'S', color: 'from-amber-500 to-orange-500' }, text: 'I\'ll be there! 🙌', time: '5h', likes: 2, liked: false, replies: [] },
            ],
            showComments: false,
        },
        {
            id: 4,
            author: { name: 'Pedro Garcia', initial: 'P', color: 'from-emerald-500 to-green-500' },
            text: 'Blessed to be part of this wonderful organization. Today we distributed school supplies to 200 children in need. Their smiles made it all worth it! 📚✨',
            image: 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=600&h=350&fit=crop',
            privacy: '🌐',
            time: 'Yesterday',
            reactions: { '👍': 67, '❤️': 45, '😢': 12, '😮': 5 },
            myReaction: null,
            comments: [
                { id: 401, author: { name: 'Carlos Aquino', initial: 'C', color: 'from-teal-500 to-cyan-500' }, text: 'This brought tears to my eyes 😭❤️', time: '20h', likes: 8, liked: false, replies: [] },
                { id: 402, author: { name: 'Isabella Torres', initial: 'I', color: 'from-rose-500 to-pink-500' }, text: 'Heroes don\'t always wear capes! You guys are amazing!', time: '18h', likes: 12, liked: false, replies: [
                    { id: 403, author: { name: 'Pedro Garcia', initial: 'P', color: 'from-emerald-500 to-green-500' }, text: 'We\'re all heroes when we work together! 💪', time: '17h', likes: 6, liked: false },
                ]},
            ],
            showComments: false,
        },
    ];

    let nextPostId = 100;
    let nextCommentId = 1000;
    function renderPosts() {
        const container = document.getElementById('feed-container');
        if (!container) return;
        container.innerHTML = posts.map(p => renderPostCard(p)).join('');
    }

    function renderPostCard(post) {
        // Reaction summary
        const totalReactions = Object.values(post.reactions).reduce((a, b) => a + b, 0);
        const topEmojis = Object.entries(post.reactions).sort((a,b) => b[1] - a[1]).slice(0, 3).map(e => e[0]).join('');
        const commentCount = countComments(post.comments);

        // My reaction display
        const myR = post.myReaction ? REACTIONS.find(r => r.emoji === post.myReaction) : null;
        const likeBtnText = myR ? `<span style="color:${myR.color}">${myR.emoji} ${myR.label}</span>` : '👍 Like';

        // Format text with line breaks
        const formattedText = escapeFeedText(post.text).replace(/\n/g, '<br>');

        let html = `
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 anim-slide-down" id="post-${post.id}">
            {{-- Header --}}
            <div class="px-4 pt-3 pb-2 flex items-start justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br ${post.author.color} flex items-center justify-center text-white font-bold text-sm shadow-sm">${post.author.initial}</div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm font-bold text-gray-900 hover:underline cursor-pointer">${escapeFeedText(post.author.name)}</span>
                        </div>
                        <div class="flex items-center gap-1 text-xs text-gray-400">
                            <span>${post.time}</span>
                            <span>·</span>
                            <span>${post.privacy}</span>
                        </div>
                    </div>
                </div>
                <button class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                </button>
            </div>

            {{-- Content --}}
            <div class="px-4 pb-2">
                <p class="text-sm text-gray-800 leading-relaxed">${formattedText}</p>
            </div>`;

        // Image
        if (post.image) {
            html += `
            <div class="mt-1">
                <img src="${post.image}" alt="Post image" class="w-full post-image" loading="lazy" />
            </div>`;
        }

        // Reaction + comment count bar
        html += `
            <div class="px-4 py-2 flex items-center justify-between text-xs text-gray-500">
                <div class="flex items-center gap-1 cursor-pointer hover:underline">
                    ${totalReactions > 0 ? `<span>${topEmojis}</span> <span>${totalReactions}</span>` : ''}
                </div>
                <div class="flex items-center gap-3">
                    ${commentCount > 0 ? `<button onclick="toggleComments(${post.id})" class="hover:underline">${commentCount} comment${commentCount > 1 ? 's' : ''}</button>` : ''}
                    <span class="hover:underline cursor-pointer">0 shares</span>
                </div>
            </div>`;

        // Action buttons
        html += `
            <div class="border-t border-gray-100 mx-4"></div>
            <div class="px-2 py-1 flex items-center">
                <div class="flex-1 relative reaction-btn-wrap">
                    <div class="reaction-picker" id="picker-${post.id}">
                        ${REACTIONS.map(r => `<span class="emoji" onclick="react(${post.id},'${r.emoji}')" title="${r.label}">${r.emoji}</span>`).join('')}
                    </div>
                    <button onclick="react(${post.id},'👍')" class="w-full py-2 rounded-lg text-sm font-semibold hover:bg-gray-100 transition-colors ${post.myReaction ? '' : 'text-gray-600'}">
                        ${likeBtnText}
                    </button>
                </div>
                <button onclick="toggleComments(${post.id})" class="flex-1 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">
                    💬 Comment
                </button>
                <button class="flex-1 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">
                    ↗️ Share
                </button>
            </div>`;

        // Comments section
        html += `<div id="comments-${post.id}" class="${post.showComments ? '' : 'hidden'} border-t border-gray-100">`;
        html += renderComments(post.id, post.comments);
        html += `
            <div class="px-4 py-3 flex items-start gap-2">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm">${currentUser.initial}</div>
                <div class="flex-1 relative">
                    <input type="text" id="comment-input-${post.id}" placeholder="Write a comment..." onkeydown="if(event.key==='Enter')addComment(${post.id})" class="w-full bg-gray-100 rounded-2xl px-4 py-2 text-sm outline-none focus:bg-gray-50 focus:ring-2 focus:ring-blue-200 transition-all pr-10" />
                    <button onclick="addComment(${post.id})" class="absolute right-2 top-1/2 -translate-y-1/2 text-blue-500 hover:text-blue-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                    </button>
                </div>
            </div>
        </div>`;

        html += `</div>`;
        return html;
    }

    function renderComments(postId, comments, isReply = false) {
        let html = '';
        comments.forEach(c => {
            const pl = isReply ? 'pl-10' : 'pl-4';
            html += `
            <div class="pr-4 ${pl} py-2 anim-fade-in" id="comment-${c.id}">
                <div class="flex items-start gap-2">
                    <div class="w-${isReply ? '6' : '8'} h-${isReply ? '6' : '8'} rounded-full bg-gradient-to-br ${c.author.color} flex items-center justify-center text-white text-[${isReply ? '9px' : '10px'}] font-bold shrink-0">${c.author.initial}</div>
                    <div class="flex-1 min-w-0">
                        <div class="bg-gray-100 rounded-2xl px-3 py-2 inline-block max-w-full">
                            <span class="text-xs font-bold text-gray-900">${escapeFeedText(c.author.name)}</span>
                            <p class="text-sm text-gray-700 mt-0.5">${escapeFeedText(c.text)}</p>
                        </div>
                        <div class="flex items-center gap-3 mt-0.5 ml-2">
                            <button onclick="likeComment(${postId},${c.id})" class="text-[11px] font-semibold ${c.liked ? 'text-blue-600' : 'text-gray-400'} hover:underline">Like${c.likes > 0 ? ` · ${c.likes}` : ''}</button>
                            ${!isReply ? `<button onclick="showReplyInput(${postId},${c.id})" class="text-[11px] font-semibold text-gray-400 hover:underline">Reply</button>` : ''}
                            <span class="text-[11px] text-gray-300">${c.time}</span>
                        </div>
                        <div id="reply-input-${c.id}" class="hidden mt-2 flex items-start gap-2">
                            <div class="w-6 h-6 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-[9px] font-bold shrink-0">${currentUser.initial}</div>
                            <input type="text" placeholder="Write a reply..." onkeydown="if(event.key==='Enter')addReply(${postId},${c.id})" class="flex-1 bg-gray-100 rounded-2xl px-3 py-1.5 text-xs outline-none focus:bg-gray-50 focus:ring-2 focus:ring-blue-200 transition-all" />
                        </div>`;

            // Render replies
            if (c.replies && c.replies.length > 0) {
                html += renderComments(postId, c.replies, true);
            }
            html += `</div></div></div>`;
        });
        return html;
    }

    function countComments(comments) {
        let count = comments.length;
        comments.forEach(c => { if (c.replies) count += c.replies.length; });
        return count;
    }

    // ─── ACTIONS: POST ───────────────────────────────────────────
    function escapeFeedText(value) {
        const element = document.createElement('span');
        element.textContent = value;
        return element.innerHTML;
    }
    let selectedFeedPhoto = null;
    window.clearFeedPhoto = () => {
        selectedFeedPhoto = null;
        document.getElementById('feed-photo').value = '';
        document.getElementById('feed-photo-preview').hidden = true;
        document.getElementById('post-btn').disabled = document.getElementById('post-input').innerText.trim() === '';
    };
    document.getElementById('feed-photo').addEventListener('change', event => {
        const file = event.target.files[0];
        const output = document.getElementById('feed-photo-status');
        clearFeedPhoto();
        if (!file) return;
        if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) { output.textContent = 'Choose a JPG or PNG image up to 5 MB.'; return; }
        const reader = new FileReader();
        reader.onload = () => {
            const preview = document.getElementById('feed-selected-image');
            preview.onload = () => { selectedFeedPhoto = reader.result; document.getElementById('feed-photo-preview').hidden = false; document.getElementById('post-btn').disabled = false; output.textContent = file.name; };
            preview.onerror = () => { clearFeedPhoto(); output.textContent = 'This image could not be opened.'; };
            preview.src = reader.result;
        };
        reader.onerror = () => { output.textContent = 'This file could not be read. Please try again.'; };
        reader.readAsDataURL(file);
    });
    window.createPost = () => {
        const input = document.getElementById('post-input');
        const text = input.innerText.trim();
        if (!text && !selectedFeedPhoto) return;

        const privacyIcon = document.getElementById('privacy-icon').innerText;

        const newPost = {
            id: nextPostId++,
            author: { name: currentUser.name, initial: currentUser.initial, color: 'from-blue-500 to-blue-600' },
            text: text,
            image: selectedFeedPhoto,
            privacy: privacyIcon,
            time: 'Just now',
            reactions: {},
            myReaction: null,
            comments: [],
            showComments: false,
        };

        posts.unshift(newPost);
        clearFeedPhoto();
        document.getElementById('feed-photo-status').textContent = 'Successfully posted in this preview. The post will reset when you leave this page.';
        input.innerHTML = '';
        document.getElementById('post-btn').disabled = true;
        renderPosts();

        // Scroll to top
        document.getElementById('feed-area').scrollTo({ top: 0, behavior: 'smooth' });
    };

    // Post button enable/disable
    const postInput = document.getElementById('post-input');
    if (postInput) {
        postInput.addEventListener('input', () => {
            document.getElementById('post-btn').disabled = postInput.innerText.trim() === '' && !selectedFeedPhoto;
        });
    }

    // ─── ACTIONS: PRIVACY ────────────────────────────────────────
    window.togglePrivacy = () => {
        document.getElementById('privacy-dropdown').classList.toggle('open');
    };
    window.setPrivacy = (icon, label) => {
        document.getElementById('privacy-icon').innerText = icon;
        document.getElementById('privacy-label').innerText = label;
        document.getElementById('privacy-dropdown').classList.remove('open');
    };
    document.addEventListener('click', (e) => {
        const dd = document.getElementById('privacy-dropdown');
        if (dd && !dd.contains(e.target)) dd.classList.remove('open');
    });

    // ─── ACTIONS: REACTIONS ──────────────────────────────────────
    window.react = (postId, emoji) => {
        const post = posts.find(p => p.id === postId);
        if (!post) return;

        if (post.myReaction === emoji) {
            // Remove reaction
            post.reactions[emoji] = Math.max(0, (post.reactions[emoji] || 1) - 1);
            if (post.reactions[emoji] === 0) delete post.reactions[emoji];
            post.myReaction = null;
        } else {
            // Remove old reaction if any
            if (post.myReaction) {
                const old = post.myReaction;
                post.reactions[old] = Math.max(0, (post.reactions[old] || 1) - 1);
                if (post.reactions[old] === 0) delete post.reactions[old];
            }
            // Add new reaction
            post.reactions[emoji] = (post.reactions[emoji] || 0) + 1;
            post.myReaction = emoji;
        }
        renderPosts();
    };

    // ─── ACTIONS: COMMENTS ───────────────────────────────────────
    window.toggleComments = (postId) => {
        const post = posts.find(p => p.id === postId);
        if (!post) return;
        post.showComments = !post.showComments;
        renderPosts();
        if (post.showComments) {
            setTimeout(() => {
                const input = document.getElementById(`comment-input-${postId}`);
                if (input) input.focus();
            }, 100);
        }
    };

    window.addComment = (postId) => {
        const input = document.getElementById(`comment-input-${postId}`);
        if (!input || !input.value.trim()) return;

        const post = posts.find(p => p.id === postId);
        if (!post) return;

        post.comments.push({
            id: nextCommentId++,
            author: { name: currentUser.name, initial: currentUser.initial, color: 'from-blue-500 to-blue-600' },
            text: input.value.trim(),
            time: 'Just now',
            likes: 0,
            liked: false,
            replies: [],
        });
        input.value = '';
        renderPosts();
    };

    window.showReplyInput = (postId, commentId) => {
        const el = document.getElementById(`reply-input-${commentId}`);
        if (el) {
            el.classList.toggle('hidden');
            const inp = el.querySelector('input');
            if (inp) inp.focus();
        }
    };

    window.addReply = (postId, commentId) => {
        const el = document.getElementById(`reply-input-${commentId}`);
        if (!el) return;
        const input = el.querySelector('input');
        if (!input || !input.value.trim()) return;

        const post = posts.find(p => p.id === postId);
        if (!post) return;

        const comment = findComment(post.comments, commentId);
        if (!comment) return;

        if (!comment.replies) comment.replies = [];
        comment.replies.push({
            id: nextCommentId++,
            author: { name: currentUser.name, initial: currentUser.initial, color: 'from-blue-500 to-blue-600' },
            text: input.value.trim(),
            time: 'Just now',
            likes: 0,
            liked: false,
        });
        renderPosts();
    };

    window.likeComment = (postId, commentId) => {
        const post = posts.find(p => p.id === postId);
        if (!post) return;
        const comment = findComment(post.comments, commentId);
        if (!comment) return;
        comment.liked = !comment.liked;
        comment.likes += comment.liked ? 1 : -1;
        renderPosts();
    };

    function findComment(comments, id) {
        for (const c of comments) {
            if (c.id === id) return c;
            if (c.replies) {
                const found = findComment(c.replies, id);
                if (found) return found;
            }
        }
        return null;
    }

    renderPosts();
});
</script>
@endsection
