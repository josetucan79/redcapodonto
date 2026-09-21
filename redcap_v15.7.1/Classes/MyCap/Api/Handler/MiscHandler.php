<?php
namespace Vanderbilt\REDCap\Classes\MyCap\Api\Handler;

use Vanderbilt\REDCap\Classes\MyCap\Api\DB\Project;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\Error\ProjectHandlerError;
use Vanderbilt\REDCap\Classes\MyCap\Api\Response;
use Vanderbilt\REDCap\Classes\MyCap\MyCapApi;

class MiscHandler
{

    /** @var array $actions Array of actions this handler implements */
    public static $actions = [
        "GET_ACCESS_KEY" => "getAccessKey",
        "TEST_ENDPOINT" => "testEndpoint"
    ];
    /** @var string $hmacKey Hash-based Message Access Code key */
    private $hmacKey;

    /**
     * The Handler constructor requires the following arguments:
     *
     * - apiDelegate
     *
     * Optional arguments:
     *
     * - hmacKey
     *
     * @param array $args Project configuration arguments.
     * @throws Exception
     */
    public function __construct(array $args)
    {
        if (isset($args["hmacKey"])) {
            $this->hmacKey = $args["hmacKey"];
        }
    }

    /**
     * TODO: This really should be a user specific token that expires after N minutes. Possible to implement
     * OAuth using REDCap as database? For now, all studies and participants share the same key. This seems
     * OK because it is very hard to abuse the API. Rethink this when consent and user provisioning is added
     * in-app.
     */
    public function getAccessKey($data)
    {
        MyCapApi::validateParameters(
            $data,
            ["stu_code"]
        );

        $stu_code = $data['stu_code'];

        try {
            $myProj = new Project();
            $projects = $myProj->loadByCode($stu_code);
            Response::sendSuccess(['accessKey' => $projects['hmac_key']]);
        } catch (\Exception $e) {
            Response::sendError(
                400,
                ProjectHandlerError::CODE_NOT_FOUND,
                $e->getMessage()
            );
        }
    }

    /**
     * This method does not do anything. Purpose is to have a method so developers can verify that the endpoint
     * can be hit from a device
     */
    public function testEndpoint()
    {
        Response::sendSuccess();
    }

}
