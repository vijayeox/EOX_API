<?php

namespace Auth\Controller;

use Auth\Adapter\LoginAdapter as AuthAdapter;
use Auth\Service\AuthService;
use Exception;
use Firebase\JWT\JWT;
use Oxzion\Controller\AbstractApiControllerHelper;
use Oxzion\Encryption\Crypto;
use Oxzion\EntityNotFoundException;
use Oxzion\ServiceException;
use Oxzion\Service\CommandService;
use Oxzion\Service\UserService;
use Oxzion\Service\UserTokenService;
use Oxzion\ValidationException;
use Zend\Authentication\Adapter\DbTable\CredentialTreatmentAdapter as ApiAdapter;

class AuthController extends AbstractApiControllerHelper
{
    /**
     * @ignore authAdapter
     */
    private $authAdapter;
    private $apiAdapter;
    /**
     * @ignore log
     */
    private $log;
    /**
     * @ignore userService
     */
    private $userService;
    private $userTokenService;
    private $authService;
    private $commandService;

    /**
     * @ignore __construct
     */
    public function __construct(AuthAdapter $authAdapter, ApiAdapter $apiAdapter, UserService $userService, UserTokenService $userTokenService, AuthService $authService, CommandService $commandService)
    {
        $this->authAdapter = $authAdapter;
        $this->apiAdapter = $apiAdapter;
        $this->log = $this->getLogger();
        $this->userService = $userService;
        $this->userTokenService = $userTokenService;
        $this->authService = $authService;
        $this->commandService = $commandService;
    }

    /**
     * Login Auth API
     * @api
     * @method POST
     * @param string $username Username of user to Login
     * @param string $password password of user to Login
     * @return array Returns a JSON Response with Status Code and Created Announcement.
     * <code>
     * {
     *  string success|error,
     *  array data{
     *    string jwt
     * },
     * }
     * </code>
     */
    public function authAction()
    {
        $data = $this->request->getPost()->toArray();
        $crypto = new Crypto();
        if (isset($data['username']) && isset($data['password'])) {
            $data['username'] = trim($data['username']);
            $this->authAdapter->setIdentity($data['username']);
            $this->authAdapter->setCredential($data['password']);
            $result = $this->authAdapter->authenticate();
        } elseif (isset($data['apikey'])) {
            $apiSecret = array_column($this->getApiSecret($data['apikey']), 'secret');
            if (!empty($apiSecret)) {
                $this->apiAdapter->setIdentity($data['apikey']);
                $this->apiAdapter->setCredential($apiSecret[0]);
                $result = $this->apiAdapter->authenticate();
            } else {
                return $this->getErrorResponse("Authentication Failure - Incorrect Api Key", 404);
            }
        }
        if (isset($result)) {
            if ($result->isValid()) {
                if (isset($data['username']) && isset($data['password'])) {
                    $userInfo = $this->userService->updateUserCacheData(0);
                    return $this->getJwt($data['username'], $this->userService->getUserAccount($data['username']), 0);
                } elseif (isset($data['apikey'])) {
                    return $this->getApiJwt($data['apikey']);
                }
            }
        }
        return $this->getErrorResponse("Authentication Failure - Incorrect data specified", 404);
    }

    public function refreshtokenAction()
    {
        $data = $this->request->getPost()->toArray();
        try {
            if (isset($data['jwt'])) {
                $tokenPayload = $this->decodeJwtToken($data['jwt']);
                if (is_array($tokenPayload) || is_object($tokenPayload)) {
                    $uname = isset($tokenPayload->data->username) ? $tokenPayload->data->username : $tokenPayload['username'];
                    $accountId = isset($tokenPayload->data->accountId) ? $tokenPayload->data->accountId : (isset($tokenPayload['accountId']) ? $tokenPayload['accountId'] : null);
                    if (!$accountId) {
                        return $this->getErrorResponse("Invalid JWT Token", 404);
                    }
                    $userDetail = $this->userService->getUserDetailsbyUserName($uname);
                    $userDetail['id'] = isset($userDetail['id']) ? $userDetail['id'] : null;
                    $userTokenInfo = $this->userTokenService->checkExpiredTokenInfo($userDetail['id']);
                    if (!empty($userTokenInfo)) {
                        $data = ['username' => $uname, 'accountId' => $accountId];
                        $dataJwt = $this->getTokenPayload($data);
                        $jwt = $this->generateJwtToken($dataJwt);
                        $refreshToken = $this->userTokenService->generateRefreshToken($userDetail);
                        return $this->getSuccessResponseWithData(['jwt' => $jwt, 'refresh_token' => $refreshToken]);
                    } else {
                        return $this->getErrorResponse("Refresh Token Expired", 404);
                    }
                } else {
                    return $this->getErrorResponse("JWT Token Error", 404);
                }
            } else {
                return $this->getErrorResponse("JWT Token Not Found", 404);
            }
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse("Invalid JWT Token", 404);
        }
    }

