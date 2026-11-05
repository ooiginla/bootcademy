import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M4.5 18.5 10.35 5.7a1.82 1.82 0 0 1 3.3 0L19.5 18.5"
                stroke="currentColor"
                strokeWidth="2.2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M7.15 13h9.7"
                stroke="currentColor"
                strokeWidth="2.2"
                strokeLinecap="round"
            />
            <circle cx="18.75" cy="5.25" r="2.25" fill="currentColor" />
        </svg>
    );
}
