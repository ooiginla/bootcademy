import { useEffect, useMemo, useRef, useState } from 'react';

function parseStatistic(value: string) {
    const match = value.trim().match(/^(\D*)([\d,.]+)(.*)$/);
    if (!match) return null;

    const numericText = match[2].replaceAll(',', '');
    const number = Number.parseFloat(numericText);
    if (!Number.isFinite(number)) return null;

    return {
        prefix: match[1],
        number,
        suffix: match[3],
        decimals: numericText.includes('.')
            ? (numericText.split('.')[1]?.length ?? 0)
            : 0,
    };
}

export default function AnimatedStatistic({
    value,
    label,
}: {
    value: string;
    label: string;
}) {
    const parsed = useMemo(() => parseStatistic(value), [value]);
    const [current, setCurrent] = useState(0);
    const element = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!parsed) return;

        let frame = 0;
        let observer: IntersectionObserver | null = null;
        let started = false;

        const showFinalValue =
            window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ??
            false;

        const start = () => {
            if (started) return;
            started = true;

            if (showFinalValue) {
                setCurrent(parsed.number);
                return;
            }

            const startedAt = performance.now();
            const duration = 1600;
            const animate = (now: number) => {
                const progress = Math.min((now - startedAt) / duration, 1);
                const eased = 1 - (1 - progress) ** 3;
                setCurrent(parsed.number * eased);
                if (progress < 1) frame = requestAnimationFrame(animate);
            };
            frame = requestAnimationFrame(animate);
        };

        if ('IntersectionObserver' in window && element.current) {
            observer = new IntersectionObserver(
                (entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) {
                        start();
                        observer?.disconnect();
                    }
                },
                { threshold: 0.3 },
            );
            observer.observe(element.current);
        } else {
            start();
        }

        return () => {
            observer?.disconnect();
            cancelAnimationFrame(frame);
        };
    }, [parsed]);

    if (!parsed) {
        return (
            <div className="text-center" aria-label={`${value} ${label}`}>
                <p className="text-4xl font-black tracking-tight sm:text-5xl">
                    {value}
                </p>
                <p className="mt-2 text-sm font-bold opacity-70">{label}</p>
            </div>
        );
    }

    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: parsed.decimals,
        maximumFractionDigits: parsed.decimals,
    }).format(current);

    return (
        <div
            ref={element}
            className="text-center"
            aria-label={`${value} ${label}`}
        >
            <p
                aria-hidden="true"
                data-statistic-value={value}
                className="text-4xl font-black tracking-tight sm:text-5xl"
            >
                {parsed.prefix}
                {formatted}
                {parsed.suffix}
            </p>
            <p aria-hidden="true" className="mt-2 text-sm font-bold opacity-70">
                {label}
            </p>
        </div>
    );
}
