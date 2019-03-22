<?php
namespace Orcses\PhpLib;


class Request
{
  /** @var Application */
  protected $app;

  protected $validator, $captured;

  /** Specifies the routes to throttle */
  protected $throttle_routes = [];

  private $data, $files;


  protected function __construct($app = null){
    if($app){
      $this->app = $app;
    }

    $this->validator = $this->app->build(Validator::class);

    $this->request()->validate();

    $this->boot();
  }


  // The child class should override this function and use it to run more actions after __construct()
  protected function boot(){
    // ...
  }


  public static function capture($app){
    $request = new static($app);

    Auth::verifyCachedToken($this);

    return $request;
  }


  public function captured(){
    return $this->captured;
  }


  public function data(){
    return $this->data;
  }


  public function files(){
    return $this->files;
  }


  public function validate(){
    // --test
    $this->data = [
      'op' =>'0e', 'user' => 'GMO.com', 'password' => '10/10-jimoh', 'type' => 'u'
    ];

    $input = $this->data();

    $controller_class = $this->app->getControllerInstanceFromOp( $input['op'] );

    $rules = $controller_class->rules();

    $errors = $this->validator->run($input, $rules);

    $this->captured = compact('input', 'errors');

    return $this;
  }


  /** @return $this */
  protected function request(){
    $request = json_decode(file_get_contents("php://input"),true);

    // ToDo: use this
    if( !empty($_FILES)){
      $this->files = $_FILES;
    }

    if( ! empty($_POST['op'])) {
      // If request includes a standard 'x-www-urlencoded-form' $_POST (e.g AngularJS file upload)
      $request = $_POST;
    }

    if(empty($request)) {
      // If empty request (e.g dev-server ping), set default op = server.ping
      // This is necessary to handle empty request normally and prevent CORS error in the response
      $request = [
        'op' => Application::get_op('server.ping')
      ];
    }

    $this->data = $request;

    return $this;
  }


  public function throttle($op){
    if( ! in_array($op, $this->throttle_routes)) {
      return true;
    }

    return self::retryOrFail($op, ['access', 3]);
  }

  protected function retry(){
    $this->retryOrFail( $this->data['op'], ['App', 2], 5 );
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

    list($error_number, $message) = (new PreUpload)->run( $this->data() );

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

