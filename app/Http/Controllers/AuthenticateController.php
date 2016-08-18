<?php

namespace App\Http\Controllers;

use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTFactory;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\User;

class AuthenticateController extends Controller
{
    public function authenticate(Request $request)
    {
        $accessToken = $request->input('access_token');
        try {
            $fb = new Facebook(\Config::get('facebook'));
            $response = $fb->get('/me?fields=name,id', $accessToken);
            $facebookUser = $response->getGraphUser();
            $currentUser = null;
            try {
                $currentUser = User::where('fb_id', $facebookUser['id'])->firstOrFail();
            } catch (ModelNotFoundException $e) {
                $currentUser = new User;
                $currentUser->fb_id = $facebookUser['id'];
                $currentUser->name = $facebookUser['name'];
                $currentUser->avatar_url = 'https://graph.facebook.com/' . $facebookUser['id'] . '/picture?type=large';
                $currentUser->save();
            }
            $claims = ['token' => $accessToken, 'user' => $currentUser];
            $payload = JWTFactory::make($claims);
            return JWTAuth::encode($payload);
        } catch (JWTException $e) {
            // something went wrong whilst attempting to encode the token
            return response()->json(['error' => 'Error creating the token'], 500);
        } catch(FacebookResponseException $e) {
            return response()->json(['error' => 'Graph returned an error'], 500);
        } catch(FacebookSDKException $e) {
            return response()->json(['error' => 'Facebook SDK returned an error'], 500);
        }
    }
}
