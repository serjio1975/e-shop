<?php
namespace Api\Service;

use Refill\Service\Customer;
use Refill\Service\System;
use Refill\Service\ApiGw;
use Zend\Db\TableGateway\TableGateway;
use Zend\View\Model\ViewModel;
use Refill\ErrorCode\ErrorCode;

class ApiFlow
{

    protected $db;

    protected $mdb;

    protected $userId;

    protected $eUserAccounts;

    protected $eEvents;

    protected $apiGw;

    protected $eUsersTbl;

    protected $eAddressTbl;

    protected $eHttpOrigin;

    protected $eMyrpmRecurring;

    protected $eMyrpmRecurringId;

    public $eMyrpmRecurringTbl;

    protected $sureTax;

    protected $stripe;

    public $eLog;

    protected $eLogTransactionId;

    protected $eStripePaymentLogTbl;

    protected $eCards;

    protected $mustFieldsShop;

    protected $config;

    protected $eProfilingTbl;

    protected $eFraudTbl;

    protected $username;

    protected $eCouponsLogsTbl;

    protected $eReferralCodeTbl;

    protected $file;

    protected $eTracking;

    protected $eRegisterLogTbl;

    protected $eUsereSpecial;

    protected $eJobsQueueTbl;

    protected $eStripeSourceTbl;

    const CUSTOMER_ERROR_BASE = 3;

    public $system;

    protected $eOnlineCouponsAppleTbl;

    protected $baseCode;

    protected $customer;

    protected $eApiSessions;

    protected $eApiFunctionsStats;

    protected $eEapiExceptionsTbl;

    /**
     *
     * @return the $username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     *
     * @param field_type $username            
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     *
     * @return the $eLogTransactionId
     */
    public function getELogTransactionId()
    {
        return $this->eLogTransactionId;
    }

    /**
     *
     * @param field_type $eLogTransactionId            
     */
    public function setELogTransactionId($eLogTransactionId)
    {
        $this->eLogTransactionId = $eLogTransactionId;
    }

    /**
     *
     * @return the $eMyrpmRecurringId
     */
    public function getEMyrpmRecurringId()
    {
        return $this->eMyrpmRecurringId;
    }

    /**
     *
     * @param number $eMyrpmRecurringId            
     */
    public function setEMyrpmRecurringId($eMyrpmRecurringId)
    {
        $this->eMyrpmRecurringId = $eMyrpmRecurringId;
    }

