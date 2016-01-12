<?php

namespace Facebook\MenuBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Facebook\MenuBundle\Controller\FacebookAPIController;

/**
 * Controleur par défaut
 * 
 */
class DefaultController extends Controller {

    const fb_appId = "994872597252810"; //API_ID
    const fb_secret = "953b1e6530e855a3b727bf5a1ad677d2"; //SECRET
    
    public function indexAction()
    {
        $facebook = new FacebookAPIController();    //Creation du controleur FacebookAPI
        $isLoggedIn = $facebook->isLoggedIn();  //On verifie si on possède déja un access token
        
        //Si un access token existe, on recupère le menu, sinon on demande a l'utilisateur de se connecter
        if($isLoggedIn){
            $menu_ilot_regal = $facebook->getMenuLilotRegal();  //Renvoi le menu de l'ilot régal
            $menu_regal_circuit = $facebook->getMenuRegalDuCircuit();   //Renvoi le menu du régal du circuit
            
            //Paramètres à passer à la vue
            $params = [
                "Title" => "Liste des menus",
                "menu_ilot" => $menu_ilot_regal,
                "menu_regal" => $menu_regal_circuit
            ];
            return $this->render('FacebookMenuBundle:Default:menu.html.twig', $params); //On appelle la vue affichant le menu
        }else{
            $loginUrl = $facebook->getLoginUrl(); //On récupère l'URL de login facebook
            $params = [
                "Title" => "Connexion à Facebook",
                "loginUrl" => $loginUrl
            ];
        return $this->render('FacebookMenuBundle:Default:loginfacebook.html.twig', $params);    //On appelle la vue de login
        }   
    }
    
    /**
     * Methode de test : Permet d'effacer les données de la session
     * 
     */
    public function killSession(){
        unset($_SESSION["facebook_access_token"]);
        exit;
    }
}
