import { SVGAttributes } from 'react';

/**
 * The Piotrack mark — the rising trend line from the marketing site, replacing
 * the starter kit's Laravel logo. Stroke-based so `text-*` utilities color it
 * via currentColor wherever it is placed.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2.4"
            strokeLinecap="round"
            strokeLinejoin="round"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path d="M3 17l5-5 4 3 8-9" />
            <path d="M15 6h5v5" />
        </svg>
    );
}