    public function registerAction()
    {
        $data = $this->extractPostData();
        if (isset($data['data'])) {
            $data = $data['data'];
        }
        try {
            if (isset($data['commands'])) {
                if (is_string($data['commands'])) {
                    if ($commands = json_decode($data['commands'], true)) {
                        $data['commands'] = $commands;
                    }
                }
            }
            $result = $this->commandService->runCommand($data, $this->getRequest());
        } catch (ServiceException $e) {
            $this->log->error("Error" . $e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), 404);
        } catch (ValidationException $e) {
            $this->log->error("Error" . $e->getMessage() . json_encode($e->getErrors()), $e);
            return $this->getErrorResponse($e->getMessage(), 417, $e->getErrors());
        } catch (Exception $e) {
            $this->log->error("Error" . $e->getMessage(), $e);
            return $this->getErrorResponse($e->getMessage(), 404);
        }
        if ($result == 1) {
            return $this->getErrorResponse("Error processing registration", 500);
        }
        if (isset($result['auto_login'])) {
            $result = $this->getJwt($result['user']['username'], $this->userService->getUserAccount($result['user']['username']), 1);
        }
        return $this->getSuccessResponseWithData($result);
    }

    /**
     * @ignore getJwt
     */
    private function getJwt($userName, $accountId, $raw = 0)
    {
        $data = ['username' => $userName, 'accountId' => $accountId];
        $dataJwt = $this->getTokenPayload($data);
        $userDetail = $this->userService->getUserDetailsbyUserName($userName);
        $refreshToken = $this->userTokenService->generateRefreshToken($userDetail);
        $jwt = $this->generateJwtToken($dataJwt);
        if ($refreshToken != 0) {
            if ($raw) {
                return ['jwt' => $jwt, 'refresh_token' => $refreshToken, 'username' => $userName];
            }
            return $this->getSuccessResponseWithData(['jwt' => $jwt, 'refresh_token' => $refreshToken, 'username' => $userName]);
        } else {
            if ($raw) {
                return array();
            }
            return $this->getErrorResponse("Login Error", 405, array());
        }
    }

    public function validatetokenAction()
    {
        $data = $this->request->getPost()->toArray();
        try {
            if (isset($data['jwt'])) {
                $tokenPayload = $this->decodeJwtToken($data['jwt']);
                if (is_array($tokenPayload) && !is_object($tokenPayload)) {
                    if ($tokenPayload['Error'] == 'Expired token') {
                        return $this->getErrorResponse("Token Expired");
                    } else {
                        return $this->getErrorResponse("Token Invalid");
                    }
                } else {
                    return $this->getSuccessResponse("Token Valid");
                }
            } else {
                return $this->getErrorResponse("JWT Token Not Found", 404);
            }
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse("Invalid JWT Token", 404);
        }
    }
    private function getApiJwt($apiKey)
    {
        $data = ['apikey' => $apiKey];
        $dataJwt = $this->getTokenPayload($data);
        $jwt = $this->generateJwtToken($dataJwt);
        if ($jwt) {
            return $this->getSuccessResponseWithData(['jwt' => $jwt]);
        } else {
            return $this->getErrorResponse("Invalid JWT Token", 404, array());
        }
    }

    public function getApiSecret($apiKey)
    {
        $apiSecret = $this->authService->getApiSecret($apiKey);
        return $apiSecret;
    }

    public function userprofAction()
    {
        $data = $this->request->getPost()->toArray();
        try {
            if (isset($data["username"])) {
                $username = $data["username"];
                $res = $this->userService->getUserBaseProfile($username);
                $profilePicUrl = $this->getBaseUrl() . "/user/profile/" . $res["uuid"];
                return $this->getSuccessResponseWithData(['username' => $res["name"], 'profileUrl' => $profilePicUrl]);
            } else {
                return $this->getErrorResponse("Invalid Request", 404);
            }
        } catch (EntityNotFoundException $e) {
            return $this->getErrorResponse("Invalid User", 404);
        } catch (Exception $e) {
            $this->log->error($e->getMessage(), $e);
            return $this->getErrorResponse("Something went wrong", 404);
        }
    }
}