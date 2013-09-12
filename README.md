VkSDK
=====

Non-official SDK for Vk written in PHP.

Notice
=====

This is not an official SDK and no support will be provided.
I am not affiliated with Vkontakte by any means.

License
=====
This software is released under the MIT license.

Authentication flow
=====
The goal of the authentication flow is to obtain an access token. Once you have the access token, the SDK will keep it in the user's session. If you want to be able to re-use the token, it is up to you to store it somewhere (in your database, for example). You then must restore it in the VkSDK object using the setAccessToken() method. You should also store and restore the user identifier. You can get it by using the getUserId() method, and restore it with the setUserId() method.

The authentication flow is a two-step process:
- Create a VkSDK object with your client id, your client secret and an address on your site to be redirected to after authenticating on vk.com
- Once the client is redirected on the aforementioned address, get the 'code' URL parameter and submit it to the loginWithCode() method.

Congratulations you are now authenticated. You are advised to store somewhere the access token (VkSDK->getAccessToken()) and the user id (VkSDK->getUserId()). Then when using the SDK, you don't need to specify the client secret nor the redirect URI anymore, but you must restore the access token and the user id if you are using a new session.

Example
=====
    <?php
    /**
    * @brief Helper class to use the Vk REST API
    * @author bagia
    * @license MIT
    */
    
    // Assume the URL of the current page is:
    // http://www.example.com/SimpleVkExample
    
    require_once('/Path/To/VkSDK.php');
    
    $sdk = new VkSDK(
        'Put your client ID here', // Client ID
        'Put your client Secret here', // Client Secret
        'http://www.example.com/SimpleVkExample?redirect=1'
    );
    
    if (!$sdk->getAccessToken()) {
        // We are not authenticated
    
        // Let's check if we are in the process
        // of authentication or if it hasn't
        // started yet.
        if (!isset($_GET['code'])) {
            // We are not being redirect yet
            $url = $sdk->getLoginURL('users');
            // Redirect to vk.com
            header('Location: ' . $url);
            return;
        }
    
        // Here we are being redirected
        $code = $_GET['code'];
        $sdk->loginWithCode($code);
    }
    
    // At this stage, if not exception was raised
    // that means we are connected.
    
    $user = $sdk->getUser();
    var_dump($user);
    
    $wall = $sdk->api('wall.get', array(
       'owner_id' => $sdk->getUserId()
    ));
    var_dump($wall);
