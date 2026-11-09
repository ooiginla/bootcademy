import { usePage } from '@inertiajs/react';

export function useAcademyTerminology() {
    const academy = usePage().props.academy;
    const singular = academy?.terminology?.singular ?? 'Student';
    const plural = academy?.terminology?.plural ?? 'Students';
    const guardianSingular =
        academy?.terminology?.guardian_singular ?? 'Guardian';
    const guardianPlural = academy?.terminology?.guardian_plural ?? 'Guardians';

    return {
        singular,
        plural,
        singularLower: singular.toLocaleLowerCase(),
        pluralLower: plural.toLocaleLowerCase(),
        guardianSingular,
        guardianPlural,
        guardianSingularLower: guardianSingular.toLocaleLowerCase(),
        guardianPluralLower: guardianPlural.toLocaleLowerCase(),
        collectGuardianInformation:
            academy?.collect_guardian_information ?? true,
    };
}
