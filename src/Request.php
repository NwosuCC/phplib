<?php
namespace Orcses\PhpLib;


use Net\PreUpload;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Access\Auth;
use Orcses\PhpLib\Routing\Router;
use Orcses\PhpLib\Cache\DatabaseCache;
use Orcses\PhpLib\Traits\ValidatesRequest;


class Request
{
  use ValidatesRequest;


  protected static $pinned = false;

  private $headers, $route_space, $method, $uri, $input, $files, $params;

  protected $errors;

  protected $captured = [
      'headers', 'method', 'uri', 'input', 'files', 'params'
  ];

  /** Specifies the routes to throttle */
  protected static $throttle_routes = [];

  /**
   * @var array $route_params
   * The parameters of the current route being processed
   */
  protected static $route_params = [];


  /** @return  static */
  public static function capture()
  {
    return (new static())->getContents();
  }


  /**
   * Bind this current Request state to the Container, to be served in subsequent resolve() calls
   */
  public function pinCurrentState()
  {
    app()->pin(static::class, $this);
  }


  /**
   * Called by Request child classes to 'capture' the (pinned) parent's contents
   */
  public function hydrate()
  {
    /**@var self $request */
    $request = app()->make(self::class);

    foreach ($request->captured as $prop){
      $this->{$prop} = $request->{$prop};
    }
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


  public function routeSpace()
  {
    return $this->route_space;
  }


  public function method()
  {
    return $this->method;
  }


  public function uri()
  {
    return $this->uri;
  }


  public function input(string $field = '')
  {
    if($field){
      return $this->input[ $field ] ?? null;
    }

    return $this->input;
  }


  public function only(array $fields, bool $assoc = true)
  {
    return Arr::pickOnly($this->input(), $fields, $assoc);
  }


  public function seek(array $fields)
  {
    $available_fields = Arr::getExistingKeys($input = $this->input(), $fields);

    return Arr::pickOnly($input, array_keys($available_fields));
  }


  public function files()
  {
    return $this->files;
  }


  public function setParams(array $params)
  {
    $this->params = $params;
  }


  public function params($keys = null, bool $assoc = true)
  {
    if( ! $keys){
      return $this->params;
    }

    $assoc &= ($is_array = is_array($keys));

    $values = Arr::pickOnly($this->params, (array) $keys, $assoc);

    return $is_array ? $values : array_shift($values);
  }


  protected final function getContents()
  {
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

    // ToDo: include Router::TMP
    $this->route_space = !empty($web_space) && ! $this->files ? Router::WEB : Router::API;

    static::$route_params = [
      'method' => $this->method, 'uri' => $this->uri, 'route_space' => $this->route_space
    ];
    pr(['usr' => __FUNCTION__, 'method' => $this->method, 'uri' => $this->uri, 'route_space' => $this->route_space, 'input' => $this->input(), 'file' => $this->files()]);

    return $this;
  }


  public function transformWith(array $transform)
  {
    //
  }


  public function validateWith(array $rules)
  {
    if($this->files()){
      $this->input = $this->input ? array_merge($this->input, $this->files) : $this->files;
    }

    [$checked_fields, $errors] = $this->validator()->make( $rules )->validate( $this );

    if($errors){
      $this->errors = [report()::VALIDATION, 1, $errors];
    }

    $this->input = $this->seek($checked_fields);

    $this->pinCurrentState();

    return $this;
  }


  public static function throttle(string $route_key, $error = null)
  {
    if( ! in_array($route_key, static::$throttle_routes)) {
      static::$throttle_routes[] = $route_key;
    }

    if( ! $error){
      $error = Auth::error(Auth::PLEASE_CONTINUE);
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
    pr(['usr' => __FUNCTION__, '$error_code' => $error_code, '$log_out' => $log_out]);

    if( ! $error_code){
      $error_code = ['App', 2];
    }

    ($log_out && $error_code[0] === report()::ACCESS)

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
        // ToDo: refactor
//        $replaces = [
//          'minutes' => Auth::throttleDelay()
//        ];

//        Request::abort( Auth::error([Auth::THROTTLE_DELAY, $replaces]), true );
      }
    }

    return $access_allowed;
  }


  // ToDo: --
  public function uploadFile(){
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

