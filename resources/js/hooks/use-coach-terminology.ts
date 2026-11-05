import { usePage } from '@inertiajs/react';

export function useCoachTerminology() {
    const terminology = usePage().props.academy?.terminology;
    const singular = terminology?.staff_singular ?? 'Coach';
    const plural = terminology?.staff_plural ?? 'Coaches';

    return {
        singular,
        plural,
        singularLower: singular.toLocaleLowerCase(),
        pluralLower: plural.toLocaleLowerCase(),
    };
}
