<?php
namespace Api\Service;

use Refill\Service\Customer;
use Refill\Service\System;
use Refill\Service\ApiGw;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\View\Model\ViewModel;
use Refill\ErrorCode\ErrorCode;
use Refill\Service\Account;
use Zend\View\Model\JsonModel;
use Zend\Crypt\Password\Bcrypt;

class ApiFlowOp extends ApiFlow
{

    protected $account;

    protected $config;

    protected $db;

    protected $username;

    protected $userId;

    public function __construct($db, $config)
    {
        $this->db = $db;
        $this->config = $config;
        
        parent::__construct($db, $config);
    }

    private function getCurrentActiveLineMdn()
    {
        $username = $this->username;
        
        $userId = $this->customer->getIdByUsername($username);
        
        $results = $this->db->query("select * from e_users where id = ?", array(
            $userId
        ));
        
        $row = $results->current();
        
        $mdn = $row['current_account_number'];
        
        return $mdn;
    }

    private function getCurrentActiveLineMdnConfirmStatus($mdn)
    {
        $username = $this->username;
        
        $userId = $this->customer->getIdByUsername($username);
        
        $results = $this->db->query("select eua.mdn_confirmed from e_users_accounts eua where mdn = ?", array(
            $mdn
        ));
        
        $row = $results->current();
        
        return $row['mdn_confirmed'] === 'YES' ? 'YES' : 'NO';
    }

    public function apiGetListLines($session_id)
    {
        try {
            $time0 = time();
            
            $ret = $this->validateSession($session_id);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            $userId = $this->userId;
            $username = $this->username;
            
            $db = $this->db;
            $config = $this->config;
            
            $this->account = new Account($db, $config);
            
            $list = $this->account->getUserLines($userId);
            $unConfirmedlist = $this->account->getUserLinesUnconfirmed($userId);
            
            $return = [
                'return_code' => 1,
                'return_text' => 'Succes',
                'return_data' => array(
                    'list' => $list,
                    'active_mdn' => $this->getCurrentActiveLineMdn(),
                    'active_mdn_confirmed' => $this->getCurrentActiveLineMdnConfirmStatus(),
                    'unconfirmed' => $unConfirmedlist
                )
            ];
            
            $ret = $return;
        } catch (\Exception $e) {
            $ret = $this->returnException($e);
        }
        
        $time1 = time();
        $delay = $time1 - $time0;
        
        $this->addStats(array(
            'dtg_started' => date("Y-m-d H:i:s", $time0),
            'dtg_ended' => date("Y-m-d H:i:s", $time1),
            'delay' => $delay,
            'api_name' => __FUNCTION__,
            'return_code' => $ret['return_code'],
            'return_text' => $ret['return_text']
        ));
        
        return $ret;
    }

    public function apiGetAccountInfo($session_id, $mdn)
    {
        try {
            
            $time0 = time();
            
            $ret = $this->validateSession($session_id);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            $userId = $this->userId;
            $username = $this->username;
            
            $db = $this->db;
            $config = $this->config;
            
            $ret = $this->validateMdn($mdn);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            
            $mdn = $ret['return_data']['account_number'];
            
            $this->account = new Account($db, $config);
            
            
            if (! $this->getCurrentActiveLineMdnConfirmStatus($mdn)) {
                throw new \Exception(" {$mdn} is not confirmed ", 2);
            }
            
            $time0 = time();
            if ($cache == 'no')
                $return = $this->account->getAccountInfo($mdn, $username, false);
            else
                $return = $this->account->getAccountInfo($mdn, $username);
            
            $allAccounts = $this->account->getUserLines($userId);
            
            $currentAccountType = '';
            $primaryAed = '';
            foreach ($allAccounts as $account) {
                if ($mdn == $account['mdn'])
                    $currentAccountType = $account['type'];
                
                if ($account['type'] == 'Family (Primary)')
                    $primaryAed = $account['aed'];
            }
            
            if ($currentAccountType == 'Family (Secondary)')
                $return['aed'] = $primaryAed;
            
            if (! empty($return['aed']) && $return['aed'] != '0000-00-00')
                $return['aed'] = date('m/d/Y', strtotime($return['aed']));
            else
                $return['aed'] = 'n/a';
            
            $r = serialize($return);
            
            $time1 = time();
            $delay = $time1 - $time0;
            
//            $ret = $return;
            $ret = array(
                'return_code'=>$return['return_code'],
                'return_text'=>$return['return_text'],
                'return_data'=>$return,
            );
        } catch (\Exception $e) {
            $ret = $this->returnException($e);
        }
        
        $time1 = time();
        $delay = $time1 - $time0;
        
        $this->addStats(array(
            'dtg_started' => date("Y-m-d H:i:s", $time0),
            'dtg_ended' => date("Y-m-d H:i:s", $time1),
            'delay' => $delay,
            'api_name' => __FUNCTION__,
            'return_code' => $ret['return_code'],
            'return_text' => $ret['return_text']
        ));
        
        return $ret;
    }

