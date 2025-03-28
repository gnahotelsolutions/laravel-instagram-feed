<?php


namespace GNAHotelSolutions\InstagramFeed;

use GNAHotelSolutions\InstagramFeed\Exceptions\BadTokenException;
use GNAHotelSolutions\InstagramFeed\Exceptions\HttpException;
use Illuminate\Support\Facades\Config;

class Instagram
{
    const REQUEST_ACCESS_TOKEN_URL = "https://api.instagram.com/oauth/access_token";
    const GRAPH_USER_INFO_FORMAT = "https://graph.instagram.com/v22.0/me?fields=id,media_count,username&access_token=%s";
    const EXCHANGE_TOKEN_FORMAT = "https://graph.instagram.com/access_token?grant_type=ig_exchange_token&client_secret=%s&access_token=%s";
    const REFRESH_TOKEN_FORMAT = "https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=%s";
    const MEDIA_URL_FORMAT = "https://graph.instagram.com/%s/media?fields=%s&limit=%s&access_token=%s";
    const MEDIA_FIELDS = "caption,id,media_type,media_url,thumbnail_url,permalink,children{media_type,media_url},timestamp";


    private $client_id;
    private $client_secret;
    private $redirect_uri;

    public function __construct($config)
    {
        $this->client_id = $config["client_id"];
        $this->client_secret = $config["client_secret"];
        $this->redirect_uri = $config["auth_callback_route"];
    }

    public function authUrlForProfile($profile)
    {
        $client_id = $this->client_id;
        $redirect = $this->redirectUriForProfile($profile->id);
        $scopes = ['instagram_business_basic'];
 
        $parameters = [
            'client_id' => $client_id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => urlencode(implode(',', $scopes)),
            'state' => $profile->identity_token,
        ];

        return 'https://www.instagram.com/oauth/authorize/?' . http_build_query($parameters);
    }

    private function redirectUriForProfile($profile_id)
    {
        $base = Config::get('instagram-feed.base_url') ?: Config::get('app.url');
        $base = rtrim($base, '/');

        return "{$base}/{$this->redirect_uri}";
    }

    public function requestTokenForProfile($profile, $auth_request)
    {
        return SimpleClient::post(static::REQUEST_ACCESS_TOKEN_URL, [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUriForProfile($profile->id),
            'code' => $auth_request->get('code')
        ]);
    }

    public function fetchUserDetails($access_token)
    {
        $url = sprintf(self::GRAPH_USER_INFO_FORMAT, $access_token['access_token'] ?? $access_token['access_code']);
        return SimpleClient::get($url);
    }

    public function exchangeToken($short_token)
    {
        $url = sprintf(self::EXCHANGE_TOKEN_FORMAT, $this->client_secret, $short_token['access_token']);

        return SimpleClient::get($url);
    }

    public function refreshToken($token)
    {
        $url = sprintf(self::REFRESH_TOKEN_FORMAT, $token);
        return SimpleClient::get($url);
    }

    /**
     * @param  AccessToken  $token
     * @param  int  $limit
     * @return array
     * @throws BadTokenException
     */
    public function fetchMedia(AccessToken $token, $limit = 20)
    {
        $url = sprintf(
            self::MEDIA_URL_FORMAT,
            $token->user_id,
            urlencode(self::MEDIA_FIELDS),
            $this->getPageSize($limit),
            $token->access_code
        );

        $response = $this->fetchResponseData($url);
        $collection = collect($response['data'])->reject(function ($media) {
            return $this->ignoreVideo($media);
        });

        while ($this->shouldFetchNextPage($response, $collection->count(), $limit)) {
            $response = $this->fetchResponseData($response['paging']['next']);
            $collection = $collection->merge($response['data'])
                ->reject(function ($media) {
                    return $this->ignoreVideo($media);
                });
        }

        return $collection
            ->map(function ($media) {
                return MediaParser::parseItem($media, Config::get('instagram-feed.ignore_video', false));
            })
            ->reject(function ($media) {
                return is_null($media);
            })
            ->sortByDesc('timestamp')
            ->take($limit ?? $collection->count())
            ->values()
            ->all();
    }

    private function getPageSize($limit)
    {
        return min($limit, 100);
    }

    /**
     * @param $url
     * @return mixed
     * @throws BadTokenException
     */
    private function fetchResponseData($url)
    {
        try {
            return $response = SimpleClient::get($url);
        } catch (HttpException $e) {
            $response = $e->getResponse();
            $error_type = $response['meta']['error_type'] ?? 'unknown';
            if ($error_type === 'OAuthAccessTokenException') {
                throw new BadTokenException('The token is invalid');
            } else {
                throw $e;
            }
        }
    }

    public function ignoreVideo($media)
    {
        if (Config::get('instagram-feed.ignore_video', false) && ($media['media_type'] == 'VIDEO')) {
            return $media['media_type'] == 'VIDEO';
        }
        return false;
    }

    private function shouldFetchNextPage($previous_response, $current_count, $limit)
    {
        $max = $limit ?? 1000;
        return ($previous_response['paging']['next'] ?? false) && ($current_count <= $max);
    }
}
