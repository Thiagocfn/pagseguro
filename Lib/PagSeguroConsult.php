<?php

App::uses('PagSeguro', 'PagSeguro.Lib');
App::uses('PagSeguroException', 'PagSeguro.Lib');

/**
 * Lib que implementa a API de consulta do PagSeguro.
 *
 * PHP versions 5.3+
 * Copyright 2010-2013, Felipe Theodoro Gonçalves, (http://ftgoncalves.com.br)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author	 	 Felipe Theodoro Gonçalves
 * @author       Cauan Cabral
 * @link         https://github.com/radig/pagseguro/
 * @license      MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class PagSeguroConsult extends PagSeguro
{

    const TRANSACTION = "transaction";
    const AUTH = "authorization";
    const TRANSACTION_PATH = "/v2/transactions/";
    const AUTH_PATH = "/v2/authorizations/notifications/";

    static public $TYPE_READ = 'read';
    static public $TYPE_SEARCH = 'search';
    static public $TYPE_ABANDONED = 'abandoned';
    protected $others = array();

    /**
     * Construtor padrão
     *
     * @param array $settings
     */
    public function __construct($settings = array())
    {
        $this->type = self::$TYPE_READ;
        $this->settings['onlyBasic'] = false;

        parent::__construct($settings);
    }

    /**
     * Requisita da API do PagSeguro os dados de uma transação (ou autorização),
     * converte a resposta de XML para Array e então o retorna.
     *
     * @param string $code Código da notificação
     * @param string $type o tipo de consulta [PagSeguroConsult::TRANSACTION | PagSeguroConsult::AUTH]
     * @return mixed array com dos dados da transação em caso de sucesso,
     * false em caso de falha
     */
    public function read($code, $type = PagSeguroConsult::TRANSACTION)
    {


        try
        {
            if ($type == PagSeguroConsult::TRANSACTION)
            {
                $this->URI['path'] = PagSeguroConsult::TRANSACTION_PATH;
            } else
            {
                $this->URI['path'] = PagSeguroConsult::AUTH_PATH;
            }


            $this->URI['path'] .= $code;
            $retorno = $this->_sendData($this->_prepareData(), 'GET');
            $this->URI['path'] = "";
            return $retorno;
        } catch (PagSeguroException $e)
        {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Faz uma consulta especificando uma data de início, fim,
     * um limite de registros por página e a página requisitada.
     *
     * @param string $begin data válida
     * @param string $end data válida
     * @param int $limit Número de registros por página
     * @param int $page Número da página requisitada
     * @return mixed array com dos dados da notificação em caso de sucesso,
     * false em caso de falha
     */
    public function find($begin, $end, $limit = 50, $page = 1)
    {
        if ($this->type === self::$TYPE_ABANDONED)
        {
            $this->URI['path'] .= 'abandoned/';
        }

        try
        {
            $bg = new DateTime($begin);
            $ed = new DateTime($end);
        } catch (Exception $e)
        {
            $this->lastError = __('Data de início ou término para a consulta é inválida.');
            return false;
        }

        $this->others = array(
            'initialDate' => $bg->format(DateTime::W3C),
            'finalDate' => $ed->format(DateTime::W3C),
            'page' => $page,
            'maxPageResults' => $limit
        );

        try
        {
            $response = $this->_sendData($this->_prepareData(), 'GET');
            return $response;
        } catch (PagSeguroException $e)
        {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Prepara os dados para enviar ao PagSeguro
     *
     * @return array
     */
    protected function _prepareData()
    {
        $config = array();
        //Definindo as configurações de autenticação.
        if ($this->settings['type'] == 'seller')
        {
            $email = $this->settings['email'];
            $token = $this->settings['token'];
            $config = compact("email", "token");
        } else
        if ($this->settings['type'] == 'application')
        {
            $appId = $this->settings['appId'];
            $appKey = $this->settings['appKey'];
            if (isset($this->authorizationCode))
            {
                $authorizationCode = $this->authorizationCode;
                $config = compact("appId", "appKey", "authorizationCode");
            } else
            {
                $config = compact("appId", "appKey");
            }
        }
        return array_merge($config, $this->others);
    }

    /**
     * Recebe o Xml convertido para Array com os dados da Consulta
     * retornado pelo PagSeguro.
     *
     * Caso o segundo parâmetro seja false, retorna o Array original
     * sem o índice 'transactionSearchResult'.
     *
     * Caso contrário devolve um Array formatado com o resultado, tendo a estrutura:
     * Array(
     *  'pages' => 000,
     *  'current' => 000,
     *  'items' => Array(
     *    Array(
     *     'date' => date('Y-m-d'),
     *     'code' => 'xxxxxxxxx',
     *     'value' => 'xxxx',
     *     'status' => 0,
     *     'reference' => 'xxxxxxxx'
     *    )
     * 	)
     * )
     *
     * um Array reduzido com as informações essenciais caso o segundo
     * parâmetro seja true
     *
     * @param String $data
     * @return array
     */
    protected function _parseResponse($data)
    {
        if (!isset($data['transactionSearchResult']) && !isset($data['transaction']) && !isset($data['authorization']))
        {
            throw new PagSeguroException("Resposta inválida do PagSeguro para uma Consulta.");
        }

        if (isset($data['transaction']))
        {
            if (!$this->settings['onlyBasic'])
            {
                return $data['transaction'];
            }

            return $this->_parseOneResponseEntry($data['transaction']);
        }
        else if (isset ($data['transactionSearchResult']))
        {

            $decoded = array(
                'pages' => $data['transactionSearchResult']['totalPages'],
                'pageSize', $data['transactionSearchResult']['resultsInThisPage'],
                'current' => $data['transactionSearchResult']['currentPage'],
            );

            $decoded['items'] = array();

            foreach ($data['transactionSearchResult']['transactions'] as $transaction)
            {
                $decoded['items'][] = $this->_parseOneResponseEntry($transaction);
            }

            return $decoded;
        }
        else 
            return $data['authorization'];
    }

    /**
     * Converte uma única transação em um array com
     * apenas os dados essenciais.
     *
     * @param array $entry Uma transação
     * @return array $decoded Resumo da transação
     */
    private function _parseOneResponseEntry($entry)
    {
        $date = substr($entry['date'], 0, 19);
        $date = str_replace('T', ' ', $date);

        $decoded = array(
            'date' => $date,
            'code' => $entry['code'],
            'value' => $entry['grossAmount'],
            'status' => $entry['status'],
            'reference' => $entry['reference'],
            'modified' => $entry['lastEventDate'],
        );

        if ($this->settings['type'] !== self::$TYPE_ABANDONED)
        {
            $decoded['paymentType'] = $entry['paymentMethod']['type'];
            $decoded['paymentCode'] = $entry['paymentMethod']['code'];
        }

        return $decoded;
    }

}
