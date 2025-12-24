<?php

declare(strict_types=1);

if (! function_exists('mask_email')) {
    /**
     * Mask an email address for privacy.
     * Examples:
     *
     *   - john@example.com -> j***@e***.com (default mode)
     *
     *   - ab@test.co -> a***@t***.co (default mode)
     *
     *   - test@example.co.uk -> t***@e***.co.uk (default mode)
     *
     *   - john@example.com -> ****@example.com (admin mode: hide local, show domain)
     */
    function mask_email(?string $email, string $mode = 'default'): string
    {
        if ($email === null || $email === '' || ! str_contains($email, '@')) {
            return $email ?? '';
        }

        [$localPart, $domain] = explode('@', $email, 2);

        // Admin mode: hide local part completely, show full domain
        if ($mode === 'admin') {
            return '****@' . $domain;
        }

        // Default mode: mask both local and domain
        // Mask local part (keep first char, rest as ***)
        $maskedLocal = substr($localPart, 0, 1) . '***';

        // Mask domain (keep first char of domain name and full TLD)
        $domainParts = explode('.', $domain);
        $maskedDomainName = substr($domainParts[0], 0, 1) . '***';

        // Rebuild domain with original TLD(s)
        array_shift($domainParts);
        $maskedDomain = $maskedDomainName . (count($domainParts) > 0 ? '.' . implode('.', $domainParts) : '');

        return $maskedLocal . '@' . $maskedDomain;
    }
}

if (! function_exists('sanitize_markdown_for_preview')) {
    /**
     * Sanitize markdown content for preview by replacing images with translated placeholder.
     * Replaces markdown image syntax ![alt](url) with a translatable placeholder.
     */
    function sanitize_markdown_for_preview(string $text): string
    {
        $placeholder = __('messages.content.attached_image');

        return (string) preg_replace('/!\[[^\]]*\]\([^)]+\)/', $placeholder, $text);
    }
}

if (! function_exists('truncate_content')) {
    /**
     * Truncate content for previews, sanitizing markdown images first.
     */
    function truncate_content(string $text, int $length = 200): string
    {
        $text = sanitize_markdown_for_preview($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }
}
