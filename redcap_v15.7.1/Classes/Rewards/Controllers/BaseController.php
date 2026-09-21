<?php
namespace VanderbiltRedcap\Rewards\Src\Controllers;

use JsonSerializable;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use VanderbiltRedcap\Rewards\Src\Services\BaseService;



abstract class BaseController {

    /**
     *
     * @var BaseService
     */
    protected $service;

    /**
     *
     * @param ResponseInterface $response
     * @param JsonSerialazable $data
     * @param integer $status
     * @return void
     */
    public function printJSON(ResponseInterface $response, $data, $status=200) {
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     *
     * @param ResponseInterface $response
     * @param Throwable $th
     * @return void
     */
    public function emitJsonError(ResponseInterface $response, Throwable $th) {
		$data = [
			'message' => $th->getMessage(),
			'code' => $code = $th->getCode(),
		];
		return $this->printJSON($response, $data, $code);
	}

}