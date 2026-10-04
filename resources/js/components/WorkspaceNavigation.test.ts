import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { Auth } from '@/types';

const { page } = vi.hoisted(() => ({
    page: {
        url: '/settings/profile',
        props: { auth: {} as Auth },
    },
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/vue3')>();

    return {
        ...original,
        usePage: () => page,
        Link: defineComponent({
            props: ['href'],
            setup(props, { slots }) {
                return () =>
                    h(
                        'a',
                        {
                            href:
                                typeof props.href === 'string'
                                    ? props.href
                                    : props.href?.url,
                        },
                        slots.default?.(),
                    );
            },
        }),
    };
});

async function sidebarHtml(): Promise<string> {
    return renderToString(
        createSSRApp({
            render: () =>
                h(SidebarProvider, {}, { default: () => h(AppSidebar) }),
        }),
    );
}

describe('shared account workspace navigation', () => {
    beforeEach(() => {
        page.props.auth = {
            user: {
                id: 1,
                name: 'Staff member',
                email: 'staff@example.test',
                email_verified_at: '2026-10-05',
                created_at: '',
                updated_at: '',
            },
            canUseCustomerArea: false,
            workspace: 'operations',
            workspaceUrl: '/operations',
        };
    });

    it('gives staff a full-page Operations link without customer navigation', async () => {
        const html = await sidebarHtml();

        expect(html).toContain('href="/operations"');
        expect(html).toContain('Operations');
        expect(html).not.toContain('href="/dashboard"');
        expect(html).not.toContain('Notifications');
        expect(html).not.toContain('Find a facility');
    });

    it('keeps customer navigation for a genuine customer role', async () => {
        page.props.auth.canUseCustomerArea = true;
        page.props.auth.workspace = 'customer';
        page.props.auth.workspaceUrl = '/dashboard';

        const html = await sidebarHtml();

        expect(html).toContain('href="/dashboard"');
        expect(html).toContain('Notifications');
        expect(html).toContain('Find a facility');
    });

    it('retains both workspaces for mixed staff and customer roles', async () => {
        page.props.auth.canUseCustomerArea = true;
        page.props.auth.workspace = 'management';
        page.props.auth.workspaceUrl = '/management';

        const html = await sidebarHtml();

        expect(html).toContain('href="/management"');
        expect(html).toContain('Management');
        expect(html).toContain('href="/dashboard"');
        expect(html).toContain('Notifications');
    });
});
