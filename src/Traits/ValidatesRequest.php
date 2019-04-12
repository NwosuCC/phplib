<?php

namespace Orcses\PhpLib\Traits;

use Orcses\PhpLib\Request;


trait ValidatesRequest
{
  /** @var \Orcses\PhpLib\Validator */
  protected $validator;

  protected $rules;


  public function validator(){
    $this->validator = app()->make('Validator');

    return $this;
  }


  public function make(array $rules)
  {
    $this->rules = $rules;

    return $this;
  }


  public function validate(Request $request)
  {
    pr(['usr' => __FUNCTION__, 'input' => $request->input(), 'file' => $request->files()]);

    $input = array_merge( $request->input(), $request->files() );

    return $this->validator->run( $input, $this->rules);
  }


}