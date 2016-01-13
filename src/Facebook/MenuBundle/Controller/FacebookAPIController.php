<?php

namespace Facebook\MenuBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Facebook\Facebook;

/**
 * Controleur gérant les appels au GraphAPI de facebook
 */
class FacebookAPIController extends Controller {

    const fb_appId = "994872597252810"; //API_ID
    const fb_secret = "953b1e6530e855a3b727bf5a1ad677d2"; //SECRET

    /**
     * Renvoi vrai si l'utilisateur est loggé sur facebook, faux sinon(présence d'un access token ou non)
     * 
     * @return boolean
     */
    public function isLoggedIn(){
        return isset($_SESSION["facebook_access_token"]) && !empty($_SESSION["facebook_access_token"]);
    }
    
    /**
     * Renvoi le login permettant de se connecter à facebook
     * 
     * @return string
     */
    public function getLoginUrl() {
        $fb_params = [
            "app_id" => self::fb_appId,
            "app_secret" => self::fb_secret
        ];
        $fb = new Facebook($fb_params);
        
        $helper = $fb->getRedirectLoginHelper();
        $permissions = ['email', 'user_likes'];
        $loginUrl = $helper->getLoginUrl('http://'.$_SERVER['SERVER_NAME'].'/Symfony/web/app_dev.php/callbackFb', $permissions);
        return $loginUrl;
    }

    /**
     * Methode de callback appelé par facebook après le login
     * 
     * @return object
     */
    public function callbackFbAction() {
        if(!session_id()){
            session_start();
        }
        $fb = new Facebook([
            "app_id" => self::fb_appId,
            "app_secret" => self::fb_secret,
            'default_graph_version' => 'v2.4'
        ]);

        $helper = $fb->getRedirectLoginHelper();

        //Tentative de récupération de l'access token
        try {
            $accessToken = $helper->getAccessToken();
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
        }

        //Si l'access token a été récupéré, on le stocke en session
        if (isset($accessToken)) {
            $_SESSION['facebook_access_token'] = (string) $accessToken;
        } elseif ($helper->getError()) {
            print_r($helper->getError());
        }
        
        return $this->redirect($this->generateUrl("facebook_menu_homepage"));
    }

    
    
    /**
     * Renvoi le menu de l'ilot regal(sans les posts non pertinents
     * 
     * @return array
     */
    public function getMenuLilotRegal(){
        $fb_params = [
            "app_id" => self::fb_appId,
            "app_secret" => self::fb_secret
        ];
        $fb = new Facebook($fb_params);

        $accessToken = $_SESSION["facebook_access_token"];  //Récupération de l'access token
        
        $rq = $fb->get('/lilotregal/posts?fields=message,picture,full_picture', $accessToken); //Requete 
        $array = json_decode($rq->getBody()); //Conversion json -> php
        
        //On trie les news contenant un menu du jour
        foreach($array->data as $key => $post){
            if (strpos($post->message,'Menu du') === false) {
                unset($array->data[$key]);
            }
        }
        return $array->data;
    }
    
    /**
     * Renvoi le menu du régal du circuit(sans les posts non pertinents)
     * 
     * @return array
     */
    public function getMenuRegalDuCircuit(){
        $fb_params = [
            "app_id" => self::fb_appId,
            "app_secret" => self::fb_secret
        ];
        $fb = new Facebook($fb_params);
        
        $accessToken = $_SESSION["facebook_access_token"];  //Récupération de l'access token
        
        $rq = $fb->get('/649823778452363/posts?fields=message,picture,full_picture', $accessToken);  //Requete 
        $array = json_decode($rq->getBody());   //Conversion json -> php
        
        foreach($array->data as $key => $post){
            if (strpos($post->message,'Repas du') === false) {
                unset($array->data[$key]);
            }
        }
        return $array->data;
    }
}
