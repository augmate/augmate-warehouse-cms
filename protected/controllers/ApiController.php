<?php
  
class ApiController extends Controller
{
  // Members
    /**
     * Key which has to be in HTTP USERNAME and PASSWORD headers 
     */
    Const APPLICATION_ID = 'CMSAUGMATE';
 
    /**
     * Default response format
     * either 'json' or 'xml'
     */
    private $format = 'json';
    /**
     * @return array action filters
     */
    public function filters()
    {
            return array();
    }
 
    // Actions
    public function actionList()
    {
       $user=$this->_checkAuth();
        switch($_GET['model'])
        {
            case 'order': 
                $models = Order::model()->findAll(array('order'=>'date ASC','condition'=>"status < 2 and Company_idCompany=".$user->Company_idCompany));
                break;
            default:  
                $this->_sendResponse(501, sprintf('Error: Mode <b>list</b> is not implemented for model <b>%s</b>',$_GET['model']) );
                exit; 
        }
        if(is_null($models)) {
            $this->_sendResponse(200, sprintf('No items where found for model <b>%s</b>', $_GET['model']) );
        } else {
            $rows = array();
            foreach($models as $model)
                $rows[] = $model->attributes;

            $this->_sendResponse(200, CJSON::encode($rows));
        }
    }
    public function getNextOrder($user){
        //Get next order of user, get the last open assigned order 
            $order = Order::model()->find(array('order'=>'date ASC','condition'=>"status = 1 and Company_idCompany=".$user->Company_idCompany." and employee=".$user->idUser));  
            //or next no assigned order that is available
            if($order==null)  
                $order = Order::model()->find(array('order'=>'date ASC','condition'=>"status = 0 and Company_idCompany=".$user->Company_idCompany));
            
            //If no are more orders, reset them for demo purposes
            if($order==null)
            {
              Order::resetOrders($user);

              $order = Order::model()->find(array('order'=>'date ASC','condition'=>"status = 0 and Company_idCompany=".$user->Company_idCompany));
            		
              if($order==null)
              {
                //If there are no more orders send a status 204
                // This should never happen now, but just in case something goes wrong with reset...
                $this->_sendResponse(204, sprintf('{"message":"No more orders available"}') );
                exit;
              }
            }
            $products=$order->productsQty;
            return array("order"=>$order,"products"=>$products);
    }
    public function actionView()
    {
       $user=$this->_checkAuth();
        // Check if id was submitted via GET
        if(!isset($_GET['id']))
            $this->_sendResponse(500, 'Error: Parameter <b>id</b> is missing' );

        switch($_GET['model'])
        {
          // Find respective model    
          case 'order':  
            $model = Order::model()->findByPk($_GET['id']);
            break;
          case 'completingOrder':  
            $order = Order::model()->findByPk($_GET['id']);
            $order->status=Order::DELIVERY_TO_PACKAGING;
            $order->save();
            $model=array("packagingLocation"=>rand(1,20));
            break;
          case 'nextOrder':  
            $model= $this->getNextOrder($user);
            break;
          case 'confirmDelivery':
            $order = Order::model()->findByPk($_GET['id']);
            $order->status=Order::COMPLETED;
            $order->save();
            $model= $this->getNextOrder($user);
            break;
          case 'orderProducts':
            $order = Order::model()->findByPk($_GET['id']);
            $model = $order->productsQty;
            break; 
          case 'companyOrders':
            $model = Order::model()->findAll(array('order'=>'date ASC','condition'=>"status < 2 and Company_idCompany=".$_GET['id']));
            break; 
          case 'product':
            $model = Product::model()->findByPk($_GET['id']);
            break;
          case 'pickProduct':
            $product = Product::model()->findByPk($_GET['id']);
            if(!isset($_GET['n'])){
                $this->_sendResponse(405, sprintf('{"message":"No indicate the number of items picked"}') );
                exit; 
            }
            $n=$_GET['n'];
            if($n>$product->quantity){
                $this->_sendResponse(405, sprintf('{"message":"Pick more products than exist in inventory"}') );
                exit; 
            }
            $product->quantity=$product->quantity-$n;
            $product->save();
            if($product->quantity<$product->min_quantity_alert){
                $this->_sendResponse(200, sprintf('{"message":"Alert inventory control replenish products"}') );
                exit; 
            }else{
                $this->_sendResponse(204, sprintf('{"message":""}') );
                exit; 
            }
            break;
          case 'quantityStockItem':
            $product = Product::model()->findByPk($_GET['id']);
            $model= array("qty"=>$product->quantity);
            break;
          case 'company':  
            $model = Company::model()->findByPk($_GET['id']);
            break;  
          case 'login':
            $model=$user;
            break;
          case 'user':
            $model = User::model()->findByPk($_GET['id']);
            break;
          default:  
                $this->_sendResponse(501, sprintf('Mode <b>view</b> is not implemented for model <b>%s</b>',$_GET['model']) );
                exit; 
        }
        if(is_null($model)) {
            $this->_sendResponse(404, 'No Item found with id '.$_GET['id']);
        } else {
            $this->_sendResponse(200,  CJSON::encode($model));
        }
    }
  
