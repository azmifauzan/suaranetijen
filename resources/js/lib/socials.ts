export type OfficialSocial = {
    key: 'instagram' | 'threads' | 'facebook';
    label: string;
    handle: string;
    url: string;
};

/** Official SuaraNetijen accounts. Footer links and Organization JSON-LD `sameAs` both read this list. */
export const officialSocials: OfficialSocial[] = [
    {
        key: 'instagram',
        label: 'Instagram',
        handle: '@suaranetijen.official',
        url: 'https://www.instagram.com/suaranetijen.official/',
    },
    {
        key: 'threads',
        label: 'Threads',
        handle: '@suaranetijen.official',
        url: 'https://www.threads.com/@suaranetijen.official',
    },
    {
        key: 'facebook',
        label: 'Facebook',
        handle: 'suaranetijen.id',
        url: 'https://www.facebook.com/suaranetijen.id',
    },
];
