<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';

interface ReviewVideo {
    id: number;
    youtube_id: string;
    title: string;
    source: 'auto' | 'manual';
    is_hidden: boolean;
}

const props = defineProps<{
    entityId: number;
    videos: ReviewVideo[];
}>();

const baseUrl = `/admin/entities/${props.entityId}/review-videos`;

const addForm = useForm({ url: '', title: '' });
const editForm = useForm({ url: '', title: '' });
const editingId = ref<number | null>(null);

function addVideo(): void {
    addForm.post(baseUrl, {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
}

function startEdit(video: ReviewVideo): void {
    editingId.value = video.id;
    editForm.clearErrors();
    editForm.title = video.title;
    editForm.url =
        video.source === 'manual'
            ? `https://www.youtube.com/watch?v=${video.youtube_id}`
            : '';
}

function saveEdit(video: ReviewVideo): void {
    editForm.put(`${baseUrl}/${video.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function removeVideo(video: ReviewVideo): void {
    router.delete(`${baseUrl}/${video.id}`, { preserveScroll: true });
}

function restoreVideo(video: ReviewVideo): void {
    router.post(`${baseUrl}/${video.id}/restore`, {}, { preserveScroll: true });
}
</script>

<template>
    <div
        class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
    >
        <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
            Review Videos
        </h2>
        <p class="mt-1 text-xs text-neutral-500">
            Shown on the public product page, manual links first. The daily job
            keeps adding YouTube reviews it finds; a hidden one is never added
            back.
        </p>

        <!-- Add a link -->
        <form
            class="mt-4 grid gap-2 sm:grid-cols-[2fr_2fr_auto]"
            @submit.prevent="addVideo"
        >
            <div>
                <label
                    for="review-video-url"
                    class="block text-xs font-medium text-neutral-700 dark:text-neutral-300"
                    >YouTube link</label
                >
                <input
                    id="review-video-url"
                    v-model="addForm.url"
                    type="text"
                    placeholder="https://www.youtube.com/watch?v=..."
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                />
                <InputError :message="addForm.errors.url" />
            </div>
            <div>
                <label
                    for="review-video-title"
                    class="block text-xs font-medium text-neutral-700 dark:text-neutral-300"
                    >Title (optional, taken from YouTube if empty)</label
                >
                <input
                    id="review-video-title"
                    v-model="addForm.title"
                    type="text"
                    class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                />
                <InputError :message="addForm.errors.title" />
            </div>
            <button
                type="submit"
                :disabled="addForm.processing || addForm.url.trim() === ''"
                class="self-end rounded-lg bg-indigo-600 px-4 py-2 text-xs font-medium whitespace-nowrap text-white hover:bg-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50"
            >
                {{ addForm.processing ? 'Checking...' : 'Add video' }}
            </button>
        </form>

        <!-- Videos -->
        <p
            v-if="videos.length === 0"
            class="mt-6 rounded-lg border border-dashed border-neutral-300 p-4 text-center text-xs text-neutral-500 dark:border-neutral-700"
        >
            No review videos yet. The daily job searches YouTube for products
            with none, or paste a link above.
        </p>

        <ul v-else class="mt-6 space-y-2">
            <li
                v-for="video in videos"
                :key="video.id"
                class="rounded-lg border border-neutral-100 bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-800/50"
                :class="{ 'opacity-60': video.is_hidden }"
            >
                <div class="flex items-start gap-3">
                    <img
                        :src="`https://i.ytimg.com/vi/${video.youtube_id}/default.jpg`"
                        alt=""
                        width="120"
                        height="90"
                        loading="lazy"
                        class="h-[68px] w-24 shrink-0 rounded object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <div
                            class="text-xs font-medium break-words text-neutral-800 dark:text-neutral-200"
                        >
                            {{ video.title }}
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px]">
                            <span
                                class="rounded bg-neutral-200 px-1.5 py-0.5 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200"
                            >
                                {{ video.source === 'manual' ? 'Manual' : 'Automatic' }}
                            </span>
                            <span
                                v-if="video.is_hidden"
                                class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-900"
                            >
                                Hidden
                            </span>
                            <a
                                :href="`https://www.youtube.com/watch?v=${video.youtube_id}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-indigo-600 hover:underline dark:text-indigo-400"
                                >Open on YouTube</a
                            >
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-3 text-xs">
                        <button
                            type="button"
                            class="text-indigo-600 hover:underline focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:outline-none dark:text-indigo-400"
                            @click="editingId === video.id ? (editingId = null) : startEdit(video)"
                        >
                            {{ editingId === video.id ? 'Cancel' : 'Edit' }}
                        </button>
                        <button
                            v-if="video.is_hidden"
                            type="button"
                            class="text-emerald-700 hover:underline focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:outline-none dark:text-emerald-400"
                            @click="restoreVideo(video)"
                        >
                            Show again
                        </button>
                        <button
                            v-else
                            type="button"
                            class="text-rose-600 hover:underline focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:outline-none"
                            @click="removeVideo(video)"
                        >
                            {{ video.source === 'manual' ? 'Delete' : 'Hide' }}
                        </button>
                    </div>
                </div>

                <!-- Inline edit -->
                <form
                    v-if="editingId === video.id"
                    class="mt-3 space-y-2 border-t border-neutral-200 pt-3 dark:border-neutral-700"
                    @submit.prevent="saveEdit(video)"
                >
                    <div>
                        <label
                            :for="`edit-title-${video.id}`"
                            class="block text-xs font-medium text-neutral-700 dark:text-neutral-300"
                            >Title</label
                        >
                        <input
                            :id="`edit-title-${video.id}`"
                            v-model="editForm.title"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                        />
                        <InputError :message="editForm.errors.title" />
                    </div>
                    <div v-if="video.source === 'manual'">
                        <label
                            :for="`edit-url-${video.id}`"
                            class="block text-xs font-medium text-neutral-700 dark:text-neutral-300"
                            >YouTube link</label
                        >
                        <input
                            :id="`edit-url-${video.id}`"
                            v-model="editForm.url"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                        />
                        <InputError :message="editForm.errors.url" />
                    </div>
                    <p v-else class="text-[11px] text-neutral-500">
                        The link of an automatic video cannot be changed. Hide it
                        and add the right link instead.
                    </p>
                    <button
                        type="submit"
                        :disabled="editForm.processing"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50"
                    >
                        {{ editForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                </form>
            </li>
        </ul>
    </div>
</template>
