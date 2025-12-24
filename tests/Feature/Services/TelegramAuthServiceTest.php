<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\TelegramAuthService;

beforeEach(function (): void {
    $this->service = app(TelegramAuthService::class);
    config(['telegram.bot_token' => 'test_bot_token_123456']);
});

describe('Hash Verification', function (): void {
    test('verifyHash returns false when bot token not configured', function (): void {
        config(['telegram.bot_token' => null]);

        $result = $this->service->verifyHash(['id' => 123, 'hash' => 'somehash']);

        expect($result)->toBeFalse();
    });

    test('verifyHash returns false when hash is missing', function (): void {
        $result = $this->service->verifyHash(['id' => 123]);

        expect($result)->toBeFalse();
    });

    test('verifyHash validates correct hash', function (): void {
        $botToken = 'test_bot_token';
        config(['telegram.bot_token' => $botToken]);

        // Build test data
        $data = [
            'id' => '123456789',
            'first_name' => 'Test',
            'username' => 'testuser',
            'auth_date' => '1704067200',
        ];

        // Generate correct hash
        $secretKey = hash('sha256', $botToken, true);
        ksort($data);
        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $dataCheckArr[] = "{$key}={$value}";
            }
        }
        $dataCheckString = implode("\n", $dataCheckArr);
        $correctHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $data['hash'] = $correctHash;

        $result = $this->service->verifyHash($data);

        expect($result)->toBeTrue();
    });

    test('verifyHash rejects incorrect hash', function (): void {
        $data = [
            'id' => '123456789',
            'first_name' => 'Test',
            'hash' => 'invalid_hash_value',
        ];

        $result = $this->service->verifyHash($data);

        expect($result)->toBeFalse();
    });

    test('verifyHash ignores null values in data', function (): void {
        $botToken = 'test_bot_token';
        config(['telegram.bot_token' => $botToken]);

        // Build test data with null values
        $data = [
            'id' => '123456789',
            'first_name' => 'Test',
            'last_name' => null,
            'username' => '',
            'auth_date' => '1704067200',
        ];

        // Generate correct hash (excluding null/empty values)
        $secretKey = hash('sha256', $botToken, true);
        $filteredData = array_filter($data, fn ($v) => $v !== null && $v !== '');
        ksort($filteredData);
        $dataCheckArr = [];
        foreach ($filteredData as $key => $value) {
            $dataCheckArr[] = "{$key}={$value}";
        }
        $dataCheckString = implode("\n", $dataCheckArr);
        $correctHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $data['hash'] = $correctHash;

        $result = $this->service->verifyHash($data);

        expect($result)->toBeTrue();
    });
});

describe('User Creation', function (): void {
    test('findOrCreateUser creates new user from Telegram data', function (): void {
        $telegramData = [
            'id' => 123456789,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'photo_url' => 'https://t.me/photos/johndoe.jpg',
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->telegram_id)->toBe(123456789);
        expect($user->telegram_username)->toBe('johndoe');
        expect($user->telegram_photo_url)->toBe('https://t.me/photos/johndoe.jpg');
        expect($user->display_name)->toBe('John Doe');
        expect($user->status)->toBe('approved');
        expect($user->email_verified_at)->not->toBeNull();
    });

    test('findOrCreateUser returns existing user by telegram_id', function (): void {
        $existingUser = User::create([
            'username' => 'existing_telegram',
            'email' => 'telegram_999@noemail.local',
            'password' => bcrypt('test'),
            'telegram_id' => 999888777,
            'status' => 'approved',
        ]);

        $telegramData = [
            'id' => 999888777,
            'first_name' => 'Existing',
            'username' => 'existinguser',
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->id)->toBe($existingUser->id);
    });

    test('findOrCreateUser uses telegram username for username', function (): void {
        $telegramData = [
            'id' => 111222333,
            'first_name' => 'Jane',
            'username' => 'jane_doe',
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->username)->toBe('jane_doe');
    });

    test('findOrCreateUser uses first+last name when no username', function (): void {
        $telegramData = [
            'id' => 444555666,
            'first_name' => 'Bob',
            'last_name' => 'Smith',
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->username)->toBe('bobsmith');
        expect($user->display_name)->toBe('Bob Smith');
    });

    test('findOrCreateUser generates random username as fallback', function (): void {
        $telegramData = [
            'id' => 777888999,
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->username)->toStartWith('telegram_');
        expect(strlen($user->username))->toBeGreaterThan(10);
    });

    test('findOrCreateUser handles username collision', function (): void {
        User::create([
            'username' => 'colliding_user',
            'email' => 'collision@test.com',
            'password' => bcrypt('test'),
            'status' => 'approved',
        ]);

        $telegramData = [
            'id' => 112233445,
            'first_name' => 'Collision',
            'username' => 'colliding_user',
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->username)->toBe('colliding_user_1');
    });

    test('findOrCreateUser updates photo on existing user', function (): void {
        $existingUser = User::create([
            'username' => 'photo_test',
            'email' => 'telegram_photo@noemail.local',
            'password' => bcrypt('test'),
            'telegram_id' => 556677889,
            'telegram_photo_url' => 'https://old-photo.jpg',
            'status' => 'approved',
        ]);

        $telegramData = [
            'id' => 556677889,
            'first_name' => 'Photo',
            'photo_url' => 'https://new-photo.jpg',
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->fresh()->telegram_photo_url)->toBe('https://new-photo.jpg');
    });

    test('findOrCreateUser updates username on existing user', function (): void {
        $existingUser = User::create([
            'username' => 'username_test',
            'email' => 'telegram_username@noemail.local',
            'password' => bcrypt('test'),
            'telegram_id' => 998877665,
            'telegram_username' => 'old_username',
            'status' => 'approved',
        ]);

        $telegramData = [
            'id' => 998877665,
            'first_name' => 'Username',
            'username' => 'new_username',
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->fresh()->telegram_username)->toBe('new_username');
    });

    test('findOrCreateUser sets placeholder email', function (): void {
        $telegramData = [
            'id' => 123123123,
            'first_name' => 'Email',
            'username' => 'emailtest',
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->email)->toBe('telegram_123123123@noemail.local');
    });

    test('findOrCreateUser handles display name when only first name', function (): void {
        $telegramData = [
            'id' => 321321321,
            'first_name' => 'OnlyFirst',
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->display_name)->toBe('OnlyFirst');
    });

    test('findOrCreateUser creates fallback display name', function (): void {
        $telegramData = [
            'id' => 999111222,
        ];

        $user = $this->service->findOrCreateUser($telegramData);

        expect($user->display_name)->toBe('Telegram User 999111222');
    });
});
