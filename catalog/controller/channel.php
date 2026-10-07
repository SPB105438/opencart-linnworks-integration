<?php
namespace Opencart\Catalog\Controller\Extension\Linnworks;
class Channel extends \Opencart\System\Engine\Controller {public function index():void{$this->response->addHeader('Content-Type: application/json');$this->response->setOutput(json_encode(['status'=>'disabled','message'=>'Outbound API integration mode']));}}
