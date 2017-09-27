<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace CampaignChain\Channel\SalesforceBundle\REST;

use CampaignChain\CoreBundle\Exception\ExternalApiException;
use CampaignChain\CoreBundle\Util\VariableUtil;
use CampaignChain\Security\Authentication\Client\OAuthBundle\Entity\Application;
use CampaignChain\Security\Authentication\Client\OAuthBundle\Entity\Token;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\ApplicationService;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\TokenService;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Exception\RequestException;

class SalesforceClient
{
    const RESOURCE_OWNER = 'Salesforce';

    protected $baseUrl;
    protected $apiVersion;
    protected $oauthApplicationService;
    protected $oauthTokenService;

    /** @var  Client */
    protected $client;
    /** @var  Application */
    protected $application;
    /** @var  Token */
    protected $token;
    protected $isSandbox;

    public function __construct(
        $apiVersion,
        ApplicationService $oauthApplicationService,
        TokenService $oauthTokenService
    )
    {
        $this->apiVersion = $apiVersion;
        $this->oauthApplicationService = $oauthApplicationService;
        $this->oauthTokenService = $oauthTokenService;
    }

    public function connectByActivity($activity){
        return $this->connectByLocation($activity->getLocation());
    }

    public function connectByLocation($location, $isSandbox = false){
        $this->isSandbox = $isSandbox;

        $this->application = $this->oauthApplicationService->getApplication(self::RESOURCE_OWNER);

        /** @var Token $token */
        $this->token = $this->oauthTokenService->getToken($location);

        $this->baseUrl = $this->token->getEndpoint().'/services/data/v'.$this->apiVersion.'/';

        return $this->connect($this->token->getAccessToken());
    }

    public function connect($accessToken){
        try {
            $this->client = new Client([
                'base_uri' => $this->baseUrl,
                'headers' => [
                    "Authorization" => "Bearer ".$accessToken,
                ]
            ]);

            return $this;
        }
        catch (\Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Generic request method to refresh token in case session expired.
     *
     * @param $method
     * @param $uri
     * @param array $body
     * @return mixed
     */
    private function request($method, $uri, $body = array())
    {
        try {
            $res = $this->client->request($method, $uri, $body);
            return json_decode($res->getBody());
        } catch(RequestException $e){
            $res = VariableUtil::json2Array(
                json_decode($e->getResponse()->getBody()->getContents())[0]
            );
            /*
             * If the session expired, then we must request a new token with the
             * refresh token.
             */
            if(isset($res['errorCode']) && $res['errorCode'] == 'INVALID_SESSION_ID'){
                $this->refreshToken($method, $uri, $body);
            } else {
                throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    protected function refreshToken($method, $uri, $body)
    {
        // Expired, so request a new token with the refresh token.
        if($this->isSandbox){
            $host = 'https://test.salesforce.com';
        } else {
            $host = 'https://login.salesforce.com';
        }

        $restUrl = $host.'/services/oauth2/token';
        $params = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->token->getRefreshToken(),
            'client_id'     => $this->application->getKey(),
            'client_secret' => $this->application->getSecret(),
        ];

        $client = new Client();
        $res = $client->post($restUrl, array('query' => $params));
        $data = json_decode($res->getBody());

        $this->oauthTokenService->refreshToken(
            $this->token->getAccessToken(), $data->access_token
        );

        // Re-connect with new access token and re-issue the request with
        // the new access token.
        $this->connect($data->access_token);
        return $this->client->request($method, $uri, $body);
    }

    public function getLeadById($id, array $fields = array())
    {
        $requestOptions = array();

        if(count($fields)){
            $fieldsQuery = implode(',', $fields);
            $requestOptions = array(
                'query' => array(
                    'fields' => $fieldsQuery
                ),
            );
        }
        return $this->request('GET', 'sobjects/Lead/'.$id, $requestOptions);
    }

    /**
     * See https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_query.htm
     *
     * @param $soql The SOQL statement: https://developer.salesforce.com/docs/atlas.en-us.soql_sosl.meta/soql_sosl/sforce_api_calls_soql_select.htm
     * @return mixed
     */
    public function getQuery($soql)
    {
        return $this->request('GET', 'query/?q='.urlencode($soql));
    }

    public function updateLead($id, $data)
    {
        return $this->request('PATCH', 'sobjects/Lead/'.$id, [
            'json' => $data
        ]);
    }

    public function createLead($data)
    {
        return $this->request('POST', 'sobjects/Lead/', [
            'json' => $data
        ]);
    }

    public function createAccount($data)
    {
        return $this->request('POST', 'sobjects/Account/', [
            'json' => $data
        ]);
    }

    public function updateAccount($id, $data)
    {
        return $this->request('PATCH', 'sobjects/Account/'.$id, [
            'json' => $data
        ]);
    }

    public function deleteAccount($id)
    {
        return $this->request('DELETE', 'sobjects/Account/'.$id);
    }
}