<?php

Route::get(config('instagram-feed.auth_callback_route'), 'GNAHotelSolutions\InstagramFeed\AccessTokenController@handleRedirect');