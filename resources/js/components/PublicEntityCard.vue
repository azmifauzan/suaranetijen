<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, MessageCircle } from '@lucide/vue';
import { show } from '@/routes/entities';

defineProps<{
    entity: {
        name: string;
        slug: string;
        category_name: string;
        type_label: string;
        score: number | null;
        opinion_count: number;
        updated_at?: string;
    };
}>();
</script>

<template>
    <Link
        :href="show(entity.slug)"
        class="group flex h-full flex-col rounded-2xl border border-[#dfe5dc] bg-white p-6 transition duration-200 hover:-translate-y-1 hover:border-[#90b79b] hover:shadow-lg motion-reduce:transform-none"
    >
        <div class="flex items-start justify-between gap-4">
            <span
                class="flex size-12 items-center justify-center rounded-xl bg-[#edf4ec] text-lg font-bold text-[#426448]"
                aria-hidden="true"
                >{{ entity.name.slice(0, 2).toUpperCase() }}</span
            ><ArrowUpRight
                class="size-5 text-[#8b998f] transition group-hover:text-[#087f5b]"
            />
        </div>
        <h3 class="mt-5 text-lg font-bold tracking-tight">{{ entity.name }}</h3>
        <p class="mt-1 text-xs text-[#738076]">
            {{ entity.type_label }} · {{ entity.category_name }}
        </p>
        <div class="mt-5 flex flex-1 flex-col justify-end">
            <div class="flex flex-wrap items-center gap-3">
                <span
                    v-if="entity.score !== null"
                    class="rounded-lg px-3 py-1.5 text-xl font-bold"
                    :class="
                        entity.score >= 70
                            ? 'bg-[#dcf5e5] text-[#17623d]'
                            : entity.score >= 50
                              ? 'bg-[#fff1d5] text-[#845713]'
                              : 'bg-[#fce5e1] text-[#a74232]'
                    "
                    >{{
                        entity.score.toLocaleString('id-ID', {
                            maximumFractionDigits: 1,
                        })
                    }}<span class="text-xs font-medium"> / 100</span></span
                ><span
                    v-else
                    class="rounded-lg bg-[#f1f3ed] px-3 py-2 text-xs font-medium text-[#6c776e]"
                    >Belum cukup opini</span
                ><span class="text-xs text-[#637368]">Sentimen Netijen</span>
            </div>
            <p class="mt-4 flex items-center gap-1.5 text-xs text-[#738076]">
                <MessageCircle class="size-3.5" />{{
                    entity.opinion_count.toLocaleString('id-ID')
                }}
                opini dianalisis
            </p>
            <p v-if="entity.updated_at" class="mt-2 text-xs text-[#738076]">
                Diperbarui {{ entity.updated_at }}
            </p>
        </div>
    </Link>
</template>
