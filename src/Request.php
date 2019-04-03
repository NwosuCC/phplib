<?php
namespace Orcses\PhpLib;


use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Access\Auth;
use Orcses\PhpLib\Routing\Router;
use Orcses\PhpLib\Cache\DatabaseCache;
use Orcses\PhpLib\Traits\ValidatesRequest;


class Request
{
  use ValidatesRequest;


  protected static $instance;

  private $headers, $route_space, $method, $uri, $input, $files;

  protected $validator, $errors, $error_code, $captured;

  /** Specifies the routes to throttle */
  protected static $throttle_routes = [];

  /**
   * @var array $route_params
   * The parameters of the current route being processed
   */
  protected static $route_params = [];


  public function __construct(){
    pr(['new Request' => !!static::$instance]);
    if(static::$instance){
      // Allow Request child classes to 'capture' the parent request and its contents
      $this->capture($this);
    }
  }


  protected static function instance(){
    if( ! static::$instance){
      static::$instance = new static();
    }

    return static::$instance;
  }


  public static function capture($request = null){
    if( ! $request){
      $request = static::instance();
    }

    /** @var static $request */
    $request->getContents();

    return $request;
  }


  public function captured(){
    return $this->captured;
  }


  public function errors(){
    return $this->errors;
  }


  public function getHeader(string $key)
  {
    return $this->headers[ $key ] ?? null;
  }


  public static function currentRouteParams(bool $assoc = false)
  {
    $params = static::$route_params;

    return $assoc ? $params : array_values($params);
  }


  public function routeSpace(){
    return $this->route_space;
  }


  public function method(){
    return $this->method;
  }


  public function uri(){
    return $this->uri;
  }


  public function input(string $field = ''){
    if($field){
      return $this->input[ $field ] ?? null;
    }

    return $this->input;
  }


  public function only(array $fields, bool $assoc = true){
    return Arr::pickOnly($this->input(), $fields, $assoc);
  }


  public function files(){
    return $this->files;
  }


  /*protected final function getContents(){
  $this->method = $_SERVER['REQUEST_METHOD'];

  $this->uri = $_SERVER['REQUEST_URI'];

  // ToDo: use the files content
  if( !empty($_FILES)){
    $this->files = $_FILES;
  }

  $input = json_decode( file_get_contents("php://input"),true);

  dd($input, $_POST);

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
}*/
  protected final function getContents(){
    $this->headers = getallheaders();

    $this->method = $_SERVER['REQUEST_METHOD'];

    $this->uri = $_SERVER['REQUEST_URI'];

    // ToDo: use the files content
    $this->files = ( !empty($_FILES)) ? $_FILES : null;

    $this->input = json_decode( file_get_contents("php://input"),true);

    if( ! $this->input){
      if( ! empty($_POST)){
        $web_space = true;
        $this->input = $_POST;
      }
    }

    $this->route_space = !empty($web_space) ? Router::WEB : Router::API;

    static::$route_params = [
      'method' => $this->method, 'uri' => $this->uri, 'route_space' => $this->route_space
    ];
  }


  public function validateWith(array $rules)
  {
    if($errors = $this->validator()->make( $rules )->validate( $this )){

      $this->errors = ['validation', 1, $errors];
    }

    $input = $this->input();

    $this->captured = compact('input', 'errors');

    return $this;
  }


  public static function throttle(string $route_key, $error = null)
  {
    if( ! in_array($route_key, static::$throttle_routes)) {
      static::$throttle_routes[] = $route_key;
    }

    if( ! $error){
      $error = Auth::error(Auth::PLS_CONTINUE);
    }

    return static::retryOrFail($route_key, $error);
  }


  /**
   * Abort the request if any unexpected error occurs e.g Controller not found (???)
   * @param array $error_code e,g ['access', 3]
   * @param bool $log_out
   */
  public static function abort(array $error_code = [], bool $log_out = false)
  {
    if( ! $error_code){
      $error_code = ['App', 2];
    }

    ($log_out && $error_code[0] === Auth::REPORT_KEY)

      ? Auth::logout( $error_code[1] )

      : Response::get( $error_code )->send();
  }


  protected function retry()
  {
    $this->retryOrFail( $this->input['op'], ['App', 2], 5 );
  }


  // Block request User after (n = $times)th failed suspicious requests
  protected static function retryOrFail(string $controller, array $error, int $times = 3)
  {
    // --test :: ToDo: remove this
    if(Application::isLocal()){
      return true;
    }

    $controller_id = 't' . '.' . $controller;

    $cache_key = sha1(($_SERVER['REMOTE_HOST'] ?? '') . $controller_id);

    $attempts_made = (int) DatabaseCache::fetch($cache_key) ?: 0;

    $current_attempt = $attempts_made + 1;

    // NOT blocked: Within allowed number of trials
    $access_allowed = ($current_attempt <= $times);

    // Access timeout: Current attempt has exceeded Allowed trials but is within extra 3 trials
    // Set Login timeout (in minutes) the user has to wait before try again
    $access_time_out = ($current_attempt <= ($times + 3));

    $blacklist_now = ! $access_allowed && ! $access_time_out;

    if($blacklist_now) {
      // Use reCaptcha or outright Block IP (add IP to Server blacklist)
      self::abort(['ping', 1]);
    }
    else {
      DatabaseCache::store($cache_key, $current_attempt);

      if($access_allowed){
        self::abort($error);

      }
      else {
        $replaces = [
          'minutes' => Auth::throttleDelay()
        ];

        Request::abort( Auth::error([Auth::THROTTLE_DELAY, $replaces]), true );
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

