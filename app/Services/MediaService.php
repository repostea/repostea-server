<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MediaService
{
    public function __construct(
        private readonly UrlValidationService $urlValidator,
    ) {}

    private array $providers = [
        // Video platforms
        'youtube.com' => 'youtube',
        'youtu.be' => 'youtube',
        'vimeo.com' => 'vimeo',
        'dailymotion.com' => 'dailymotion',
        'facebook.com/watch' => 'facebook',

        // Podcast platforms
        'soundcloud.com' => 'soundcloud',
        'spotify.com' => 'spotify',
        'podcasts.apple.com' => 'apple_podcasts',
        'ivoox.com' => 'ivoox',
        'anchor.fm' => 'anchor',
        'spreaker.com' => 'spreaker',
        'transistor.fm' => 'transistor',
        'buzzsprout.com' => 'buzzsprout',
        'podbean.com' => 'podbean',
        'captivate.fm' => 'captivate',
        'castos.com' => 'castos',
        'acast.com' => 'acast',
        'megaphone.fm' => 'megaphone',
        'player.simplecast.com' => 'simplecast',
        'simplecast.com' => 'simplecast',
        'podigee.io' => 'podigee',
        'podcasts.google.com' => 'google_podcasts',

        // Music platforms
        'bandcamp.com' => 'bandcamp',
        'mixcloud.com' => 'mixcloud',
        'audioboom.com' => 'audioboom',
        'deezer.com' => 'deezer',
        'suno.com' => 'suno',

        // Audiobooks
        'audible.com' => 'audible',
        'audible.es' => 'audible',
        'audible.co.uk' => 'audible',

        // Social media platforms
        'twitter.com' => 'twitter',
        'x.com' => 'twitter',
        'instagram.com' => 'instagram',
        'tiktok.com' => 'tiktok',
        'vm.tiktok.com' => 'tiktok',
    ];

    public function detectMediaProvider(string $url): ?string
    {
        if (str_contains($url, 'spotify.com/show/') ||
            str_contains($url, 'spotify.com/episode/') ||
            str_contains($url, 'spotify.com/podcast/') ||
            str_contains($url, 'spotify.com/track/') ||
            str_contains($url, 'spotify.com/album/') ||
            str_contains($url, 'spotify.com/playlist/')) {
            return 'spotify';
        }

        foreach ($this->providers as $domain => $provider) {
            if (str_contains($url, $domain)) {
                return $provider;
            }
        }

        return null;
    }

    public function getMediaType(?string $provider): ?string
    {
        if ($provider === null) {
            return null;
        }

        $videoProviders = ['youtube', 'vimeo', 'dailymotion', 'facebook', 'twitter', 'instagram', 'tiktok'];
        $audioProviders = [
            'soundcloud', 'spotify', 'apple_podcasts', 'simplecast',
            'ivoox', 'anchor', 'spreaker', 'transistor', 'buzzsprout',
            'podbean', 'captivate', 'castos', 'acast', 'megaphone',
            'podigee', 'google_podcasts', 'bandcamp', 'mixcloud',
            'audioboom', 'deezer', 'audible', 'suno',
        ];

        if (in_array($provider, $videoProviders, true)) {
            return 'video';
        } elseif (in_array($provider, $audioProviders, true)) {
            return 'audio';
        }

        return null;
    }

    public function validateMediaUrl(string $url): array
    {
        $provider = $this->detectMediaProvider($url);
        $mediaType = $this->getMediaType($provider);

        return [
            'valid' => $provider !== null,
            'provider' => $provider,
            'url' => $url,
            'type' => $mediaType,
        ];
    }

    public function getMediaInfo(string $url): array
    {
        $provider = $this->detectMediaProvider($url);
        $mediaType = $this->getMediaType($provider);

        $mediaInfo = [
            'url' => $url,
            'provider' => $provider,
            'type' => $mediaType,
            'valid' => $provider !== null,
        ];

        try {
            switch ($provider) {
                case 'youtube':
                    $mediaInfo = array_merge($mediaInfo, $this->getYouTubeInfo($url));
                    break;
                case 'vimeo':
                    $mediaInfo = array_merge($mediaInfo, $this->getVimeoInfo($url));
                    break;
                case 'soundcloud':
                    $mediaInfo = array_merge($mediaInfo, $this->getSoundCloudInfo($url));
                    break;
                case 'spotify':
                    $mediaInfo = array_merge($mediaInfo, $this->getSpotifyInfo($url));
                    break;
                case 'apple_podcasts':
                    $mediaInfo = array_merge($mediaInfo, $this->getApplePodcastsInfo($url));
                    break;
                case 'ivoox':
                    $mediaInfo = array_merge($mediaInfo, $this->getIVooxInfo($url));
                    break;
                case 'anchor':
                    $mediaInfo = array_merge($mediaInfo, $this->getAnchorInfo($url));
                    break;
                case 'spreaker':
                    $mediaInfo = array_merge($mediaInfo, $this->getSpreakerInfo($url));
                    break;
                case 'mixcloud':
                    $mediaInfo = array_merge($mediaInfo, $this->getMixcloudInfo($url));
                    break;
                case 'bandcamp':
                    $mediaInfo = array_merge($mediaInfo, $this->getBandcampInfo($url));
                    break;
                case 'transistor':
                    $mediaInfo = array_merge($mediaInfo, $this->getTransistorInfo($url));
                    break;
                case 'buzzsprout':
                    $mediaInfo = array_merge($mediaInfo, $this->getBuzzsproutInfo($url));
                    break;
                case 'podbean':
                    $mediaInfo = array_merge($mediaInfo, $this->getPodbeanInfo($url));
                    break;
                case 'captivate':
                    $mediaInfo = array_merge($mediaInfo, $this->getCaptivateInfo($url));
                    break;
                case 'castos':
                    $mediaInfo = array_merge($mediaInfo, $this->getCastosInfo($url));
                    break;
                case 'acast':
                    $mediaInfo = array_merge($mediaInfo, $this->getAcastInfo($url));
                    break;
                case 'megaphone':
                    $mediaInfo = array_merge($mediaInfo, $this->getMegaphoneInfo($url));
                    break;
                case 'simplecast':
                    $mediaInfo = array_merge($mediaInfo, $this->getSimplecastInfo($url));
                    break;
                case 'podigee':
                    $mediaInfo = array_merge($mediaInfo, $this->getPodigeeInfo($url));
                    break;
                case 'google_podcasts':
                    $mediaInfo = array_merge($mediaInfo, $this->getGooglePodcastsInfo($url));
                    break;
                case 'audioboom':
                    $mediaInfo = array_merge($mediaInfo, $this->getAudioboomInfo($url));
                    break;
                case 'deezer':
                    $mediaInfo = array_merge($mediaInfo, $this->getDeezerInfo($url));
                    break;
                case 'suno':
                    $mediaInfo = array_merge($mediaInfo, $this->getSunoInfo($url));
                    break;
                case 'audible':
                    $mediaInfo = array_merge($mediaInfo, $this->getAudibleInfo($url));
                    break;
                case 'twitter':
                    $mediaInfo = array_merge($mediaInfo, $this->getTwitterInfo($url));
                    break;
                case 'instagram':
                    $mediaInfo = array_merge($mediaInfo, $this->getInstagramInfo($url));
                    break;
                case 'tiktok':
                    $mediaInfo = array_merge($mediaInfo, $this->getTikTokInfo($url));
                    break;
            }
        } catch (Exception $e) {
            Log::error('Error getting media info', [
                'url' => $url,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }

        return $mediaInfo;
    }

    private function getYouTubeInfo(string $url): array
    {
        $videoId = $this->extractYouTubeId($url);

        if ($videoId === null) {
            return ['error' => __('media.youtube_id_extraction_error')];
        }

        return [
            'id' => $videoId,
            'embed_url' => "https://www.youtube.com/embed/{$videoId}",
            'thumbnail_url' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
        ];
    }

    private function extractYouTubeId(string $url): ?string
    {
        // Includes support for YouTube Shorts
        $pattern = '/(?:youtube\.com\/(?:shorts\/|[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getVimeoInfo(string $url): array
    {
        $videoId = $this->extractVimeoId($url);

        if ($videoId === null) {
            return ['error' => __('media.vimeo_id_extraction_error')];
        }

        try {
            $apiUrl = "https://vimeo.com/api/v2/video/{$videoId}.json";
            $this->urlValidator->validate($apiUrl);
            $response = Http::get($apiUrl);

            if ($response->successful() && is_array($response->json()) && ! empty($response->json())) {
                $data = $response->json()[0] ?? null;

                if ($data !== null && $data !== []) {
                    return [
                        'id' => $videoId,
                        'title' => $data['title'] ?? null,
                        'description' => $data['description'] ?? null,
                        'embed_url' => "https://player.vimeo.com/video/{$videoId}",
                        'thumbnail_url' => $data['thumbnail_large'] ?? null,
                    ];
                }
            }
        } catch (Exception $e) {
            Log::error('Error getting Vimeo info', [
                'url' => $url,
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'id' => $videoId,
            'embed_url' => "https://player.vimeo.com/video/{$videoId}",
        ];
    }

    private function extractVimeoId(string $url): ?string
    {
        $pattern = '/vimeo\.com\/(?:video\/)?([0-9]+)/';

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getSoundCloudInfo(string $url): array
    {
        $encodedUrl = urlencode($url);

        return [
            'url' => $url,
            'embed_url' => "https://w.soundcloud.com/player/?url={$encodedUrl}&color=%23ff5500&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true&visual=true",
        ];
    }

    private function getSpotifyInfo(string $url): array
    {
        // Extract Spotify ID from URL
        // Supports: show, episode, podcast, track, album, playlist
        // Also handles URLs with locale like /intl-es/
        if (preg_match('/spotify\.com\/(?:intl-[a-z]{2}\/)?(show|episode|podcast|track|album|playlist)\/([a-zA-Z0-9]+)/', $url, $matches)) {
            $type = $matches[1]; // 'show', 'episode', 'podcast', 'track', 'album', or 'playlist'
            $id = $matches[2];

            return [
                'id' => $id,
                'embed_url' => "https://open.spotify.com/embed/{$type}/{$id}",
            ];
        }

        return ['url' => $url];
    }

    private function getApplePodcastsInfo(string $url): array
    {
        // Apple Podcasts embed
        // Format: https://podcasts.apple.com/us/podcast/name/id123456789
        // Extract podcast ID from URL
        if (preg_match('/id(\d+)/', $url, $matches)) {
            $podcastId = $matches[1];

            return [
                'id' => $podcastId,
                'embed_url' => "https://embed.podcasts.apple.com/us/podcast/id{$podcastId}",
            ];
        }

        return ['url' => $url];
    }

    private function getIVooxInfo(string $url): array
    {
        // iVoox - uses jPlayer (HTML5 + Flash fallback)
        // URLs can be:
        // Episode: https://www.ivoox.com/audio-title_md_12345678_1.mp3
        // Player: https://www.ivoox.com/player_ej_12345678_6_1.html
        // Podcast: https://www.ivoox.com/podcast-name_sq_f12345678_1.html

        // Direct player URL
        if (preg_match('/ivoox\.com\/player_ej_(\d+)_/', $url, $matches)) {
            $episodeId = $matches[1];

            return [
                'id' => $episodeId,
                'embed_url' => "https://www.ivoox.com/player_ej_{$episodeId}_6_1.html",
            ];
        }

        // Episode with _md_ or _rf_ format
        if (preg_match('/ivoox\.com\/.*_(?:md|rf)_(\d+)_/', $url, $matches)) {
            $episodeId = $matches[1];

            return [
                'id' => $episodeId,
                'embed_url' => "https://www.ivoox.com/player_ej_{$episodeId}_6_1.html",
            ];
        }

        // Podcast channel with _sq_f format
        if (preg_match('/ivoox\.com\/.*_sq_f(\d+)_/', $url, $matches)) {
            $podcastId = $matches[1];

            return [
                'id' => $podcastId,
                'embed_url' => "https://www.ivoox.com/player_ek_podcast_{$podcastId}_1.html",
            ];
        }

        return ['url' => $url];
    }

    private function getAnchorInfo(string $url): array
    {
        // Anchor.fm is now Spotify for Podcasters
        // Format: https://anchor.fm/podcast-name/episodes/episode-name-episode-id
        if (preg_match('/anchor\.fm\/([a-zA-Z0-9-]+)\/episodes\/([a-zA-Z0-9-]+)/', $url, $matches)) {
            $podcastName = $matches[1];
            $episodeName = $matches[2];

            return [
                'id' => $episodeName,
                'embed_url' => "https://anchor.fm/{$podcastName}/embed/episodes/{$episodeName}",
            ];
        }

        return ['url' => $url];
    }

    private function getSpreakerInfo(string $url): array
    {
        // Spreaker embed
        // Format: https://www.spreaker.com/show/show-id or /episode/episode-slug--numeric-id
        if (preg_match('/spreaker\.com\/(show|episode)\/(.+?)(?:--(\d+))?$/', $url, $matches)) {
            $type = $matches[1];
            $id = $matches[3] ?? $matches[2];

            return [
                'id' => $id,
                'embed_url' => "https://widget.spreaker.com/player?{$type}_id={$id}&theme=light&playlist=false&cover_image_url=",
            ];
        }

        return ['url' => $url];
    }

    private function getMixcloudInfo(string $url): array
    {
        // Mixcloud embed
        // Format: https://www.mixcloud.com/username/showname/
        return [
            'url' => $url,
            'embed_url' => 'https://www.mixcloud.com/widget/iframe/?feed=' . urlencode($url),
        ];
    }

    private function getBandcampInfo(string $url): array
    {
        // Bandcamp embed
        // Format: https://artist.bandcamp.com/track/song-name or /album/album-name
        // Bandcamp embeds are complex and require album/track IDs that aren't in the URL
        // We can at least provide a basic embed URL structure
        if (preg_match('/([a-zA-Z0-9-]+)\.bandcamp\.com\/(track|album)\/([a-zA-Z0-9-]+)/', $url, $matches)) {
            $artist = $matches[1];
            $type = $matches[2]; // 'track' or 'album'
            $slug = $matches[3];

            // Basic embed - may need manual adjustment with actual IDs
            return [
                'url' => $url,
                'artist' => $artist,
                'type' => $type,
                'slug' => $slug,
                // Note: Full embed requires track/album ID which isn't in URL
                // Users can get proper embed code from Bandcamp's "Share/Embed" button
            ];
        }

        return ['url' => $url];
    }

    private function getTransistorInfo(string $url): array
    {
        // Transistor embed
        // Format: https://share.transistor.fm/s/episode-id
        if (preg_match('/transistor\.fm\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
            $episodeId = $matches[1];

            return [
                'id' => $episodeId,
                'embed_url' => "https://share.transistor.fm/e/{$episodeId}",
            ];
        }

        return ['url' => $url];
    }

    private function getBuzzsproutInfo(string $url): array
    {
        // Buzzsprout embed
        // Format: https://www.buzzsprout.com/podcast-id/episode-id
        if (preg_match('/buzzsprout\.com\/(\d+)\/(\d+)/', $url, $matches)) {
            $podcastId = $matches[1];
            $episodeId = $matches[2];

            return [
                'id' => $episodeId,
                'embed_url' => "https://www.buzzsprout.com/{$podcastId}/{$episodeId}?client_source=small_player&iframe=true",
            ];
        }

        return ['url' => $url];
    }

    private function getPodbeanInfo(string $url): array
    {
        // Podbean - embed URL refuses connections in iframes
        // Format: https://podcastname.podbean.com/e/episode-name/
        if (preg_match('/([a-zA-Z0-9-]+)\.podbean\.com\/e\/([a-zA-Z0-9-]+)/', $url, $matches)) {
            $episodeName = $matches[2];

            return [
                'id' => $episodeName,
            ];
        }

        return ['url' => $url];
    }

    private function getCaptivateInfo(string $url): array
    {
        // Captivate embed
        // Format: https://player.captivate.fm/episode/episode-id
        if (preg_match('/player\.captivate\.fm\/episode\/([a-zA-Z0-9-]+)/', $url, $matches)) {
            $episodeId = $matches[1];

            return [
                'id' => $episodeId,
                'embed_url' => "https://player.captivate.fm/episode/{$episodeId}",
            ];
        }

        return ['url' => $url];
    }

    private function getCastosInfo(string $url): array
    {
        // Castos embed
        // Format: https://player.castos.com/episode-id
        if (preg_match('/player\.castos\.com\/([a-zA-Z0-9-]+)/', $url, $matches)) {
            $episodeId = $matches[1];

            return [
                'id' => $episodeId,
                'embed_url' => "https://player.castos.com/{$episodeId}",
            ];
        }

        return ['url' => $url];
    }

    private function getAcastInfo(string $url): array
    {
        // Acast embed
        // Format: https://play.acast.com/s/podcast-name/episode-name
        if (preg_match('/play\.acast\.com\/s\/([a-zA-Z0-9-]+)\/([a-zA-Z0-9-]+)/', $url, $matches)) {
            $podcastName = $matches[1];
            $episodeName = $matches[2];

            return [
                'id' => $episodeName,
                'embed_url' => "https://embed.acast.com/{$podcastName}/{$episodeName}",
            ];
        }

        return ['url' => $url];
    }

    private function getMegaphoneInfo(string $url): array
    {
        // Megaphone embed
        // Format: https://player.megaphone.fm/episode-id
        if (preg_match('/player\.megaphone\.fm\/([A-Z0-9]+)/', $url, $matches)) {
            $episodeId = $matches[1];

            return [
                'id' => $episodeId,
                'embed_url' => "https://player.megaphone.fm/{$episodeId}",
            ];
        }

        return ['url' => $url];
    }

    private function getSimplecastInfo(string $url): array
    {
        // Simplecast embed
        // Format: https://player.simplecast.com/episode-id
        if (preg_match('/player\.simplecast\.com\/([a-zA-Z0-9-]+)/', $url, $matches)) {
            $episodeId = $matches[1];

            return [
                'id' => $episodeId,
                'embed_url' => "https://player.simplecast.com/{$episodeId}?dark=false",
            ];
        }

        return ['url' => $url];
    }

    private function getPodigeeInfo(string $url): array
    {
        // Podigee embed
        // Format: https://podcastname.podigee.io/episode-number-episode-name
        if (preg_match('/([a-zA-Z0-9-]+)\.podigee\.io\/([a-zA-Z0-9-]+)/', $url, $matches)) {
            $podcastName = $matches[1];
            $episodeSlug = $matches[2];

            return [
                'id' => $episodeSlug,
                'embed_url' => "https://{$podcastName}.podigee.io/{$episodeSlug}/embed?context=external",
            ];
        }

        return ['url' => $url];
    }

    private function getGooglePodcastsInfo(string $url): array
    {
        // Google Podcasts doesn't have an official embed player
        // We'll return the URL for now
        return [
            'url' => $url,
            // Google Podcasts doesn't provide embeds
        ];
    }

    private function getAudioboomInfo(string $url): array
    {
        // Audioboom - no embed support (only shows "Listen on Audioboom" button)
        // Format: https://audioboom.com/posts/post-id
        if (preg_match('/audioboom\.com\/posts\/(\d+)/', $url, $matches)) {
            $postId = $matches[1];

            return [
                'id' => $postId,
            ];
        }

        return ['url' => $url];
    }

    private function getDeezerInfo(string $url): array
    {
        // Deezer embed
        // Format: https://www.deezer.com/track/track-id or /album/album-id
        if (preg_match('/deezer\.com\/(track|album|playlist)\/(\d+)/', $url, $matches)) {
            $type = $matches[1];
            $id = $matches[2];

            return [
                'id' => $id,
                'embed_url' => "https://widget.deezer.com/widget/dark/{$type}/{$id}",
            ];
        }

        return ['url' => $url];
    }

    private function getSunoInfo(string $url): array
    {
        // Suno AI music embed
        // Format: https://suno.com/song/uuid or https://suno.com/embed/uuid
        // May have query params like ?sh=xxx
        if (preg_match('/suno\.com\/(?:song|embed)\/([a-f0-9-]{36})/', $url, $matches)) {
            $songId = $matches[1];

            return [
                'id' => $songId,
                'embed_url' => "https://suno.com/embed/{$songId}",
            ];
        }

        return ['url' => $url];
    }

    private function getAudibleInfo(string $url): array
    {
        // Audible doesn't provide embeds
        // We return the URL for linking
        return [
            'url' => $url,
            // Audible doesn't provide embed players
        ];
    }

    private function getTwitterInfo(string $url): array
    {
        // Twitter/X embed uses widget.js, no direct iframe
        // Formats:
        // - https://twitter.com/user/status/1234567890
        // - https://x.com/user/status/1234567890
        if (preg_match('/(?:twitter\.com|x\.com)\/[a-zA-Z0-9_]+\/status\/(\d+)/', $url, $matches)) {
            $tweetId = $matches[1];

            return [
                'id' => $tweetId,
                'embed_type' => 'widget',
            ];
        }

        return ['url' => $url];
    }

    private function getInstagramInfo(string $url): array
    {
        // Instagram embed
        // Formats:
        // - https://www.instagram.com/p/ABC123/
        // - https://www.instagram.com/reel/ABC123/
        if (preg_match('/instagram\.com\/(?:p|reel)\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $postId = $matches[1];

            return [
                'id' => $postId,
                'embed_url' => "https://www.instagram.com/p/{$postId}/embed/",
                'embed_type' => 'iframe',
            ];
        }

        return ['url' => $url];
    }

    private function getTikTokInfo(string $url): array
    {
        // TikTok embed
        // Formats:
        // - https://www.tiktok.com/@user/video/1234567890
        // - https://vm.tiktok.com/ABC123/ (short URL)
        if (preg_match('/tiktok\.com\/@[a-zA-Z0-9_.]+\/video\/(\d+)/', $url, $matches)) {
            $videoId = $matches[1];

            return [
                'id' => $videoId,
                'embed_url' => "https://www.tiktok.com/embed/v2/{$videoId}",
                'embed_type' => 'iframe',
            ];
        }

        // Short URL - needs redirect resolution
        if (str_contains($url, 'vm.tiktok.com')) {
            return [
                'url' => $url,
                'embed_type' => 'widget',
                'needs_resolution' => true,
            ];
        }

        return ['url' => $url];
    }

    public function getFormattedProviderName(string $provider): string
    {
        // Format provider names for display
        $formattedNames = [
            'youtube' => 'YouTube',
            'vimeo' => 'Vimeo',
            'soundcloud' => 'SoundCloud',
            'spotify' => 'Spotify',
            'apple_podcasts' => 'Apple Podcasts',
            'simplecast' => 'Simplecast',
            'dailymotion' => 'Dailymotion',
            'facebook' => 'Facebook',
            'ivoox' => 'iVoox',
            'anchor' => 'Anchor',
            'spreaker' => 'Spreaker',
            'transistor' => 'Transistor',
            'buzzsprout' => 'Buzzsprout',
            'podbean' => 'Podbean',
            'captivate' => 'Captivate',
            'castos' => 'Castos',
            'acast' => 'Acast',
            'megaphone' => 'Megaphone',
            'podigee' => 'Podigee',
            'google_podcasts' => 'Google Podcasts',
            'bandcamp' => 'Bandcamp',
            'mixcloud' => 'Mixcloud',
            'audioboom' => 'Audioboom',
            'deezer' => 'Deezer',
            'suno' => 'Suno',
            'audible' => 'Audible',
            'twitter' => 'X (Twitter)',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
        ];

        return $formattedNames[$provider] ?? $provider;
    }
}
