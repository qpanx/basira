export type LandingCopy = {
    meta: {
        title: string;
        description: string;
    };
    nav: {
        features: string;
        how: string;
        family: string;
        pricing: string;
        faq: string;
        explore: string;
        login: string;
        dashboard: string;
        menu: string;
        close: string;
        label: string;
        language: string;
        skip: string;
    };
    hero: {
        eyebrow: string;
        title: string;
        accent: string;
        description: string;
        primary: string;
        secondary: string;
        note: string;
        concept: string;
        assist: string;
        assistDetail: string;
        connected: string;
        sos: string;
        phone: string;
        phoneStatus: string;
        phoneLabel: string;
        phoneHint: string;
    };
    values: {
        title: string;
        description: string;
    }[];
    how: {
        eyebrow: string;
        title: string;
        description: string;
        steps: {
            title: string;
            description: string;
        }[];
        flow: string[];
    };
    features: {
        eyebrow: string;
        title: string;
        description: string;
        items: {
            title: string;
            label: string;
            description: string;
            visual: string;
        }[];
        note: string;
    };
    family: {
        eyebrow: string;
        title: string;
        description: string;
        points: string[];
        link: string;
        demo: string;
        dashboard: string;
        person: string;
        initial: string;
        relationship: string;
        contact: string;
        battery: string;
        batteryValue: string;
        status: string;
        last: string;
        live: string;
        offline: string;
        connected: string;
        location: string;
        lastTime: string;
        liveTime: string;
        offlineTime: string;
        stale: string;
        fresh: string;
        offlineNote: string;
        sos: string;
        sosDetail: string;
        states: string;
        mapLabel: string;
    };
    pricing: {
        eyebrow: string;
        title: string;
        description: string;
        soon: string;
        planned: string;
        highlight: string;
        cta: string;
        includesFree: string;
        includesPlus: string;
        notice: string;
        existing: string;
        future: string;
        plans: {
            name: string;
            tagline: string;
            features: string[];
        }[];
    };
    privacy: {
        eyebrow: string;
        title: string;
        description: string;
        items: {
            title: string;
            description: string;
        }[];
    };
    faq: {
        eyebrow: string;
        title: string;
        items: {
            question: string;
            answer: string;
        }[];
    };
    final: {
        eyebrow: string;
        title: string;
        cta: string;
    };
    footer: {
        description: string;
        contact: string;
        contactSoon: string;
        legal: string;
        rights: string;
        back: string;
        note: string;
    };
};