    public function actionCreate()
    {
    }
  
    public function actionUpdate()
    {
    }
  
    public function actionDelete()
    {
    }

    private function _sendResponse($status = 200, $body = '', $content_type = 'text/html')
    {
      // set the status
      $status_header = 'HTTP/1.1 ' . $status . ' ' . $this->_getStatusCodeMessage($status);
      header($status_header);
      // and the content type
      header('Content-type: ' . $content_type);
   
      // pages with body are easy
      if($body != '')
      {
          // send the body
          echo $body;
      }
      // we need to create the body if none is passed
      else
      {
          // create some body messages
          $message = '';
   
          // this is purely optional, but makes the pages a little nicer to read
          // for your users.  Since you won't likely send a lot of different status codes,
          // this also shouldn't be too ponderous to maintain
          switch($status)
          {
              case 401:
                  $message = 'You must be authorized to view this page.';
                  break;
              case 404:
                  $message = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';
                  break;
              case 500:
                  $message = 'The server encountered an error processing your request.';
                  break;
              case 501:
                  $message = 'The requested method is not implemented.';
                  break;
          }
   
          // servers don't always have a signature turned on 
          // (this is an apache directive "ServerSignature On")
          $signature = ($_SERVER['SERVER_SIGNATURE'] == '') ? $_SERVER['SERVER_SOFTWARE'] . ' Server at ' . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'] : $_SERVER['SERVER_SIGNATURE'];
   
          // this should be templated in a real-world solution
          $body = '
            <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
            <html>
            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
            <title>' . $status . ' ' . $this->_getStatusCodeMessage($status) . '</title>
            </head>
            <body>
            <h1>' . $this->_getStatusCodeMessage($status) . '</h1>
            <p>' . $message . '</p>
            <hr />
            <address>' . $signature . '</address>
            </body>
            </html>';
          echo $body;
      }
      Yii::app()->end();
  }

    /**
     * Gets the message for a status code
     * 
     * @param mixed $status 
     * @access private
     * @return string
     */
    private function _getStatusCodeMessage($status)
    {
        // these could be stored in a .ini file and loaded
        // via parse_ini_file()... however, this will suffice
        // for an example
        $codes = Array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported'
        );

        return (isset($codes[$status])) ? $codes[$status] : '';
    }

  /**
     * Checks if a request is authorized
     * 
     * @access private
     * @return user
     */
    private function _checkAuth()
    {
        // Check if we have the USERNAME and PASSWORD HTTP headers set?
        if(!(isset($_SERVER['PHP_AUTH_USER']) and isset($_SERVER['PHP_AUTH_PW']))) {
            // Error: Unauthorized
            $this->_sendResponse(401);
        }
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
        // Find the user
        $user=User::model()->find('LOWER(username)=?',array(strtolower($username)));
        if($user===null) {
            // Error: Unauthorized
            $this->_sendResponse(401, 'Error: User Name is invalid'. $username .' '.$password);
        } else if($user->password!=$password) {
            // Error: Unauthorized
            $this->_sendResponse(401, 'Error: User Password is invalid'. $username .' '.$password);
        }
        return $user;
    }
  
  
    /**
     * Returns the json or xml encoded array
     * 
     * @param mixed $model 
     * @param mixed $array Data to be encoded
     * @access private
     * @return void
     */
    private function _getObjectEncoded($model, $array)
    {
        if(isset($_GET['format']))
            $this->format = $_GET['format'];

        if($this->format=='json')
        {
            return CJSON::encode($array);
        }
        elseif($this->format=='xml')
        {
            $result = '<?xml version="1.0">';
            $result .= "\n<$model>\n";
            foreach($array as $key=>$value)
                $result .= "    <$key>".utf8_encode($value)."</$key>\n"; 
            $result .= '</'.$model.'>';
            return $result;
        }
        else
        {
            return;
        }
    }

}