    /**
     * api request call : attachLine
     * params : 1. session_id , 2.mdn
    */
    
    public function apiAttachLine($session_id,$mdn)
    {
        try {
            $time0 = time();
            
            $ret = $this->validateSession($session_id);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            $userId = $this->userId;
            $username = $this->username;
            
            $db = $this->db;
            $config = $this->config;

            // add OP code below

            $userMdn=$mdn;

            $mdn = trim($userMdn);

            $mdn = preg_replace('/\D/', '',$mdn);

            if (strlen($mdn) == 11)
                $mdn = substr($mdn, 1);


            if (! preg_match('/^\d{10}$/', $mdn)) {
                throw new \Exception("Invalid MDN [{$mdn}] format. Please enter only 10 digits", 12);
            }

            $this->customer->setUserId($userId);
            $retResult = $this->customer->addMdn($mdn,'',$username);

            $this->account = new Account($db, $config);
            $setLineResult=$this->account->setLine($mdn, $userId);
            $returnData=array('addMdnResult'=>$retResult['return_data'],'setLineResult'=>$setLineResult);
            // end of OP code
            
            $return = [
                'return_code' => $retResult['return_code'],
                'return_text' => $retResult['return_text'],
                'return_data' => $returnData,
            ];
            
            $ret = $return;
        } catch (\Exception $e) {
            $ret = $this->returnException($e);
        }
        
        $time1 = time();
        $delay = $time1 - $time0;
        
        $this->addStats(array(
            'dtg_started' => date("Y-m-d H:i:s", $time0),
            'dtg_ended' => date("Y-m-d H:i:s", $time1),
            'delay' => $delay,
            'api_name' => __FUNCTION__,
            'return_code' => $ret['return_code'],
            'return_text' => $ret['return_text']
        ));
        
        return $ret;
    }

    /**
     * api request call : getEventHistory
     * params : 1. session_id
     */

    public function apiGetEventHistory($session_id)
    {
        try {
            $time0 = time();

            $ret = $this->validateSession($session_id);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            $userId = $this->userId;
            $username = $this->username;

            $db = $this->db;
            $config = $this->config;

            // add OP code below

            $getEventQuery="SELECT * FROM ".$this->system->tbls['E_EV']." where e_user_id=? order by id desc";
            $getEventQueryParam=array($userId);
            $results = $this->db->query($getEventQuery,$getEventQueryParam);

            $DataArray = array();
            foreach ($results as $row) {
                $DataArray[] = $row;
            }
            // end of OP code

            $return = [
                'return_code' => 1,
                'return_text' => 'success',
                'return_data' => $DataArray,
            ];

            $ret = $return;
        } catch (\Exception $e) {
            $ret = $this->returnException($e);
        }

        $time1 = time();
        $delay = $time1 - $time0;

        $this->addStats(array(
            'dtg_started' => date("Y-m-d H:i:s", $time0),
            'dtg_ended' => date("Y-m-d H:i:s", $time1),
            'delay' => $delay,
            'api_name' => __FUNCTION__,
            'return_code' => $ret['return_code'],
            'return_text' => $ret['return_text']
        ));

        return $ret;
    }

    /**
     * api request call : getPaymentHistory
     * params : 1. session_id
     */

    public function apiGetPaymentHistory($session_id)
    {
        try {
            $time0 = time();

            $ret = $this->validateSession($session_id);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            $userId = $this->userId;
            $username = $this->username;

            $db = $this->db;
            $config = $this->config;

            // add OP code below

            $getEventQuery="SELECT * FROM ".$this->system->tbls['E_EV']." where  e_user_id=? and (( event_type = ? and order_id is not null ) or(event_type = ?)) order by id desc";
            $getEventQueryParam=array($userId,'payment','activate-payment');
            $results = $this->db->query($getEventQuery,$getEventQueryParam);

            $DataArray = array();
            foreach ($results as $row) {
                $DataArray[] = $row;
            }
            // end of OP code

            $return = [
                'return_code' => 1,
                'return_text' => 'success',
                'return_data' => $DataArray,
            ];

            $ret = $return;
        } catch (\Exception $e) {
            $ret = $this->returnException($e);
        }

        $time1 = time();
        $delay = $time1 - $time0;

        $this->addStats(array(
            'dtg_started' => date("Y-m-d H:i:s", $time0),
            'dtg_ended' => date("Y-m-d H:i:s", $time1),
            'delay' => $delay,
            'api_name' => __FUNCTION__,
            'return_code' => $ret['return_code'],
            'return_text' => $ret['return_text']
        ));

        return $ret;
    }