    /**
     *
     * @return the $userId
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     *
     * @param field_type $userId            
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function __construct($db, $config)
    {
        $this->db = $db;
        $this->eUserAccounts = new TableGateway('e_users_accounts', $this->db);
        $this->eEvents = new TableGateway('e_events', $this->db);
        $this->eSourceTbl = new TableGateway('e_source', $this->db);
        $this->eAddressTbl = new TableGateway('e_address', $this->db);
        $this->eHttpOriginTbl = new TableGateway('e_http_origin', $this->db);
        $this->eMyrpmRecurringTbl = new TableGateway('e_myrpm_recurring', $this->db);
        $this->system = new System($this->db, $config);
        
        $this->customer = new \Refill\Service\Customer($this->db, (array) $config);
        
        $this->config = $config;
        $this->eApiSessions = new TableGateway('e_api_sessions', $this->db);
        
        $this->eApiFunctionsStats = new TableGateway('eapi_functions_stats', $this->db);
        
        $this->eEapiExceptionsTbl = new TableGateway('eapi_exceptions', $this->db);
    }

    public function api12($username, $password)
    {
        
        /*
         * $results = $this->db->query('select * from e_payment_transaction where myrpm_recurring_id = ? order by id desc limit 1', array(
         * $rid
         * ));
         *
         * $row = $results->current();
         */
        $aRet = array(
            'return_code' => 1,
            'return_text' => 'Success',
            'return_data' => array()
        );
        return $aRet;
    }

    public function ipBlacklist($ip)
    {
        $results = $this->db->query("select * from {$this->system->tbls['EAPI_BLK']} where  ip = ? ", array(
            $ip
        ));
        
        $row = $results->current();
        
        if ($row) {
            $ret = true;
        } else {
            $ret = false;
        }
        
        return $ret;
    }

    public function authValidation($key)
    {
        try {
            
            $this->setBaseCode(1);
            
            $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
            
            if ($this->ipBlacklist($ip)) {
                throw new \Exception($this->customer->map(1080), $this->rcm(1080));
            }
            
            $httpMethod = $_SERVER['REQUEST_METHOD'];
            if ('GET' === $httpMethod) {
                throw new \Exception($this->customer->map(1083), $this->rcm(1083));
            }
            ;
            
            if (! isset($key)) {
                throw new \Exception($this->customer->map(1082), $this->rcm(1082));
            }
            
            if ($key !== $this->customer->map(1081)) {
                throw new \Exception($this->customer->map(1082), $this->rcm(1082));
            }
            
            $ret = array(
                'return_code' => 1,
                'return_text' => ''
            );
        } catch (\Exception $e) {
            $ret = array(
                'return_code' => $e->getCode(),
                'return_text' => $e->getMessage()
            );
        }
        
        return $ret;
    }

    public function getFunctionsAuth($ip)
    {
        $results = $this->db->query("select class, method from {$this->system->tbls['EAPI_FUNC']} where  category = ? and status = ?", array(
            'auth',
            'Enabled'
        ));
        
        foreach ($results as $k => $v) {
            $ret[] = array(
                $v['class'],
                $v['method']
            );
        }
        
        return $ret;
    }

    public function setBaseCode($baseCode)
    {
        $this->baseCode = $baseCode;
    }

    public function rcm($code)
    {
        $ret = $code == 1 ? 1 : sprintf("%d%04d", $this->baseCode, $code);
        return (integer) $ret;
    }

    public function getFunctionsCustomer()
    {
        $results = $this->db->query("select class, method, api_name from {$this->system->tbls['EAPI_FUNC']} where  category = ? and status = ?", array(
            'customer',
            'Enabled'
        ));
        
        foreach ($results as $k => $v) {
            $ret[] = array(
                $v['class'],
                $v['method'],
                $v['api_name']
            );
        }
        
        return $ret;
    }

    public function validateSession($hash)
    {
        try {
            $this->setBaseCode(2);
            
            $hash = filter_var($hash, FILTER_SANITIZE_STRING);
            if (! $hash) {
                throw new \Exception($this->customer->map(1086), $this->rcm(1086));
            }
            
            $results = $this->db->query("select * from {$this->system->tbls['EAPI_SESS']} where  session_id = ? ", array(
                $hash
            ));
            
            $row = $results->current();
            
            if (! $row) {
                throw new \Exception($this->customer->map(1086), $this->rcm(1086));
            }
            
            if ($row) {
                
                $lastUsed = strtotime($row['dtg_updated']);
                $cur = strtotime($this->customer->map(1085));
                
                if ($lastUsed < $cur) {
                    
                    throw new \Exception($this->customer->map(1087), $this->rcm(1087));
                }
                
                $uRow = $this->customer->getUsernameById($row['user_id']);
                if ($uRow['status'] != 'ENABLED') {
                    throw new \Exception($this->customer->map(1088), $this->rcm(1088));
                }
                
                $arr = array(
                    'dtg_updated' => date('Y-m-d H:i:s')
                );
                $this->eApiSessions->update($arr, "id = {$row['id']}");
                
                $this->userId = $row['user_id'];
                $this->username = $uRow['username'];
                
                $ret = array(
                    'return_code' => 1,
                    'return_text' => 'Success',
                    'return_data' => array(
                        'user_id' => $this->userId,
                        'username' => $this->username
                    )
                );
            }
        } catch (\Exception $e) {
            $this->eApiSessions->delete("session_id='{$hash}'");
            
            $ret = array(
                'return_code' => $e->getCode(),
                'return_text' => $e->getMessage()
            );
        }
        
        return $ret;
    }

    public function validateMdn($mdn)
    {
        try {
            $this->setBaseCode(2);
            
            $mdn = filter_var(trim($mdn), FILTER_SANITIZE_NUMBER_INT);
            if (! $mdn) {
                throw new \Exception($this->customer->map(1089), $this->rcm(1089));
            }
            
            if (strlen($mdn) != 10) {
                throw new \Exception($this->customer->map(1089), $this->rcm(1089));
            }
            
            $ret = array(
                'return_code' => 1,
                'return_text' => 'Success',
                'return_data' => array(
                    'account_number' => $mdn
                )
            );
        } catch (\Exception $e) {
            $ret = array(
                'return_code' => $e->getCode(),
                'return_text' => $e->getMessage()
            );
        }
        
        return $ret;
    }

    public function addStats($in)
    {
        $arr = array(
            'dtg_started' => $in['dtg_started'],
            'dtg_ended' => $in['dtg_ended'],
            'delay' => $in['delay'],
            'api_name' => $in['api_name'],
            'return_code' => $in['return_code'],
            'return_text' => $in['return_text'],
            'ip' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']
        );
        
        $this->eApiFunctionsStats->insert($arr);
    }

    public function returnException($e)
    {
        $hash = bin2hex(openssl_random_pseudo_bytes(8));
        
        $exData = array(
            'dtg' => date('Y-m-d H:i:s'),
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            // 'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'hash' => $hash,
            'server_name' => $_SERVER['SERVER_NAME'],
            'request_uri' => $_SERVER['REQUEST_URI'],
            'http_agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']
        
        );
        
        $this->eEapiExceptionsTbl->insert($exData);
        
        $return = [
            'return_code' => $e->getCode(),
            'return_text' => $e->getMessage() . " #{$hash}"
        ];
        return $return;
    }
}
