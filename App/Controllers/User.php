<?php

namespace App\Controllers;

use App\Config;
use App\Model\UserRegister;
use App\Models\Articles;
use App\Utility\Hash;
use App\Utility\Session;
use \Core\View;
use Exception;
use http\Env\Request;
use http\Exception\InvalidArgumentException;
use App\Utility\Mail;
use App\Models\User as UserModel;

/**
 * User controller
 */
class User extends \Core\Controller
{

    /**
     * Affiche la page de login
     */
    public function loginAction()
    {
        if(isset($_POST['submit'])){
            $f = $_POST;

            // TODO: Validation

            $this->login($f);

            // Si login OK, redirige vers le compte
            header('Location: /account');
        }

        View::renderTemplate('User/login.html');
    }

    /**
     * Page de création de compte
     */
    public function registerAction()
    {
        if(isset($_POST['submit'])){
            $f = $_POST;

            if($f['password'] !== $f['password-check']){
                // TODO: Gestion d'erreur côté utilisateur
            }

            // validation

            $this->register($f);
            // TODO: Rappeler la fonction de login pour connecter l'utilisateur
        }

        View::renderTemplate('User/register.html');
    }

    /**
     * Affiche la page du compte
     */
    public function accountAction()
    {
        $articles = Articles::getByUser($_SESSION['user']['id']);

        View::renderTemplate('User/account.html', [
            'articles' => $articles
        ]);
    }

    /*
     * Fonction privée pour enregister un utilisateur
     */
    private function register($data)
    {
        try {
            // Generate a salt, which will be applied to the during the password
            // hashing process.
            $salt = Hash::generateSalt(32);

            $userID = \App\Models\User::createUser([
                "email" => $data['email'],
                "username" => $data['username'],
                "password" => Hash::generate($data['password'], $salt),
                "salt" => $salt
            ]);

            return $userID;

        } catch (Exception $ex) {
            // TODO : Set flash if error : utiliser la fonction en dessous
            /* Utility\Flash::danger($ex->getMessage());*/
        }
    }

    private function login($data){
        try {
            if(!isset($data['email'])){
                throw new Exception('TODO');
            }

            $user = \App\Models\User::getByLogin($data['email']);

            if (Hash::generate($data['password'], $user['salt']) !== $user['password']) {
                return false;
            }

            // TODO: Create a remember me cookie if the user has selected the option
            // to remained logged in on the login form.
            // https://github.com/andrewdyer/php-mvc-register-login/blob/development/www/app/Model/UserLogin.php#L86

            // création du cookie remember-me à stocker dans une table
            if(isset($data['remember-me']) && $data['remember-me'] == true){
                $token = bin2hex(random_bytes(16));
                $expiry = time() + (60 * 60 * 24 * 30); // 30 days
                setcookie('remember-me', $user['id'] . ':' . $token, $expiry, '/');
                // \App\Models\User::updateRememberToken($user['id'], $token); ****** création d'une table pour plus tard
            }

            $_SESSION['user'] = array(
                'id' => $user['id'],
                'username' => $user['username'],
            );

            return true;

        } catch (Exception $ex) {
            // TODO : Set flash if error
            /* Utility\Flash::danger($ex->getMessage());*/
        }
    }


    /**
     * Logout: Delete cookie and session. Returns true if everything is okay,
     * otherwise turns false.
     * @access public
     * @return boolean
     * @since 1.0.2
     */
    public function logoutAction() {

        /*
        if (isset($_COOKIE[$cookie])){
            // TODO: Delete the users remember me cookie if one has been stored.
            // https://github.com/andrewdyer/php-mvc-register-login/blob/development/www/app/Model/UserLogin.php#L148
        }*/
        // Destroy all data registered to the session.

        // supression du cookie remember-me stocker dans une table
        if(isset($_COOKIE['remember-me'])){
            list ($user_id, $token) = explode(':', $_COOKIE['remember-me']);
            setcookie('remember-me', '', time() - 3600, '/');
            // \App\Models\User::updateRememberToken($user_id, null); ****** création d'une table pour plus tard
        }

        setcookie('email','',time()-3600);
        setcookie('password','',time()-3600);

        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        header ("Location: /");

        return true;
    }

    /**
     * permet à l'utilisateur de recevoir un nouveau mot de passe par mail
     */
    public function passwordForgottenAction(){
        
        if($_SERVER['REQUEST_METHOD'] == "GET"){
            View::renderTemplate('User/forgotten.html');
        }else{
            $password = UserModel::resetPassword($_POST["email"]);
        Mail::sendMail($_POST["email"], "Votre nouveau mot de passe est ".$password, "Votre nouveau mot de passe !");
        header("location:/login");
        
        }
    }

    /**
     * permet à l'utilisateur de paramétrer un nouveau mot de passe
     */
    public function resetPasswordAction(){
        
        if($_SERVER['REQUEST_METHOD'] == "GET"){
            View::renderTemplate('User/reset.html');
        }else{
            $password = UserModel::resetPasswordByUser($_POST["password"]);
        
            header("location:/");
        
        }
    }

}