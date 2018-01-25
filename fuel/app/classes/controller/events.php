<?php
//require_once '../../../vendor/autoload.php';
use Firebase\JWT\JWT;

class Controller_Events extends Controller_Rest
{
    private $key = 'my_secret_key';
    protected $format = 'json';

    function post_create()
    {
        // falta token
        if (!isset(apache_request_headers()['Authorization']))
        {
            return $this->createResponse(400, 'Token no encontrado');
        }
        $jwt = apache_request_headers()['Authorization'];
        // valdiar token
        try {

            $this->validateToken($jwt);
        } catch (Exception $e) {

            return $this->createResponse(400, 'Error de autentificacion');
        }
        // validar rol de admin
        $user = $this->decodeToken();

        // --------------------------------------------------------
        /* todo los usuarios pueden crear eventos, en un futuro no
        if ($user->data->id_rol != 1) {
            return $this->createResponse(401, 'No autorizado');
        }*/
        // falta parametro email
        if (empty($_POST['title']) || empty($_POST['description']) || empty($_POST['id_group'])|| empty($_POST['id_type'])) 
            {

              return $this->createResponse(400, 'Falta parámetros obligatorios (title, description,[id_group]), id_type ');
            }

        $title = $_POST['title'];
        $description = $_POST['description'];
        $id_group = $_POST['id_group'];
        $id_type = $_POST['id_type'];

        try {
            $eventDB = new Model_Events();
            $eventDB->title = $title;
            $eventDB->description = $description;

            $typeDB = Model_Types::find($id_type);
            if ($typeDB==null) {
                return $this->createResponse(400, 'No existe el tipo de evento mandado por parametro');
            }

            $eventDB->id_type = $id_type;

            if (!empty($_POST['image'])) {
            	$eventDB->image = $_POST['image'];
            }
            if (!empty($_POST['lat'])) {
            	$eventDB->lat = $_POST['lat'];
            }
            if (!empty($_POST['lon'])) {
            	$eventDB->lon = $_POST['lon'];
            }
            if (!empty($_POST['url'])) {
                $eventDB->url = $_POST['url'];
            }

            $eventDB->id_user = $user->data->id;

            $eventDB->save();
            foreach ($id_group as $key => $group) {

                $groupDB = Model_Groups::find($group);
                if ($groupDB!= null) {
                    $asignDB = new Model_Asign();
                    $asignDB->id_event = $eventDB->id;
                    $asignDB->id_group = $group;
                    
                    $asignDB->save();
                    return $this->createResponse(200, $asignDB);
                }
                
            }
            return $this->createResponse(200, 'Evento creado');

        } catch (Exception $e) 
        {
            return $this->createResponse(500, $e->getMessage());
        }
    }

    function get_events()
    {

        // falta token
        if (!isset(apache_request_headers()['Authorization']))
        {
            return $this->createResponse(400, 'Token no encontrado');
        }
        $jwt = apache_request_headers()['Authorization'];
        // valdiar token
        try {

            $this->validateToken($jwt);
        } catch (Exception $e) {

            return $this->createResponse(400, 'Error de autentificacion');
        }

        $user = $this->decodeToken();
        $id= $user->data->id;
        if (empty($_GET['type'])) 
        {
          return $this->createResponse(400, 'Falta parámetros obligatorios (type, 0 -> todos, 1-> eventos, 2-> ofertas trabajo, 3 -> notificaciones, 4 -> noticias) ');
        }

        $type = $_GET['type'];
        try {
            
            if ($type == 0 ) {
                $query = \DB::query('SELECT events.* FROM belong 
                                        JOIN users ON users.id = '.$id.' 
                                        JOIN groups ON groups.id = belong.id_group 
                                        JOIN asign ON asign.id_group = belong.id_group 
                                        JOIN events ON events.id = asign.id_event'
                                        )->as_assoc()->execute();
                

            }else{
                $typeDB = Model_Types::find($type);
                if ($typeDB == null) {
                    return $this->createResponse(400, 'Parametro type no valido');
                }
                $query = \DB::query('SELECT events.* FROM belong 
                                        JOIN users ON users.id = '.$id.' 
                                        JOIN groups ON groups.id = belong.id_group 
                                        JOIN asign ON asign.id_group = belong.id_group 
                                        JOIN events ON events.id = asign.id_event
                                        WHERE events.id_type='.$type.''
                                        )->as_assoc()->execute();

            }
            if (count($query) == 0) {
                return $this->createResponse(400, 'Listado de eventos vacio');
            }

            return $this->createResponse(200, 'Listado de eventos', $query);

        } catch (Exception $e) 
        {
            return $this->createResponse(500, $e->getMessage());
        }
    }

