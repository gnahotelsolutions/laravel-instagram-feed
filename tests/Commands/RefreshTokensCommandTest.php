<?php


namespace GNAHotelSolutions\InstagramFeed\Tests\Commands;


use GNAHotelSolutions\InstagramFeed\AccessToken;
use GNAHotelSolutions\InstagramFeed\Instagram;
use GNAHotelSolutions\InstagramFeed\Profile;
use GNAHotelSolutions\InstagramFeed\SimpleClient;
use GNAHotelSolutions\InstagramFeed\Tests\FakesInstagramCalls;
use GNAHotelSolutions\InstagramFeed\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class RefreshTokensCommandTest extends TestCase
{
    use FakesInstagramCalls;
    /**
     * @test
     */
    public function tokens_get_refreshed()
    {
        $profileA = Profile::create(['username' => 'testA']);
        $profileB = Profile::create(['username' => 'testB']);
        $tokenA = $this->makeToken($profileA);
        $tokenB = $this->makeToken($profileB);

        $this->assertDatabaseHas('instagram_feed_tokens', [
            'profile_id' => 1,
            'access_code' => 'VALID_LONG_LIVED_TOKEN',
        ]);

        $this->assertDatabaseHas('instagram_feed_tokens', [
            'profile_id' => 2,
            'access_code' => 'VALID_LONG_LIVED_TOKEN',
        ]);

        Http::fake([
            $this->makeRefreshUrl($tokenA) => $this->refreshedLongLivedToken(),
            $this->makeRefreshUrl($tokenB) => $this->refreshedLongLivedToken(),
        ]);

        Artisan::call('instagram-feed:refresh-tokens');

        $this->assertDatabaseHas('instagram_feed_tokens', [
            'profile_id' => 1,
            'access_code' => 'REFRESHED_LONG_LIVED_TOKEN',
        ]);

        $this->assertDatabaseHas('instagram_feed_tokens', [
            'profile_id' => 2,
            'access_code' => 'REFRESHED_LONG_LIVED_TOKEN',
        ]);
    }

    private function makeToken($profile)
    {
        return AccessToken::create([
            'profile_id'           => $profile->id,
            'access_code'          => 'VALID_LONG_LIVED_TOKEN',
            'username'             => 'instagram_test_username',
            'user_id'              => 'FAKE_USER_ID',
            'user_fullname'        => 'test user real name',
            'user_profile_picture' => 'https://test.test/test_pic.jpg',
        ]);
    }

    private function makeRefreshUrl($token)
    {
        return sprintf(Instagram::REFRESH_TOKEN_FORMAT, $token->access_code);
    }
}