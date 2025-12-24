<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GuestNameGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GuestNameGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_non_empty_names(): void
    {
        $name = GuestNameGenerator::generateName();

        $this->assertIsString($name);
        $this->assertNotEmpty($name);
        $this->assertGreaterThan(2, strlen($name));
    }

    #[Test]
    public function it_generates_non_empty_usernames(): void
    {
        $username = GuestNameGenerator::generateUsername();

        $this->assertIsString($username);
        $this->assertNotEmpty($username);
        $this->assertGreaterThan(4, strlen($username));
    }

    #[Test]
    public function it_generates_usernames_with_correct_format(): void
    {
        $username = GuestNameGenerator::generateUsername();

        // Should be lowercase_number format
        $this->assertMatchesRegularExpression('/^[a-z]+_\d{3}$/', $username);

        // Should contain underscore and numbers
        $this->assertStringContainsString('_', $username);
        $this->assertMatchesRegularExpression('/\d/', $username);
    }

    #[Test]
    public function it_generates_names_with_valid_characters(): void
    {
        $name = GuestNameGenerator::generateName();

        // Should only contain letters, spaces, and "the" word
        $this->assertMatchesRegularExpression('/^[A-Za-z\s]+$/', $name);

        // Should not contain numbers or special characters
        $this->assertDoesNotMatchRegularExpression('/[0-9_@#$%^&*()+={}\[\]|\\:";\'<>?,.\\/]/', $name);
    }

    #[Test]
    public function it_generates_different_names_on_multiple_calls(): void
    {
        $names = [];
        $usernames = [];

        // Generate 10 names and usernames
        for ($i = 0; $i < 10; $i++) {
            $names[] = GuestNameGenerator::generateName();
            $usernames[] = GuestNameGenerator::generateUsername();
        }

        // Should have some variety (not all identical)
        $uniqueNames = array_unique($names);
        $uniqueUsernames = array_unique($usernames);

        $this->assertGreaterThan(1, count($uniqueNames), 'Names should have variety');
        $this->assertGreaterThan(1, count($uniqueUsernames), 'Usernames should have variety');
    }

    #[Test]
    public function it_generates_names_from_expected_sources(): void
    {
        // Test that generated names come from Greek/Latin names
        $validNames = [
            'Alexios', 'Dimitris', 'Konstantinos', 'Nikos', 'Panagiotis', 'Stavros', 'Vasilis', 'Yannis',
            'Anastasia', 'Eleni', 'Katerina', 'Maria', 'Sofia', 'Dimitra', 'Ioanna', 'Chrysoula',
            'Aristoteles', 'Platon', 'Sokrates', 'Pythagoras', 'Euklides', 'Archimedes', 'Herakles', 'Odysseus',
            'Athena', 'Artemis', 'Aphrodite', 'Hera', 'Demeter', 'Persephone', 'Hecate', 'Penelope',
            'Marcus', 'Lucius', 'Gaius', 'Publius', 'Quintus', 'Titus', 'Maximus', 'Augustus',
            'Julia', 'Claudia', 'Livia', 'Octavia', 'Flavia', 'Valeria', 'Aurelia', 'Cornelia',
            'Cicero', 'Seneca', 'Ovid', 'Virgil', 'Horace', 'Tacitus', 'Juvenal', 'Catullus',
            'Minerva', 'Venus', 'Diana', 'Juno', 'Vesta', 'Ceres', 'Fortuna', 'Victoria',
        ];

        $adjectives = [
            'Wise', 'Noble', 'Brave', 'Swift', 'Bright', 'Kind', 'Bold', 'Fair',
            'Strong', 'Gentle', 'Clever', 'True', 'Pure', 'Serene', 'Proud', 'Free',
        ];

        // Generate multiple names and check they contain valid elements
        $foundValidName = false;
        for ($i = 0; $i < 20; $i++) {
            $name = GuestNameGenerator::generateName();

            // Check if name contains any of the valid names
            foreach ($validNames as $validName) {
                if (str_contains($name, $validName)) {
                    $foundValidName = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($foundValidName, 'Generated names should contain Greek/Latin names');
    }

    #[Test]
    public function it_generates_usernames_from_expected_sources(): void
    {
        $validNames = [
            'alexios', 'dimitris', 'konstantinos', 'nikos', 'panagiotis', 'stavros', 'vasilis', 'yannis',
            'anastasia', 'eleni', 'katerina', 'maria', 'sofia', 'dimitra', 'ioanna', 'chrysoula',
            'aristoteles', 'platon', 'sokrates', 'pythagoras', 'euklides', 'archimedes', 'herakles', 'odysseus',
            'athena', 'artemis', 'aphrodite', 'hera', 'demeter', 'persephone', 'hecate', 'penelope',
            'marcus', 'lucius', 'gaius', 'publius', 'quintus', 'titus', 'maximus', 'augustus',
            'julia', 'claudia', 'livia', 'octavia', 'flavia', 'valeria', 'aurelia', 'cornelia',
            'cicero', 'seneca', 'ovid', 'virgil', 'horace', 'tacitus', 'juvenal', 'catullus',
            'minerva', 'venus', 'diana', 'juno', 'vesta', 'ceres', 'fortuna', 'victoria',
        ];

        // Generate multiple usernames and check they contain valid elements
        $foundValidUsername = false;
        for ($i = 0; $i < 20; $i++) {
            $username = GuestNameGenerator::generateUsername();
            $baseUsername = explode('_', $username)[0];

            if (in_array($baseUsername, $validNames)) {
                $foundValidUsername = true;
                break;
            }
        }

        $this->assertTrue($foundValidUsername, 'Generated usernames should contain Greek/Latin names');
    }

    #[Test]
    public function it_never_generates_generic_guest_names(): void
    {
        // Ensure we never get the old broken names
        $forbiddenNames = [
            'Guest User',
            'guest_',
            'Anonymous',
            'anonymous_',
            'User',
            'TempUser',
        ];

        // Test multiple generations
        for ($i = 0; $i < 50; $i++) {
            $name = GuestNameGenerator::generateName();
            $username = GuestNameGenerator::generateUsername();

            foreach ($forbiddenNames as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $name, "Generated name should not contain '{$forbidden}'");
                $this->assertStringNotContainsString($forbidden, $username, "Generated username should not contain '{$forbidden}'");
            }
        }
    }

    #[Test]
    public function it_generates_names_suitable_for_display(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $name = GuestNameGenerator::generateName();

            // Should be suitable for UI display
            $this->assertLessThanOrEqual(50, strlen($name), 'Name should not be too long for UI');
            $this->assertGreaterThanOrEqual(3, strlen($name), 'Name should not be too short');

            // Should start with capital letter
            $this->assertTrue(ctype_upper($name[0]), 'Name should start with capital letter');

            // Should not have double spaces
            $this->assertStringNotContainsString('  ', $name, 'Name should not have double spaces');

            // Should not start or end with spaces
            $this->assertEquals(trim($name), $name, 'Name should not have leading/trailing spaces');
        }
    }
}
