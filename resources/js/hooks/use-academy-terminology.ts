import { usePage } from '@inertiajs/react';

export function useAcademyTerminology() {
    const academy = usePage().props.academy;
    const singular = academy?.terminology?.singular ?? 'Student';
    const plural = academy?.terminology?.plural ?? 'Students';

    return {
        singular,
        plural,
        singularLower: singular.toLocaleLowerCase(),
        pluralLower: plural.toLocaleLowerCase(),
    };
}
