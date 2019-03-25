<?php
namespace Orcses\PhpLib;


use Orcses\PhpLib\Utility\Arr;

class Request
{
  /** @var Application */
  protected $app;

  protected $validator, $captured;

  /** Specifies the routes to throttle */
  protected $throttle_routes = [];

  private $method, $uri, $input, $files;


  protected function __construct($app = null){
    if($app){
      $this->app = $app;
    }
  }


  public static function capture(){
    $request = new static();

    //    Access::verifyCachedToken($this);

    $request->getContents();

    return $request;
  }


  public function captured(){
    return $this->captured;
  }


  public function method(){
    return $this->method;
  }


  public function uri(){
    return $this->uri;
  }


  public function input(string $field = ''){
    return $field ? ($this->input[ $field ] ?? null) : $this->input;
  }


  public function files(){
    return $this->files;
  }


  protected function getContents(){
    $this->method = $_SERVER['REQUEST_METHOD'];

    $this->uri = $_SERVER['REQUEST_URI'];

    // ToDo: use the files content
    if( !empty($_FILES)){
      $this->files = $_FILES;
    }

    $input = json_decode( file_get_contents("php://input"),true);

    if( ! empty($_POST['op'])) {
      // If request includes a standard 'x-www-urlencoded-form' $_POST (e.g AngularJS file upload)
      $input = $_POST;
    }

    if(empty($input)) {
      // If empty request (e.g dev-server ping), set default op = server.ping
      // This is necessary to handle empty request normally and prevent CORS error in the response
      $input = [
        'op' => Application::get_op('server.ping')
      ];
    }

    $this->input = $input;
  }


  public function validate(Validator $validator){
//    $this->validator = $this->app->build(Validator::class);

//    $this->request()->validate();

    $input = $this->input();

    $controller_class = $this->app->getControllerInstanceFromOp( $input['op'] );

    $rules = $controller_class->rules();

//    $errors = $this->validator->run($input, $rules);
    $errors = $validator->run($input, $rules);

    $this->captured = compact('input', 'errors');

    return $this;
  }


  public function throttle($op){
    if( ! in_array($op, $this->throttle_routes)) {
      return true;
    }

    return self::retryOrFail($op, ['access', 3]);
  }


  /**
   * Abort the request if any unexpected error occurs e.g Controller not found (???)
   * @return  Response
   */
  public static function abort(){
    $result = Result::prepare(['App', 2]);

    return Response::package( $result );
  }


  protected function retry(){
    $this->retryOrFail( $this->input['op'], ['App', 2], 5 );
  }


  // Block request User after (n = $times)th failed suspicious requests
  public static function retryOrFail(string $op, array $error, int $times = 3){
    // --test :: ToDo: remove this
    if(Application::isLocal()){
      return true;
    }

    $op_id = 't' . '.' . $op;

    $cache_key = sha1(($_SERVER['REMOTE_HOST'] ?? '') . $op_id);

    $attempts_made = $c_key = (int) DatabaseCache::fetch($cache_key) ?: 0;

    $current_attempt = $attempts_made + 1;

    // NOT blocked: Within allowed number of trials
    $access_allowed = ($current_attempt <= $times);

    // Access timeout: Current attempt has exceeded Allowed trials but is within extra 3 trials
    // Set Login timeout (in minutes) the user has to wait before try again
    $access_time_out = ($current_attempt <= ($times + 3));

    $blacklist_now = !$access_allowed && !$access_time_out;

    if($blacklist_now) {
      // Use reCaptcha or outright Block IP (add IP to Server blacklist)
      Result::prepareAndSend(['ping', 1]);

    }
    else {
      DatabaseCache::store($cache_key, $current_attempt);

      if($access_allowed){
        Result::prepareAndSend($error);
      }
      else {
        $replaces = ['minutes' => $timeout_minutes = Auth::timeout()];

        Auth::logout([6, $replaces], true, $timeout_minutes);
      }
    }

    return $access_allowed;
  }


  // ToDo: --
  public function run_upload(){
    $info = [];

    list($error_number, $message) = (new PreUpload)->run( $this->input() );

    if( ! $upload_result = intval($error_number)){
      $info = $message;
    }
    else{
      $replaces['more_message'] = 'Error ' . $error_number . ': ' . $message;

      $error_number = 1;

      $upload_result = [$error_number, $replaces];
    }

    Result::prepareAndSend(['upload', $upload_result, $info]);
  }

}

