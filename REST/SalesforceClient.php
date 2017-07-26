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

use CampaignChain\Security\Authentication\Client\OAuthBundle\Entity\Token;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\ApplicationService;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\TokenService;
use GuzzleHttp\Client;

class SalesforceClient
{
    const RESOURCE_OWNER = 'Salesforce';

    protected $baseUrl;
    protected $apiVersion;
    protected $oauthApplicationService;
    protected $oauthTokenService;

    /** @var  Client */
    protected $client;

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

    public function connectByLocation($location){

        $this->organizerKey = $location->getIdentifier();

        $application = $this->oauthApplicationService->getApplication(self::RESOURCE_OWNER);

        /** @var Token $token */
        $token = $this->oauthTokenService->getToken($location);

        $this->baseUrl = $token->getEndpoint().'/services/data/v'.$this->apiVersion.'/';

        return $this->connect($application->getKey(), $application->getSecret(), $token->getAccessToken(), $token->getTokenSecret());
    }

    public function connect($appKey, $appSecret, $accessToken, $tokenSecret){
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
            throw new \Exception($e->getMessage());
        }
    }

    public function getLeadById($objectId)
    {
        $res = $this->client->request('GET', 'sobjects/Lead/'.$objectId);
        return json_decode($res->getBody());
    }

    /**
     * See https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_query.htm
     *
     * @param $soql The SOQL statement: https://developer.salesforce.com/docs/atlas.en-us.soql_sosl.meta/soql_sosl/sforce_api_calls_soql_select.htm
     * @return mixed
     */
    public function getQuery($soql)
    {
        $res = $this->client->request('GET', 'query/?q='.urlencode($soql));
        return json_decode($res->getBody());
    }
}