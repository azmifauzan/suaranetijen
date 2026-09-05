import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import PublicEntityCard from '@/components/PublicEntityCard.vue';
import Welcome from '@/pages/Welcome.vue';

const page = vi.hoisted(() => ({
    url: '/',
    props: { auth: { user: null as { name: string } | null } },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: { get: vi.fn(), visit: vi.fn() },
    Head: defineComponent({
        props: { title: String },
        setup:
            (props, { slots }) =>
            () =>
                h('head', [
                    props.title ? h('title', props.title) : null,
                    ...(slots.default?.() ?? []),
                ]),
    }),
    Link: defineComponent({
        props: ['href'],
        setup:
            (props, { slots }) =>
            () =>
                h(
                    'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href.url,
                    },
                    slots.default?.(),
                ),
    }),
}));

beforeEach(() => {
    page.props.auth.user = null;
});

it('keeps search accessible and shows an honest empty state before opinions exist', async () => {
    const html = await renderToString(
        createSSRApp(Welcome, { categories: [] }),
    );

    expect(html).toContain('role="search"');
    expect(html).toContain('role="combobox"');
    expect(html).toContain('Cari brand, produk, atau layanan');
    expect(html).toContain('Sebelum pilih, cek kata netizen.');
    expect(html).toContain(
        '<title>Sentimen Publik Brand, Produk, dan Layanan Indonesia | SuaraNetijen</title>',
    );
    expect(html).toContain('name="description"');
    expect(html).toContain('opini netizen');
    expect(html).toContain('Belum ada entitas dengan data yang cukup');
    expect(html).not.toContain('Baru diperbarui');
    expect(html).toContain('href="/register"');
});

it('connects category discovery and real entity summaries to their detail pages', async () => {
    const html = await renderToString(
        createSSRApp(Welcome, {
            categories: [
                {
                    id: 1,
                    name: 'Internet',
                    slug: 'internet',
                    entities_count: 2,
                },
            ],
            topEntities: [
                {
                    id: 1,
                    name: 'Contoh Internet',
                    slug: 'contoh-internet',
                    type_label: 'Layanan',
                    category_name: 'Internet',
                    score: 78,
                    opinion_count: 150,
                },
            ],
            recentEntities: [
                {
                    id: 2,
                    name: 'Layanan Baru',
                    slug: 'layanan-baru',
                    type_label: 'Layanan',
                    category_name: 'Internet',
                    score: null,
                    opinion_count: 12,
                },
            ],
        }),
    );

    expect(html).toContain('href="/category/internet"');
    expect(html).toContain('href="/e/contoh-internet"');
    expect(html).toContain('150 opini dianalisis');
    expect(html).toContain('Baru diperbarui');
    expect(html).toContain('Belum cukup opini');
    expect(html).toContain('href="/search?q=IndiHome"');
});

it.each([
    { score: 0, text: '0', empty: false },
    { score: 78.5, text: '78,5', empty: false },
    { score: null, text: 'Belum cukup opini', empty: true },
])(
    'renders $score sentiment without converting it into a user star rating',
    async ({ score, text, empty }) => {
        const html = await renderToString(
            createSSRApp(PublicEntityCard, {
                entity: {
                    name: 'Contoh Brand',
                    slug: 'contoh-brand',
                    category_name: 'Internet',
                    type_label: 'Brand',
                    score,
                    opinion_count: 40,
                },
            }),
        );

        expect(html).toContain(text);
        expect(html).toContain('Sentimen Netijen');
        expect(html).toContain('40 opini dianalisis');
        expect(html.includes('/ 100')).toBe(!empty);
        expect(html).not.toContain('Rating Netijen');
    },
);

it('shows the dashboard destination for a signed-in visitor', async () => {
    page.props.auth.user = { name: 'Pengguna' };

    const html = await renderToString(
        createSSRApp(Welcome, { categories: [] }),
    );

    expect(html).toContain('href="/dashboard"');
    expect(html).not.toContain('href="/register"');
});
