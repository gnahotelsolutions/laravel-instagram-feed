<?php


namespace GNAHotelSolutions\InstagramFeed\Commands;


use GNAHotelSolutions\InstagramFeed\AccessToken;
use GNAHotelSolutions\InstagramFeed\Instagram;
use GNAHotelSolutions\InstagramFeed\Profile;
use Illuminate\Console\Command;

class RefreshTokens extends Command
{
    protected $signature = 'instagram-feed:refresh-tokens {username}';

    protected $description = 'Refresh long lived tokens so they do not expire';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $profile = Profile::where('username', $this->argument('username'))->get()
            ->filter->hasInstagramAccess()
            ->first();

        if (! $profile) {
            $this->warn('This profile does not exist or access token is expired');
            return;
        }

        $profile->refreshToken();
    }
}