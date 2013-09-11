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
