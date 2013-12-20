<?php

App::uses('HttpSocket', 'Network/Http');
App::uses('Xml', 'Utility');
App::uses('PagSeguroException', 'PagSeguro.Lib');
App::uses('PagSeguroValidation', 'PagSeguro.Lib');
App::uses('TransactionStatuses', 'PagSeguro.Lib/Map');

/**
 * Classe base que fornece estrutura comum aos componentes
 * que interagem com o PagSeguro
 *
 * PHP versions 5.3+
 * Copyright 2010-2013, Felipe Theodoro Gonçalves, (http://ftgoncalves.com.br)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author      Felipe Theodoro Gonçalves
 * @author      Cauan Cabral
 * @link        https://github.com/radig/pagseguro/
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class PagSeguro
{

    /**
     * URI do webservice do PagSeguro
     *
     * @var array
     */
    protected $URI = array(
        'scheme' => 'https',
        'host' => 'ws.pagseguro.uol.com.br',
        'port' => '443',
        'path' => '',
    );

    /**
     * Configurações para uso da API
     *
     * @var array
     */
    protected $settings = array(
        'email' => null,
        'token' => null,
        'appId' => null,
        'appKey' => null
    );

    /**
     * Conjunto de caracteres utilizados na comunicação
     * com a API.
     *
     * @var string
     */
    public $charset = 'UTF-8';

    /**
     * Timeout das requisições com a API.
     *
     * @var integer
     */
    public $timeout = 20;

    /**
     * array de erros da última requisição executada.
     *
     * @var mixed
     */
    public $lastError = null;

    public function __construct($settings = array())
    {
        if (empty($settings) && Configure::read('PagSeguro') !== null)
        {
            $settings = (array) Configure::read('PagSeguro');
        }
        $this->config($settings);
    }

    /**
     * Sobrescreve as configurações em tempo de execução
     *
     * @param array $config
     */
    public function config($config = null)
    {
        if ($config !== null)
        {
            $this->settings = array_merge($this->settings, $config);
            $this->_settingsValidates();

            return $this;
        }

        return $this->settings;
    }

    /**
     * Retorna o nome de uma situação a partir de seu
     * código.
     *
     * @param  int $statusCode Código da situação (status)
     * @return string Nome da situação
     * @deprecated Não faz parte do módulo básico e deve ser removido.
     */
    public static function getStatusName($statusCode)
    {
        return TransactionStatuses::getMessage($statusCode) ? : 'Situação inválida';
    }

    /**
     * Envia os dados para API do PagSeguro usando método especificado.
     *
     * @throws PagSeguroException
     * @param array | string $data uma string de xml se o método for XML ou array para os demais tipos de requisições.
     * @param string $method POST | GET | XML
     * @param array $headers um array com os cabeçalhos a serem enviados.
     * @return array
     */
    protected function _sendData($data, $method = 'POST', $headers = null)
    {
        $this->_settingsValidates();



        if ('xml' === strtolower($method))
        {
            if (!isset($headers))
            {
                $headers = array('Content-Type: application/xml; charset=ISO-8859-1');
            }

            $url = $this->unparse_url($this->URI);
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_POST, $method == "POST"); //Post Request Type;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);    //set headers using the above array of headers
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    // return values as a string - not to std out

            $responseXML = curl_exec($ch);
            curl_close($ch);

            if (!$responseXML)
            {
                throw new PagSeguroException($this->error_codes[curl_errno($ch)], curl_errno($ch), curl_error($ch));
            }
            try
            {
                $response = Xml::toArray(Xml::build($responseXML));
            } catch (XmlException $e)
            {
                throw new PagSeguroException('A resposta do PagSeguro não é um XML válido.');
            }
        } else
        {
            $HttpSocket = new HttpSocket(array(
                'timeout' => $this->timeout
            ));

            if ('get' === strtolower($method))
            {
                $return = $HttpSocket->get(
                        $this->URI, $data, array('header' => array('Content-Type' => "application/x-www-form-urlencoded; charset={$this->charset}"))
                );
            } elseif ('post' === strtolower($method))
            {
                $return = $HttpSocket->post(
                        $this->URI, $data, array('header' => array('Content-Type' => "application/x-www-form-urlencoded; charset={$this->charset}"))
                );
            }
            

            switch ($return->code)
            {
                case 200:
                    break;
                case 400:
                    throw new PagSeguroException('A requisição foi rejeitada pela API do PagSeguro. Verifique as configurações.', 400, $return->body);
                case 401:
                    throw new PagSeguroException('O Token ou E-mail foi rejeitado pelo PagSeguro. Verifique as configurações.', 401);
                case 404:
                    throw new PagSeguroException('Recurso não encontrado. Verifique os dados enviados.', 404);
                default:
                    throw new PagSeguroException('Erro desconhecido com a API do PagSeguro. Verifique suas configurações.');
            }

            try
            {
                $response = Xml::toArray(Xml::build($return->body));
            } catch (XmlException $e)
            {
                throw new PagSeguroException('A resposta do PagSeguro não é um XML válido.');
            }
        }

        if ($this->_parseResponseErrors($response))
        {
            throw new PagSeguroException("Erro com os dados enviados no PagSeguro.", 666, $return->body);
        }

        return $this->_parseResponse($response);
    }

    /**
     * Parseia e retorna a resposta do PagSeguro.
     * Deve ser implementado nas classes filhas
     *
     * @param array $data
     * @return array
     */
    protected function _parseResponse($data)
    {
        throw new PagSeguroException("Erro de implementação. O método _parseResponse deve ser implementado nas classes filhas de PagSeguro.");
    }

    /**
     * Verifica se há erros na resposta.
     * @see PagSeguro::getLastError
     * @param array $data
     * @return bool True caso hajam erros, False caso contrário
     */
    protected function _parseResponseErrors($data)
    {
        if (!isset($data['errors']))
        {
            $this->lastError = null;
            return false;
        }
        $this->lastError = $data['errors'];
        return true;
    }

    /**
     * Valida os dados de configuração caso falhe dispara uma exceção
     *
     * @throws PagSeguroException
     * @return void
     */
    protected function _settingsValidates()
    {

        if (!isset($this->settings['type']))
        {
            $this->settings['type'] = 'seller';
        }
        if ($this->settings['type'] == 'seller')
        {
            $fields = array('email', 'token');
        } else if ($this->settings['type'] == 'application')
        {
            $fields = array('appId', 'appKey');
        } else
        {
            throw new PagSeguroException("Erro de configuração - Atributo 'type' precisa ser: seller ou application");
        }



        foreach ($fields as $fieldName)
        {
            if (!isset($this->settings[$fieldName]))
            {
                throw new PagSeguroException("Erro de configuração - Atributo '{$fieldName}' não definido.");
            }

            if (PagSeguroValidation::$fieldName($this->settings[$fieldName]) === false)
            {
                $msg = str_replace(':attr:', $fieldName, PagSeguroValidation::$lastError);
                throw new PagSeguroException("Erro de configuração - " . $msg);
            }
        }
    }

    /**
     * Converte uma URI que está em array para uma URL em string
     * Função inversa de parse_url.
     * @param array $parsed_url uri
     * @return string url
     */
    public function unparse_url($parsed_url)
    {
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }
    /**
     * Formata um array para querystring para ser enviado.
     * @param type $array
     * @return 
     */
    function _parsePostFieldsArray($array)
    {
        $data = array();
        foreach ($array as $key => $val)
        {
            $data[] = urlencode($key) . '=' . urlencode($val);
        }

        return implode('&', $data);
    }
    /**
     * array de erros da última requisição executada.
     * @return array
     */
    public function getLastError()
    {
        return $this->lastError;
    }

}