    function get_event()
    {

        // falta token
        if (!isset(apache_request_headers()['Authorization']))
        {
            return $this->createResponse(400, 'Token no encontrado');
        }
        $jwt = apache_request_headers()['Authorization'];
        // valdiar token
        try {

            $this->validateToken($jwt);
        } catch (Exception $e) {

            return $this->createResponse(400, 'Error de autentificacion');
        }

        $user = $this->decodeToken();

        if (empty($_GET['id'])) 
        {
          return $this->createResponse(400, 'Falta parámetros obligatorios (id) ');
        }

        $id = $_GET['id'];
   
        try {
            
            $event = Model_Events::find($id); 
            if ($event == null) {
                return $this->createResponse(400, 'No existe el evento');

            }

            // TODO sacar los 3 comentarios y devolverlos --------------------------------------------------------------------------
            $commentsBD = Model_Comments::find('all', array(
                'where' => array(
                array('id_event' ,$id),
                )
            ));
            foreach ($commentsBD as $key => $comment) {
                $userBD = Model_Users::find($comment->id_user);
                $comment['username']= $userBD->username;
            }

            // dejar solo 3 ultimos comentarios
            for ($i=0; $i < count($commentsBD)-1; $i++) { 
                unset($commentsBD[$i]);
            }
            return $this->createResponse(200, 'Evento y comentarios', array('event'=>$event , 'comments'=> $commentsBD));

        } catch (Exception $e) 
        {
            return $this->createResponse(500, $e->getMessage());
        }
    }

    function post_delete()
    {

        // falta token
        if (!isset(apache_request_headers()['Authorization']))
        {
            return $this->createResponse(400, 'Token no encontrado');
        }
        $jwt = apache_request_headers()['Authorization'];
        // valdiar token
        try {

            $this->validateToken($jwt);
        } catch (Exception $e) {

            return $this->createResponse(400, 'Error de autentificacion');
        }

        $user = $this->decodeToken();

        if (empty($_POST['id'])) 
        {
          return $this->createResponse(400, 'Falta parámetros obligatorios (id) ');
        }

        $id = $_POST['id'];
   
        try {
            
            $event = Model_Events::find($id); 
            if ($event == null) {
                return $this->createResponse(400, 'No existe el evento');
            }

            if ($event->id_user == $user->data->id || $user->data->id_rol == 1) {
                $event->delete();
                return $this->createResponse(200, 'Evento borrado');
            }else{
                return $this->createResponse(401, 'No autorizado');
            }

            

        } catch (Exception $e) 
        {
            return $this->createResponse(500, $e->getMessage());
        }
    }

    function get_find()
    {

        // falta token
        if (!isset(apache_request_headers()['Authorization']))
        {
            return $this->createResponse(400, 'Token no encontrado');
        }
        $jwt = apache_request_headers()['Authorization'];
        // valdiar token
        try {

            $this->validateToken($jwt);
        } catch (Exception $e) {

            return $this->createResponse(400, 'Error de autentificacion');
        }

        $user = $this->decodeToken();

        if (empty($_GET['search'])) 
        {
          return $this->createResponse(400, 'Falta parámetros obligatorios (search) ');
        }

        $search = $_GET['search'];
   
        try {
            
            $eventsDB = Model_Events::find('all', array(
            'where' => array(
                array('description' ,'LIKE' ,'%'.$search.'%'),'or' => array(array('title' ,'LIKE' ,'%'.$search.'%'))
                ),
            )); 

            if ($eventsDB == null) {
                return $this->createResponse(400, 'No existen eventos');
            }

            return $this->createResponse(200, 'Listado de eventos', $eventsDB);

        } catch (Exception $e) 
        {
            return $this->createResponse(500, $e->getMessage());
        }
    }

    function get_types()
    {

        // falta token
        if (!isset(apache_request_headers()['Authorization']))
        {
            return $this->createResponse(400, 'Token no encontrado');
        }
        $jwt = apache_request_headers()['Authorization'];
        // valdiar token
        try {

            $this->validateToken($jwt);
        } catch (Exception $e) {

            return $this->createResponse(400, 'Error de autentificacion');
        }

        $user = $this->decodeToken();
   
        try {
            $typesBD = Model_Types::find('all');
            if ($typesBD== null) {
                return $this->createResponse(400, 'No existe ningun tipo');
            }
            return $this->createResponse(200, $typesBD);

        } catch (Exception $e) 
        {
            return $this->createResponse(500, $e->getMessage());
        }
    }

    function decodeToken()
    {

        $jwt = apache_request_headers()['Authorization'];
        $token = JWT::decode($jwt, $this->key , array('HS256'));
        return $token;
    }

    function validateToken($jwt)
    {
        $token = JWT::decode($jwt, $this->key, array('HS256'));

        $email = $token->data->email;
        $password = $token->data->password;
        $id = $token->data->id;

        $userDB = Model_Users::find('all', array(
            'where' => array(
                array('email', $email),
                array('password', $password),
                array('id',$id)
                )
            ));
        if($userDB != null){
            return true;
        }else{
            return false;
        }
    }

    function createResponse($code, $message, $data = [])
    {

        $json = $this->response(array(
          'code' => $code,
          'message' => $message,
          'data' => $data
          ));

        return $json;
    }

}