export type IconName =
    | 'arrow'
    | 'eye'
    | 'heart'
    | 'shield'
    | 'pin'
    | 'scan'
    | 'text'
    | 'route'
    | 'bell'
    | 'cloud'
    | 'cane'
    | 'check'
    | 'menu'
    | 'close'
    | 'plus';

const paths: Record<IconName, string> = {
    arrow: 'M5 12h14m-6-6 6 6-6 6',
    eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Zm13 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
    heart: 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z',
    shield: 'M12 3 3 7v5c0 5 9 9 9 9s9-4 9-9V7l-9-4Zm-4 9 3 3 5-6',
    pin: 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Zm-5 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
    scan: 'M8 3H3v5m13-5h5v5M3 16v5h5m8 0h5v-5M7 7h10v10H7Z',
    text: 'M4 4h16M12 4v16m-4 0h8M4 9V4m16 5V4',
    route: 'M5 20V8a4 4 0 0 1 8 0v8a4 4 0 0 0 8 0V4m-3 3 3-3 3 3',
    bell: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
    cloud: 'M7 18h11a4 4 0 0 0 .5-8A7 7 0 0 0 5 8a5 5 0 0 0 2 10Z',
    cane: 'm9 22 5-16a3 3 0 0 0-6-2L7 7',
    check: 'm5 12 4 4L19 6',
    menu: 'M4 6h16M4 12h16M4 18h16',
    close: 'm6 6 12 12M6 18 18 6',
    plus: 'M12 5v14M5 12h14',
};

export function Icon({
    name,
    className = '',
}: {
    name: IconName;
    className?: string;
}) {
    return (
        <svg
            aria-hidden="true"
            className={className}
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.6"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d={paths[name]} />
        </svg>
    );
}

export function Brand() {
    return (
        <span className="inline-flex items-center gap-2.5" dir="ltr">
            <span className="brand-symbol">
                <Icon name="eye" />
            </span>
            <span className="text-xl font-semibold tracking-[0.17em]">
                BASIRA
            </span>
        </span>
    );
}
