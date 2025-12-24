<?php

declare(strict_types=1);

namespace App\Services;

final class GuestNameGenerator
{
    private static array $greekNames = [
        'Alexios', 'Dimitris', 'Konstantinos', 'Nikos', 'Panagiotis', 'Stavros', 'Vasilis', 'Yannis',
        'Anastasia', 'Eleni', 'Katerina', 'Maria', 'Sofia', 'Dimitra', 'Ioanna', 'Chrysoula',
        'Aristoteles', 'Platon', 'Sokrates', 'Pythagoras', 'Euklides', 'Archimedes', 'Herakles', 'Odysseus',
        'Athena', 'Artemis', 'Aphrodite', 'Hera', 'Demeter', 'Persephone', 'Hecate', 'Penelope',
    ];

    private static array $latinNames = [
        'Marcus', 'Lucius', 'Gaius', 'Publius', 'Quintus', 'Titus', 'Maximus', 'Augustus',
        'Julia', 'Claudia', 'Livia', 'Octavia', 'Flavia', 'Valeria', 'Aurelia', 'Cornelia',
        'Cicero', 'Seneca', 'Ovid', 'Virgil', 'Horace', 'Tacitus', 'Juvenal', 'Catullus',
        'Minerva', 'Venus', 'Diana', 'Juno', 'Vesta', 'Ceres', 'Fortuna', 'Victoria',
    ];

    private static array $adjectives = [
        'Wise', 'Noble', 'Brave', 'Swift', 'Bright', 'Kind', 'Bold', 'Fair',
        'Strong', 'Gentle', 'Clever', 'True', 'Pure', 'Serene', 'Proud', 'Free',
    ];

    public static function generateName(): string
    {
        $names = array_merge(self::$greekNames, self::$latinNames);
        $name = $names[array_rand($names)];

        // Sometimes add an adjective
        if (random_int(1, 3) === 1) {
            $adjective = self::$adjectives[array_rand(self::$adjectives)];
            $name = random_int(1, 2) === 1 ? "{$adjective} {$name}" : "{$name} the {$adjective}";
        }

        return $name;
    }

    public static function generateUsername(?string $displayName = null): string
    {
        if ($displayName !== null && $displayName !== '') {
            // Convert display name to slug format
            $slug = strtolower(str_replace(['the ', ' '], ['', '_'], $displayName));
            $suffix = random_int(100, 999);

            return $slug . '_' . $suffix;
        }

        // Fallback to old method if no display name provided
        $names = array_merge(self::$greekNames, self::$latinNames);
        $name = $names[array_rand($names)];
        $suffix = random_int(100, 999);

        return strtolower($name) . '_' . $suffix;
    }
}