    /**
     * api request call : viewProfile
     * params : 1. session_id
     */

    public function apiViewProfile($session_id)
    {
        try {
            $time0 = time();

            $ret = $this->validateSession($session_id);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            $userId = $this->userId;
            $username = $this->username;

            $db = $this->db;
            $config = $this->config;

            // add OP code below

            $getEventQuery="SELECT * FROM ".$this->system->tbls['E_U']."  WHERE `status`='ENABLED' and username =  ? ";
            $getEventQueryParam=array($username);
            $results = $this->db->query($getEventQuery,$getEventQueryParam);

            $resultData = $results->current();

            // end of OP code

            $return = [
                'return_code' => 1,
                'return_text' => 'success',
                'return_data' => $resultData,
            ];

            $ret = $return;
        } catch (\Exception $e) {
            $ret = $this->returnException($e);
        }

        $time1 = time();
        $delay = $time1 - $time0;

        $this->addStats(array(
            'dtg_started' => date("Y-m-d H:i:s", $time0),
            'dtg_ended' => date("Y-m-d H:i:s", $time1),
            'delay' => $delay,
            'api_name' => __FUNCTION__,
            'return_code' => $ret['return_code'],
            'return_text' => $ret['return_text']
        ));

        return $ret;
    }

    /**
     * api request call : editProfile
     * params : 1. session_id
     */

    public function apiEditProfile($session_id)
    {
        try {
            $time0 = time();

            $ret = $this->validateSession($session_id);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            $userId = $this->userId;
            $username = $this->username;

            $db = $this->db;
            $config = $this->config;

            // add OP code below

            $getEventQuery="SELECT * FROM ".$this->system->tbls['E_U']."  WHERE `status`='ENABLED' and username =  ? ";
            $getEventQueryParam=array($username);
            $results = $this->db->query($getEventQuery,$getEventQueryParam);

            $resultData = $results->current();

            $lineResults = $this->db->query("select * from ".$this->system->tbls['E_UA']." eua where eua.user_id = ?", array($userId));

            $lineArray = array();
            foreach ($lineResults as $row) {
                $lineArray[] = $row;
            }

            $returnData=array('user'=>$resultData,'lines'=>$lineArray);

            // end of OP code

            $return = [
                'return_code' => 1,
                'return_text' => 'success',
                'return_data' => $returnData,
            ];

            $ret = $return;
        } catch (\Exception $e) {
            $ret = $this->returnException($e);
        }

        $time1 = time();
        $delay = $time1 - $time0;

        $this->addStats(array(
            'dtg_started' => date("Y-m-d H:i:s", $time0),
            'dtg_ended' => date("Y-m-d H:i:s", $time1),
            'delay' => $delay,
            'api_name' => __FUNCTION__,
            'return_code' => $ret['return_code'],
            'return_text' => $ret['return_text']
        ));

        return $ret;
    }

    /**
     * api request call : saveProfile
     * params : 1. session_id, 2. inputArray
     */

