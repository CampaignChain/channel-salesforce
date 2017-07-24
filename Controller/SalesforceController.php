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

namespace CampaignChain\Channel\SalesforceBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class SalesforceController extends Controller
{
    const RESOURCE_OWNER = 'Salesforce';

    private $applicationInfo = array(
        'key_labels' => array('id', 'Client ID'),
        'secret_labels' => array('secret', 'Client Secret'),
        'config_url' => 'https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/intro_defining_remote_access_applications.htm',
        'parameters' => array(),
        'wrapper' => array(
            'class'=>'Hybrid_Providers_Salesforce',
            'path' => 'vendor/campaignchain/channel-salesforce/REST/SalesforceOAuth.php'
        ),
    );

    public function createAction()
    {
        $oauthApp = $this->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        if(!$application){
            return $oauthApp->newApplicationTpl(self::RESOURCE_OWNER, $this->applicationInfo);
        }
        else {
            return $this->render(
                'CampaignChainChannelSalesforceBundle:Create:index.html.twig',
                array(
                    'page_title' => 'Connect with Salesforce',
                    'app_id' => $application->getKey(),
                )
            );
        }
    }

    public function loginAction(Request $request){
        $oauth = $this->get('campaignchain.security.authentication.client.oauth.authentication');
        $this->applicationInfo['parameters']['is_sandbox'] = $request->get('is_sandbox');
        $status = $oauth->authenticate(self::RESOURCE_OWNER, $this->applicationInfo);

        if($status){
            $this->get('session')->getFlashBag()->add(
                'success',
                'The location <a href="#">'.$profile->displayName.'</a> was connected successfully.'
            );
        } else {
            $this->get('session')->getFlashBag()->add(
                'warning',
                'A location has already been connected for this account.'
            );
        }

        return $this->render(
            'CampaignChainChannelSalesforceBundle:Create:login.html.twig',
            array(
                'redirect' => $this->generateUrl('campaignchain_core_location')
            )
        );
    }
}