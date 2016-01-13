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
    
    private $weekdays = [
        1 => "Lundi",
        2 => "Mardi",
        3 => "Mercredi",
        4 => "Jeudi",
        5 => "Vendredi",
        6 => "Samedi",
        7 => "Dimanche"
    ];

    private $months = [
        1 => "Janvier",
        2 => "Fevrier",
        3 => "Mars",
        4 => "Avril",
        5 => "Mai",
        6 => "Juin",
        7 => "Juillet",
        8 => "Aout",
        9 => "Septembre",
        10 => "Octobre",
        11 => "Novembre",
        12 => "Decembre"
    ];
    
    private $keywords = ["Poulet à la crème", "Rougaille Saucisse", "Rôti porc", "Civet cerf", "Canard aux olives"];

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
        $feed = json_decode($rq->getBody()); //Conversion json -> php
        
        //On trie les news contenant un menu du jour
        $posts_menu_array = array();
        foreach($feed->data as $key => $post){
            if (strpos($post->message,'Menu du') !== false) {
                $exploded_message = explode(":", $post->message, 2);    //On sépare le menu du jour et le corps du post
                $title = $exploded_message[0];
                $message = $exploded_message[1];
                
                $message = nl2br($message); //Conversion des \n en <br/>
                
                //Date du jour au format mercredi 13 janvier sans les accents
                $date_du_jour = strtolower($this->weekdays[date("N")]. " " . date("j") . " " . $this->months[date("n")]);
                $date_du_jour = $this->wd_remove_accents($date_du_jour);    
                
                //Date du menu
                $exploded_title = explode("Menu du", $title);   
                $date_menu = trim($this->wd_remove_accents($exploded_title[1]));
                
                //Présence des mots clés
                $keyword_found = false;
                $plats_favoris = array();
                foreach($this->keywords as $keyword){
                    $a = $this->wd_remove_accents(strtolower($message));
                    if(strpos($a,$this->wd_remove_accents(strtolower($keyword))) !== false){
                        $keyword_found = true;
                        array_push($plats_favoris, $keyword);
                    }
                }
                
                $post_menu = [
                    "title" => trim(strstr($title, 'Menu')), //strstr sert à enlever tout ce qui se trouve avant le pattern "Menu"
                    "message" => preg_replace('/<br \/>/', '', $message, 1), //On remplacer le premier <br/> pour une meilleure lisibilté
                    "picture" => $post->picture,
                    "full_picture" => $post->full_picture,
                    "menu_du_jour" => ($date_menu == $date_du_jour),
                    "keyword_found" => $keyword_found,
                    "plats_favoris" => $plats_favoris
                ];
                array_push($posts_menu_array, $post_menu);
            }
            if(count($posts_menu_array) >= 3){
                break;
            }
        }

        return $posts_menu_array;
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
        $feed = json_decode($rq->getBody());   //Conversion json -> php

        //On trie les news contenant un menu du jour
        $posts_menu_array = array();
        foreach($feed->data as $key => $post){
            if (strpos($post->message,'Repas du') !== false) {
                $exploded_message = explode("\n", $post->message, 2);    //On sépare le menu du jour et le corps du post
                $title = $exploded_message[0];
                $message = $exploded_message[1];
                
                $message = nl2br($message); //Conversion des \n en <br/>
                
                $date_du_jour = strtolower($this->weekdays[date("N")]. " " . date("j") . " " . $this->months[date("n")]);
                $date_du_jour = $this->wd_remove_accents($date_du_jour);
                
                $exploded_title = explode("Repas du jour", $title);
                $date_menu = trim($this->wd_remove_accents($exploded_title[1]));
                
                //Présence des mots clés
                $keyword_found = false;
                $plats_favoris = array();
                foreach($this->keywords as $keyword){
                    $a = $this->wd_remove_accents(strtolower($message));
                    if(strpos($a,$this->wd_remove_accents(strtolower($keyword))) !== false){
                        $keyword_found = true;
                        array_push($plats_favoris, $keyword);
                    }
                }
                
                $post_menu = [
                    "title" => trim(strstr($title, 'Repas')), //strstr sert à enlever tout ce qui se trouve avant le pattern "Repas"
                    "message" => preg_replace('/<br \/>/', '', $message, 1), //On remplacer le premier <br/> pour une meilleure lisibilté
                    "picture" => $post->picture,
                    "full_picture" => $post->full_picture,
                    "menu_du_jour" => ($date_menu == $date_du_jour),
                    "keyword_found" => $keyword_found,
                    "plats_favoris" => $plats_favoris
                ];
                array_push($posts_menu_array, $post_menu);
            }
            if(count($posts_menu_array) >= 3){
                break;
            }
        }
        return $posts_menu_array;
    }
    
    function wd_remove_accents($str, $charset = 'utf-8') {
        $str = htmlentities($str, ENT_NOQUOTES, $charset);

        $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'
        $str = preg_replace('#&[^;]+;#', '', $str); // supprime les autres caractères

        return $str;
    }

}
