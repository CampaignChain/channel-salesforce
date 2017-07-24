<?php
/*
 * Copyright 2017 CampaignChain, Inc. <info@campaignchain.com>
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

/**
 * Hybrid_Providers_Salesforce
 *
 * https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/intro_understanding_authentication.htm
 */
class Hybrid_Providers_Salesforce extends Hybrid_Provider_Model_OAuth2
{ 
	// default permissions  
	// (no scope)
	public $scope = "";

	public $userProfileUrl = 'https://login.salesforce.com/services/oauth2/userinfo';

	/**
	* IDp wrappers initializer 
	*/
	public function initialize()
	{
        parent::initialize();

	    if(isset($this->config['is_sandbox']) && $this->config['is_sandbox'] == 1){
            // Provider api end-points for Salesforce sandboxes
            $this->api->authorize_url   = "https://test.salesforce.com/services/oauth2/authorize";
            $this->api->token_url       = "https://test.salesforce.com/services/oauth2/token";
            $this->userProfileUrl       = 'https://test.salesforce.com/services/oauth2/userinfo';
        } else {
            // Provider api end-points for Salesforce production environment
            $this->api->authorize_url   = "https://login.salesforce.com/services/oauth2/authorize";
            $this->api->token_url       = "https://login.salesforce.com/services/oauth2/token";
        }
	}

    /**
     * load the user profile from the IDp api client
     */
    function getUserProfile()
    {
        $this->api->curl_header = array(
            'Authorization: Bearer '.$this->api->access_token,
        );
        $data = $this->api->get( $this->userProfileUrl );
        return json_decode(json_encode($data), true);
    }
}