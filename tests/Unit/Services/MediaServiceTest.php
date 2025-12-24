<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MediaService;
use App\Services\UrlValidationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class MediaServiceTest extends TestCase
{
    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $urlValidator = new UrlValidationService();
        $this->service = new MediaService($urlValidator);
    }

    public function test_it_detects_youtube_provider(): void
    {
        $provider = $this->service->detectMediaProvider('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertEquals('youtube', $provider);

        $provider = $this->service->detectMediaProvider('https://youtu.be/dQw4w9WgXcQ');
        $this->assertEquals('youtube', $provider);
    }

    public function test_it_detects_vimeo_provider(): void
    {
        $provider = $this->service->detectMediaProvider('https://vimeo.com/1234567');
        $this->assertEquals('vimeo', $provider);
    }

    public function test_it_detects_soundcloud_provider(): void
    {
        $provider = $this->service->detectMediaProvider('https://soundcloud.com/artist/track');
        $this->assertEquals('soundcloud', $provider);
    }

    public function test_it_detects_spotify_podcast_provider(): void
    {
        $provider = $this->service->detectMediaProvider('https://open.spotify.com/show/123456');
        $this->assertEquals('spotify', $provider);

        $provider = $this->service->detectMediaProvider('https://open.spotify.com/episode/123456');
        $this->assertEquals('spotify', $provider);
    }

    public function test_it_returns_correct_media_type(): void
    {
        $type = $this->service->getMediaType('youtube');
        $this->assertEquals('video', $type);

        $type = $this->service->getMediaType('vimeo');
        $this->assertEquals('video', $type);

        $type = $this->service->getMediaType('soundcloud');
        $this->assertEquals('audio', $type);

        $type = $this->service->getMediaType('spotify');
        $this->assertEquals('audio', $type);
    }

    public function test_it_validates_media_url(): void
    {
        $result = $this->service->validateMediaUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertTrue($result['valid']);
        $this->assertEquals('youtube', $result['provider']);
        $this->assertEquals('video', $result['type']);

        $result = $this->service->validateMediaUrl('https://example.com');
        $this->assertFalse($result['valid']);
        $this->assertNull($result['provider']);
    }

    public function test_it_extracts_youtube_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertTrue($info['valid']);
        $this->assertEquals('youtube', $info['provider']);
        $this->assertEquals('video', $info['type']);
        $this->assertEquals('dQw4w9WgXcQ', $info['id']);
        $this->assertEquals('https://www.youtube.com/embed/dQw4w9WgXcQ', $info['embed_url']);
        $this->assertEquals('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $info['thumbnail_url']);
    }

    public function test_it_extracts_vimeo_info(): void
    {
        Http::fake([
            'vimeo.com/api/v2/video/*' => Http::response([
                [
                    'title' => 'Test Video',
                    'description' => 'Test Description',
                    'thumbnail_large' => 'https://example.com/thumbnail.jpg',
                ],
            ], 200),
        ]);

        $info = $this->service->getMediaInfo('https://vimeo.com/123456');

        $this->assertTrue($info['valid']);
        $this->assertEquals('vimeo', $info['provider']);
        $this->assertEquals('video', $info['type']);
        $this->assertEquals('123456', $info['id']);
        $this->assertEquals('https://player.vimeo.com/video/123456', $info['embed_url']);
        $this->assertEquals('Test Video', $info['title']);
        $this->assertEquals('Test Description', $info['description']);
        $this->assertEquals('https://example.com/thumbnail.jpg', $info['thumbnail_url']);
    }

    public function test_it_extracts_soundcloud_info(): void
    {
        $url = 'https://soundcloud.com/artist/track';
        $info = $this->service->getMediaInfo($url);

        $this->assertTrue($info['valid']);
        $this->assertEquals('soundcloud', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals($url, $info['url']);
        // Check that the URL is properly encoded in the embed URL
        $this->assertStringContainsString(urlencode($url), $info['embed_url']);
        $this->assertStringContainsString('w.soundcloud.com/player', $info['embed_url']);
    }

    public function test_it_returns_formatted_provider_name(): void
    {
        $formatted = $this->service->getFormattedProviderName('youtube');
        $this->assertEquals('YouTube', $formatted);

        $formatted = $this->service->getFormattedProviderName('soundcloud');
        $this->assertEquals('SoundCloud', $formatted);
    }

    // Audio Platform Tests

    public function test_it_detects_spotify_provider(): void
    {
        $this->assertEquals('spotify', $this->service->detectMediaProvider('https://open.spotify.com/show/4rOoJ6Egrf8K2IrywzwOMk'));
        $this->assertEquals('spotify', $this->service->detectMediaProvider('https://open.spotify.com/episode/7makk4oTQel546B0PZlDM5'));
    }

    public function test_it_extracts_spotify_info(): void
    {
        $info = $this->service->getMediaInfo('https://open.spotify.com/show/4rOoJ6Egrf8K2IrywzwOMk');

        $this->assertTrue($info['valid']);
        $this->assertEquals('spotify', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('4rOoJ6Egrf8K2IrywzwOMk', $info['id']);
        $this->assertEquals('https://open.spotify.com/embed/show/4rOoJ6Egrf8K2IrywzwOMk', $info['embed_url']);
    }

    public function test_it_detects_spotify_tracks_albums_playlists(): void
    {
        $this->assertEquals('spotify', $this->service->detectMediaProvider('https://open.spotify.com/track/73Sk2zDTBUg0PnqAh98gic'));
        $this->assertEquals('spotify', $this->service->detectMediaProvider('https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy'));
        $this->assertEquals('spotify', $this->service->detectMediaProvider('https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M'));
    }

    public function test_it_extracts_spotify_track_info(): void
    {
        $info = $this->service->getMediaInfo('https://open.spotify.com/track/73Sk2zDTBUg0PnqAh98gic');

        $this->assertTrue($info['valid']);
        $this->assertEquals('spotify', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('73Sk2zDTBUg0PnqAh98gic', $info['id']);
        $this->assertEquals('https://open.spotify.com/embed/track/73Sk2zDTBUg0PnqAh98gic', $info['embed_url']);
    }

    public function test_it_extracts_spotify_track_with_locale_info(): void
    {
        $info = $this->service->getMediaInfo('https://open.spotify.com/intl-es/track/73Sk2zDTBUg0PnqAh98gic');

        $this->assertTrue($info['valid']);
        $this->assertEquals('spotify', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('73Sk2zDTBUg0PnqAh98gic', $info['id']);
        $this->assertEquals('https://open.spotify.com/embed/track/73Sk2zDTBUg0PnqAh98gic', $info['embed_url']);
    }

    public function test_it_detects_ivoox_provider(): void
    {
        $this->assertEquals('ivoox', $this->service->detectMediaProvider('https://www.ivoox.com/en-la-boca-del-lobo-15-11-24-audios-mp3_rf_127948086_1.html'));
        $this->assertEquals('ivoox', $this->service->detectMediaProvider('https://www.ivoox.com/podcast-javascript_sq_f1516337_1.html'));
    }

    public function test_it_extracts_ivoox_episode_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.ivoox.com/en-la-boca-del-lobo-15-11-24-audios-mp3_rf_127948086_1.html');

        $this->assertTrue($info['valid']);
        $this->assertEquals('ivoox', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('127948086', $info['id']);
        $this->assertEquals('https://www.ivoox.com/player_ej_127948086_6_1.html', $info['embed_url']);
    }

    public function test_it_extracts_ivoox_podcast_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.ivoox.com/podcast-javascript_sq_f1516337_1.html');

        $this->assertTrue($info['valid']);
        $this->assertEquals('ivoox', $info['provider']);
        $this->assertEquals('1516337', $info['id']);
        $this->assertEquals('https://www.ivoox.com/player_ek_podcast_1516337_1.html', $info['embed_url']);
    }

    public function test_it_detects_apple_podcasts_provider(): void
    {
        $this->assertEquals('apple_podcasts', $this->service->detectMediaProvider('https://podcasts.apple.com/us/podcast/the-daily/id1200361736'));
    }

    public function test_it_extracts_apple_podcasts_info(): void
    {
        $info = $this->service->getMediaInfo('https://podcasts.apple.com/us/podcast/the-daily/id1200361736');

        $this->assertTrue($info['valid']);
        $this->assertEquals('apple_podcasts', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('1200361736', $info['id']);
        $this->assertEquals('https://embed.podcasts.apple.com/us/podcast/id1200361736', $info['embed_url']);
    }

    public function test_it_detects_anchor_provider(): void
    {
        $this->assertEquals('anchor', $this->service->detectMediaProvider('https://anchor.fm/thenerdstash/episodes/Episode-1-Introduction-e1q8k0j'));
    }

    public function test_it_extracts_anchor_info(): void
    {
        $info = $this->service->getMediaInfo('https://anchor.fm/thenerdstash/episodes/Episode-1-Introduction-e1q8k0j');

        $this->assertTrue($info['valid']);
        $this->assertEquals('anchor', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertStringContainsString('anchor.fm/thenerdstash/embed/episodes/Episode-1-Introduction-e1q8k0j', $info['embed_url']);
    }

    public function test_it_detects_spreaker_provider(): void
    {
        $this->assertEquals('spreaker', $this->service->detectMediaProvider('https://www.spreaker.com/show/stuff-you-should-know'));
    }

    public function test_it_extracts_spreaker_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.spreaker.com/show/stuff-you-should-know');

        $this->assertTrue($info['valid']);
        $this->assertEquals('spreaker', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertStringContainsString('widget.spreaker.com/player', $info['embed_url']);
    }

    public function test_it_extracts_spreaker_episode_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.spreaker.com/episode/tertulia-de-cuesta-se-desvela-la-implicacion-cada-vez-mayor-de-torres-en-la-trama-de-las-mascarillas--68453264');

        $this->assertTrue($info['valid']);
        $this->assertEquals('spreaker', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('68453264', $info['id']);
        $this->assertStringContainsString('episode_id=68453264', $info['embed_url']);
    }

    public function test_it_detects_buzzsprout_provider(): void
    {
        $this->assertEquals('buzzsprout', $this->service->detectMediaProvider('https://www.buzzsprout.com/1121972/9742163'));
    }

    public function test_it_extracts_buzzsprout_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.buzzsprout.com/1121972/9742163');

        $this->assertTrue($info['valid']);
        $this->assertEquals('buzzsprout', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('9742163', $info['id']);
        $this->assertEquals('https://www.buzzsprout.com/1121972/9742163?client_source=small_player&iframe=true', $info['embed_url']);
    }

    public function test_it_detects_podbean_provider(): void
    {
        $this->assertEquals('podbean', $this->service->detectMediaProvider('https://thejobhunterpodcast.podbean.com/e/episode-1-introduction/'));
    }

    public function test_it_extracts_podbean_info(): void
    {
        $info = $this->service->getMediaInfo('https://thejobhunterpodcast.podbean.com/e/episode-1-introduction/');

        $this->assertTrue($info['valid']);
        $this->assertEquals('podbean', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('episode-1-introduction', $info['id']);
        $this->assertArrayNotHasKey('embed_url', $info);
    }

    public function test_it_detects_mixcloud_provider(): void
    {
        $this->assertEquals('mixcloud', $this->service->detectMediaProvider('https://www.mixcloud.com/NTSRadio/khruangbin-30th-november-2015/'));
    }

    public function test_it_extracts_mixcloud_info(): void
    {
        $url = 'https://www.mixcloud.com/NTSRadio/khruangbin-30th-november-2015/';
        $info = $this->service->getMediaInfo($url);

        $this->assertTrue($info['valid']);
        $this->assertEquals('mixcloud', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertStringContainsString('www.mixcloud.com/widget/iframe/', $info['embed_url']);
        $this->assertStringContainsString(urlencode($url), $info['embed_url']);
    }

    public function test_it_detects_deezer_provider(): void
    {
        $this->assertEquals('deezer', $this->service->detectMediaProvider('https://www.deezer.com/track/3135556'));
        $this->assertEquals('deezer', $this->service->detectMediaProvider('https://www.deezer.com/album/302127'));
    }

    public function test_it_extracts_deezer_track_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.deezer.com/track/3135556');

        $this->assertTrue($info['valid']);
        $this->assertEquals('deezer', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('3135556', $info['id']);
        $this->assertEquals('https://widget.deezer.com/widget/dark/track/3135556', $info['embed_url']);
    }

    public function test_it_extracts_deezer_album_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.deezer.com/album/302127');

        $this->assertTrue($info['valid']);
        $this->assertEquals('deezer', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('302127', $info['id']);
        $this->assertEquals('https://widget.deezer.com/widget/dark/album/302127', $info['embed_url']);
    }

    public function test_it_detects_transistor_provider(): void
    {
        $this->assertEquals('transistor', $this->service->detectMediaProvider('https://share.transistor.fm/s/d2faeb96'));
    }

    public function test_it_extracts_transistor_info(): void
    {
        $info = $this->service->getMediaInfo('https://share.transistor.fm/s/d2faeb96');

        $this->assertTrue($info['valid']);
        $this->assertEquals('transistor', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('d2faeb96', $info['id']);
        $this->assertEquals('https://share.transistor.fm/e/d2faeb96', $info['embed_url']);
    }

    public function test_it_detects_captivate_provider(): void
    {
        $this->assertEquals('captivate', $this->service->detectMediaProvider('https://player.captivate.fm/episode/e950e7b2-11b1-4f38-9be1-8b95e0f35cc1'));
    }

    public function test_it_extracts_captivate_info(): void
    {
        $info = $this->service->getMediaInfo('https://player.captivate.fm/episode/e950e7b2-11b1-4f38-9be1-8b95e0f35cc1');

        $this->assertTrue($info['valid']);
        $this->assertEquals('captivate', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('e950e7b2-11b1-4f38-9be1-8b95e0f35cc1', $info['id']);
        $this->assertEquals('https://player.captivate.fm/episode/e950e7b2-11b1-4f38-9be1-8b95e0f35cc1', $info['embed_url']);
    }

    public function test_it_detects_acast_provider(): void
    {
        $this->assertEquals('acast', $this->service->detectMediaProvider('https://play.acast.com/s/the-case-study/e01-meet-your-hosts'));
    }

    public function test_it_extracts_acast_info(): void
    {
        $info = $this->service->getMediaInfo('https://play.acast.com/s/the-case-study/e01-meet-your-hosts');

        $this->assertTrue($info['valid']);
        $this->assertEquals('acast', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('https://embed.acast.com/the-case-study/e01-meet-your-hosts', $info['embed_url']);
    }

    public function test_it_detects_megaphone_provider(): void
    {
        $this->assertEquals('megaphone', $this->service->detectMediaProvider('https://player.megaphone.fm/VMP5931568456'));
    }

    public function test_it_extracts_megaphone_info(): void
    {
        $info = $this->service->getMediaInfo('https://player.megaphone.fm/VMP5931568456');

        $this->assertTrue($info['valid']);
        $this->assertEquals('megaphone', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('VMP5931568456', $info['id']);
        $this->assertEquals('https://player.megaphone.fm/VMP5931568456', $info['embed_url']);
    }

    public function test_it_detects_simplecast_provider(): void
    {
        $this->assertEquals('simplecast', $this->service->detectMediaProvider('https://player.simplecast.com/86b8aa1e-7021-4c19-a2cc-c788adc8f096'));
    }

    public function test_it_extracts_simplecast_info(): void
    {
        $info = $this->service->getMediaInfo('https://player.simplecast.com/86b8aa1e-7021-4c19-a2cc-c788adc8f096');

        $this->assertTrue($info['valid']);
        $this->assertEquals('simplecast', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('86b8aa1e-7021-4c19-a2cc-c788adc8f096', $info['id']);
        $this->assertEquals('https://player.simplecast.com/86b8aa1e-7021-4c19-a2cc-c788adc8f096?dark=false', $info['embed_url']);
    }

    public function test_it_detects_podigee_provider(): void
    {
        $this->assertEquals('podigee', $this->service->detectMediaProvider('https://working-in-audio.podigee.io/21-new-episode'));
    }

    public function test_it_extracts_podigee_info(): void
    {
        $info = $this->service->getMediaInfo('https://working-in-audio.podigee.io/21-new-episode');

        $this->assertTrue($info['valid']);
        $this->assertEquals('podigee', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertStringContainsString('working-in-audio.podigee.io/21-new-episode/embed', $info['embed_url']);
    }

    public function test_it_detects_audioboom_provider(): void
    {
        $this->assertEquals('audioboom', $this->service->detectMediaProvider('https://audioboom.com/posts/8419876-welcome-to-the-world-of-podcasting'));
    }

    public function test_it_extracts_audioboom_info(): void
    {
        $info = $this->service->getMediaInfo('https://audioboom.com/posts/8419876-welcome-to-the-world-of-podcasting');

        $this->assertTrue($info['valid']);
        $this->assertEquals('audioboom', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('8419876', $info['id']);
        $this->assertArrayNotHasKey('embed_url', $info);
    }

    // Social Media Platform Tests

    public function test_it_detects_twitter_provider(): void
    {
        $this->assertEquals('twitter', $this->service->detectMediaProvider('https://twitter.com/elonmusk/status/1234567890123456789'));
        $this->assertEquals('twitter', $this->service->detectMediaProvider('https://x.com/user/status/1234567890'));
    }

    public function test_it_extracts_twitter_info(): void
    {
        $info = $this->service->getMediaInfo('https://twitter.com/elonmusk/status/1234567890123456789');

        $this->assertTrue($info['valid']);
        $this->assertEquals('twitter', $info['provider']);
        $this->assertEquals('video', $info['type']);
        $this->assertEquals('1234567890123456789', $info['id']);
        $this->assertEquals('widget', $info['embed_type']);
    }

    public function test_it_extracts_x_dot_com_info(): void
    {
        $info = $this->service->getMediaInfo('https://x.com/user/status/9876543210');

        $this->assertTrue($info['valid']);
        $this->assertEquals('twitter', $info['provider']);
        $this->assertEquals('9876543210', $info['id']);
    }

    public function test_it_detects_instagram_provider(): void
    {
        $this->assertEquals('instagram', $this->service->detectMediaProvider('https://www.instagram.com/p/CxYzAbCdEfG/'));
        $this->assertEquals('instagram', $this->service->detectMediaProvider('https://instagram.com/reel/ABC123xyz/'));
    }

    public function test_it_extracts_instagram_post_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.instagram.com/p/CxYzAbCdEfG/');

        $this->assertTrue($info['valid']);
        $this->assertEquals('instagram', $info['provider']);
        $this->assertEquals('video', $info['type']);
        $this->assertEquals('CxYzAbCdEfG', $info['id']);
        $this->assertEquals('https://www.instagram.com/p/CxYzAbCdEfG/embed/', $info['embed_url']);
        $this->assertEquals('iframe', $info['embed_type']);
    }

    public function test_it_extracts_instagram_reel_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.instagram.com/reel/ABC123xyz/');

        $this->assertTrue($info['valid']);
        $this->assertEquals('instagram', $info['provider']);
        $this->assertEquals('ABC123xyz', $info['id']);
        $this->assertEquals('https://www.instagram.com/p/ABC123xyz/embed/', $info['embed_url']);
    }

    public function test_it_detects_tiktok_provider(): void
    {
        $this->assertEquals('tiktok', $this->service->detectMediaProvider('https://www.tiktok.com/@username/video/7123456789012345678'));
    }

    public function test_it_extracts_tiktok_info(): void
    {
        $info = $this->service->getMediaInfo('https://www.tiktok.com/@username/video/7123456789012345678');

        $this->assertTrue($info['valid']);
        $this->assertEquals('tiktok', $info['provider']);
        $this->assertEquals('video', $info['type']);
        $this->assertEquals('7123456789012345678', $info['id']);
        $this->assertEquals('https://www.tiktok.com/embed/v2/7123456789012345678', $info['embed_url']);
        $this->assertEquals('iframe', $info['embed_type']);
    }

    public function test_it_handles_tiktok_short_url(): void
    {
        $info = $this->service->getMediaInfo('https://vm.tiktok.com/ABC123/');

        $this->assertTrue($info['valid']);
        $this->assertEquals('tiktok', $info['provider']);
        $this->assertEquals('widget', $info['embed_type']);
        $this->assertTrue($info['needs_resolution']);
    }

    public function test_it_returns_correct_media_type_for_social_platforms(): void
    {
        $this->assertEquals('video', $this->service->getMediaType('twitter'));
        $this->assertEquals('video', $this->service->getMediaType('instagram'));
        $this->assertEquals('video', $this->service->getMediaType('tiktok'));
    }

    public function test_it_returns_formatted_social_provider_names(): void
    {
        $this->assertEquals('X (Twitter)', $this->service->getFormattedProviderName('twitter'));
        $this->assertEquals('Instagram', $this->service->getFormattedProviderName('instagram'));
        $this->assertEquals('TikTok', $this->service->getFormattedProviderName('tiktok'));
    }

    // Suno AI Music Tests

    public function test_it_detects_suno_provider(): void
    {
        $this->assertEquals('suno', $this->service->detectMediaProvider('https://suno.com/song/dcfcbf35-32ba-43ff-8dc8-6315f98b1d9c'));
        $this->assertEquals('suno', $this->service->detectMediaProvider('https://suno.com/embed/dcfcbf35-32ba-43ff-8dc8-6315f98b1d9c'));
    }

    public function test_it_extracts_suno_info(): void
    {
        $info = $this->service->getMediaInfo('https://suno.com/song/dcfcbf35-32ba-43ff-8dc8-6315f98b1d9c');

        $this->assertTrue($info['valid']);
        $this->assertEquals('suno', $info['provider']);
        $this->assertEquals('audio', $info['type']);
        $this->assertEquals('dcfcbf35-32ba-43ff-8dc8-6315f98b1d9c', $info['id']);
        $this->assertEquals('https://suno.com/embed/dcfcbf35-32ba-43ff-8dc8-6315f98b1d9c', $info['embed_url']);
    }

    public function test_it_extracts_suno_info_with_query_params(): void
    {
        $info = $this->service->getMediaInfo('https://suno.com/song/dcfcbf35-32ba-43ff-8dc8-6315f98b1d9c?sh=dBxkWF1Apc3rhIZ1');

        $this->assertTrue($info['valid']);
        $this->assertEquals('suno', $info['provider']);
        $this->assertEquals('dcfcbf35-32ba-43ff-8dc8-6315f98b1d9c', $info['id']);
        $this->assertEquals('https://suno.com/embed/dcfcbf35-32ba-43ff-8dc8-6315f98b1d9c', $info['embed_url']);
    }

    public function test_it_returns_suno_as_audio_type(): void
    {
        $this->assertEquals('audio', $this->service->getMediaType('suno'));
    }

    public function test_it_returns_formatted_suno_name(): void
    {
        $this->assertEquals('Suno', $this->service->getFormattedProviderName('suno'));
    }
}