    public function apiSaveProfile($session_id,$inputArray)
    {
        try {
            $time0 = time();

            $ret = $this->validateSession($session_id);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            $userId = $this->userId;
            $username = $this->username;

            $db = $this->db;
            $config = $this->config;

            // add OP code below

            $this->customer = new Customer($db, $config);

            $email=isset($inputArray['email'])?filter_var($inputArray['email'],FILTER_SANITIZE_EMAIL):'';
            $userNameTxt=isset($inputArray['username'])?filter_var($inputArray['username'],FILTER_SANITIZE_STRING):'';
            $firstName=isset($inputArray['first_name'])?filter_var($inputArray['first_name'],FILTER_SANITIZE_STRING):'';
            $lastName=isset($inputArray['last_name'])?filter_var($inputArray['last_name'],FILTER_SANITIZE_STRING):'';
            $address1=isset($inputArray['address1'])?filter_var($inputArray['address1'],FILTER_SANITIZE_STRING):'';
            $address2=isset($inputArray['address2'])?filter_var($inputArray['address2'],FILTER_SANITIZE_STRING):'';
            $city=isset($inputArray['city'])?filter_var($inputArray['city'],FILTER_SANITIZE_STRING):'';
            $state=isset($inputArray['state'])?filter_var($inputArray['state'],FILTER_SANITIZE_STRING):'';
            $zipcode=isset($inputArray['zipcode'])?filter_var($inputArray['zipcode'],FILTER_SANITIZE_STRING):'';
            $country=isset($inputArray['country'])?filter_var($inputArray['country'],FILTER_SANITIZE_STRING):'';
            $alternatePhone=isset($inputArray['alternatephone'])?filter_var($inputArray['alternatephone'],FILTER_SANITIZE_STRING):'';

            $masterAccountNumber=isset($inputArray['master_account_number'])?filter_var($inputArray['master_account_number'],FILTER_SANITIZE_STRING):'';

            $updateDetailArray=array();

            if(!empty($email)){$updateDetailArray['email']=$email;}
            if(!empty($firstName)){$updateDetailArray['first_name']=$firstName;}
            if(!empty($lastName)){$updateDetailArray['last_name']=$lastName;}
            if(!empty($address1)){$updateDetailArray['address1']=$address1;}
            if(!empty($address2)){$updateDetailArray['address2']=$address2;}
            if(!empty($city)){$updateDetailArray['city']=$city;}
            if(!empty($state)){$updateDetailArray['state']=$state;}
            if(!empty($zipcode)){$updateDetailArray['zipcode']=$zipcode;}
            if(!empty($country)){$updateDetailArray['country']=$country;}
            if(!empty($alternatePhone)){$updateDetailArray['alternatephone']=$alternatePhone;}

            $isValidRet = $this->customer->isValidAddressBilling($updateDetailArray);
            if($isValidRet['return_code']!=1){
                throw new \Exception($isValidRet['return_text'], $isValidRet['return_code']);
            }

            if(!empty($masterAccountNumber))
            {
                $updateDetailArray['master_account_number']=$masterAccountNumber;
                $mid = $this->customer->getAccountRowByMdn($masterAccountNumber);
                $updateDetailArray['master_account_id']=$mid['id'];
            }

            unset($updateDetailArray['email']);

            $sql = new Sql($db);
            $update = $sql->update('e_users');

            $update->where(['username' => $username]);
            $update->set($updateDetailArray);
            $selectString = $sql->getSqlStringForSqlObject($update);

            $results = $this->db->query($selectString, Adapter::QUERY_MODE_EXECUTE);

            // end of OP code

            $return = [
                'return_code' => 1,
                'return_text' => 'success',
                'return_data' => $results,
            ];

            $ret = $return;
        } catch (\Exception $e) {
            $ret = $this->returnException($e);
        }

        $time1 = time();
        $delay = $time1 - $time0;

        $this->addStats(array(
            'dtg_started' => date("Y-m-d H:i:s", $time0),
            'dtg_ended' => date("Y-m-d H:i:s", $time1),
            'delay' => $delay,
            'api_name' => __FUNCTION__,
            'return_code' => $ret['return_code'],
            'return_text' => $ret['return_text']
        ));

        return $ret;
    }

    /**
     * api request call : resetPassword
     * params : 1. session_id,2. password
     */

    public function apiResetPassword($session_id,$password)
    {
        try {
            $time0 = time();

            $ret = $this->validateSession($session_id);
            if ($ret['return_code'] != 1) {
                throw new \Exception($ret['return_text'], $ret['return_code']);
            }
            $userId = $this->userId;
            $username = $this->username;

            $db = $this->db;
            $config = $this->config;

            // add OP code below

            $newPassword=filter_var($password,FILTER_SANITIZE_STRING);

            $bcrypt = new Bcrypt();
            $passwordHash = $bcrypt->create($newPassword);

            $updatePassQuery="update ".$this->system->tbls['E_U']." set password = ? WHERE username = ? limit 1";
            $updatePassQueryParam=array($passwordHash,$username);
            $results = $this->db->query($updatePassQuery,$updatePassQueryParam);

            $updateUserQuery="update ".$this->system->tbls['E_U']." set auth_method = ? WHERE username = ? and auth_method = ? limit 1";
            $updateUserQueryParam=array('DEFAULT',$username,'MAGENTO');
            $results2 = $this->db->query($updateUserQuery,$updateUserQueryParam);

            // end of OP code

            $return = [
                'return_code' => 1,
                'return_text' => 'success',
                'return_data' => $results,
            ];

            $ret = $return;
        } catch (\Exception $e) {
            $ret = $this->returnException($e);
        }

        $time1 = time();
        $delay = $time1 - $time0;

        $this->addStats(array(
            'dtg_started' => date("Y-m-d H:i:s", $time0),
            'dtg_ended' => date("Y-m-d H:i:s", $time1),
            'delay' => $delay,
            'api_name' => __FUNCTION__,
            'return_code' => $ret['return_code'],
            'return_text' => $ret['return_text']
        ));

        return $ret;
    }
    
}
