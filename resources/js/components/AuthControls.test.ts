import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: ['as', 'href', 'method', 'tabindex'],
        setup(props, { slots }) {
            return () =>
                h(
                    props.as === 'button' ? 'button' : 'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
                        method: props.method,
                        tabindex: props.tabindex,
                    },
                    slots.default?.(),
                );
        },
    }),
}));

describe('auth controls', () => {
    it('renders auth links with visible brand styling', async () => {
        const html = await renderToString(
            createSSRApp({
                render: () =>
                    h(
                        TextLink,
                        { href: '/register' },
                        { default: () => 'Daftar' },
                    ),
            }),
        );

        expect(html).toContain('href="/register"');
        expect(html).toContain('text-primary');
        expect(html).toContain('Daftar');
    });

    it('renders a keyboard-accessible password visibility toggle', async () => {
        const html = await renderToString(
            createSSRApp(PasswordInput, {
                id: 'password',
                name: 'password',
            }),
        );

        expect(html).toContain('type="password"');
        expect(html).toContain('data-test="password-visibility-toggle"');
        expect(html).toContain('aria-label="Tampilkan kata sandi"');
        expect(html).not.toContain('tabindex="-1"');
    });
});
