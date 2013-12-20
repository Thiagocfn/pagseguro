<?php

App::uses('Xml', 'Utility');
App::uses('PagSeguro', 'PagSeguro.Lib');
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of PagSeguroAuthorization
 *
 * @author zoox
 */
class PagSeguroAuthorization extends PagSeguro
{
    /**
     * A aplicação poderá direcionar compradores para o PagSeguro e intermediar 
     * pagamentos para você.
     * 
     */

    const CREATE_CHECKOUTS = "CREATE_CHECKOUTS";
    /**
     * A aplicação poderá receber e consultar notificações das transações
     *  que ela intermediou para você
     */
    const RECEIVE_TRANSACTION_NOTIFICATIONS = "RECEIVE_TRANSACTION_NOTIFICATIONS";
    /**
     * A aplicação poderá consultar as transações que ela intermediou para você
     */
    const SEARCH_TRANSACTIONS = "SEARCH_TRANSACTIONS";
    /**
     * A aplicação poderá gerenciar e utilizar pré-aprovações
     *  de pagamentos para você
     */
    const MANAGE_PAYMENT_PRE_APPROVALS = "MANAGE_PAYMENT_PRE_APPROVALS";

    /**
     * Array com as constantes de permissão que serão solicitadas
     * @see PagSeguroAuthorization::CREATE_CHECKOUTS
     * @see PagSeguroAuthorization::RECEIVE_TRANSACTION_NOTIFICATIONS
     * @see PagSeguroAuthorization::SEARCH_TRANSACTIONS
     * @see PagSeguroAuthorization::MANAGE_PAYMENT_PRE_APPROVALS
     * @var type 
     */
    protected $permissions = array();

    /**
     * Identificador usado para fazer referência à autorização da sua requisição.
     * Opcional.
     * Tipo: Texto
     * Formato: Livre, com limite de 20 caracteres.
     * @var type 
     */
    protected $reference = null;

    /**
     * URL da API de Autorização do PagSeguro
     * @var type 
     */
    protected $url = "https://ws.pagseguro.uol.com.br/v2/authorizations/request";

    /**
     * Endereço do ambiente de autorização do PagSeguro.
     * ps.: deve estar presente o codigo %s, o qual será substituído pelo código
     *  identificador de sessão do PagSeguro
     * eg.: https://pagseguro.uol.com.br/v2/authorization/request.jhtml?code=%s
     * @var String
     */
    protected $redirectTo = 'https://pagseguro.uol.com.br/v2/authorization/request.jhtml?code=%s';

    /**
     * Sua URL que o PagSeguro deve retornar após ao final do
     *  fluxo de autorização no ambiente do PagSeguro.
     * 
     * Será enviado sob a tag: redirectURL
     * @var string URL bem formada eg.:http://www.seusite.com.br/retorno.php
     */
    protected $returnTo = "http://www.seusite.com.br/retorno.php";

    /**
     * Os dados de Cadastro Vendedor/Empresarial que será solicitada autorização.
     * @var array
     */
    protected $account = null;

    /**
     * Construtor padrão
     *
     * @param array $settings
     */
    public function __construct($settings = array())
    {
        parent::__construct($settings);
        $this->URI['path'] = "/v2/authorizations/request";
        
        
        
    }

    /**
     * Reúne os dados informados e manda a solicitação de autorização
     *  para a API do PagSeguro.
     * @see PagSeguroAuthorization::getLastError() 
     * @return array com a resposta do PagSeguro ou false em caso de erros
     */
    public function finalize()
    {
        try
        {
            $response = $this->_sendData($this->_prepareData(),"XML");
            return $response;
        } catch (PagSeguroException $e)
        {
            return false;
        }
    }

    /**
     * Adiciona um tipo de permissão que será requerido.
     * A permissão deve ser válida.
     * <i>
     * PagSeguroAuthorization::CREATE_CHECKOUTS
     * PagSeguroAuthorization::RECEIVE_TRANSACTION_NOTIFICATIONS
     * PagSeguroAuthorization::SEARCH_TRANSACTIONS
     * PagSeguroAuthorization::MANAGE_PAYMENT_PRE_APPROVALS
     * </i>
     * 
     * @param string $permission string com permissão válida.
     * @throws CakeException 
     */
    public function addPermission($permission)
    {
        if ($permission == PagSeguroAuthorization::CREATE_CHECKOUTS ||
                $permission == PagSeguroAuthorization::RECEIVE_TRANSACTION_NOTIFICATIONS ||
                $permission == PagSeguroAuthorization::SEARCH_TRANSACTIONS ||
                $permission == PagSeguroAuthorization::MANAGE_PAYMENT_PRE_APPROVALS)
        {
            $this->permissions[] = $permission;
            $this->permissions = array_unique($this->permissions);
        } else
        {
            throw new CakeException("Erro! Tipo de Permissão inexistente.");
        }
    }

    /**
     * Remove um tipo de permissão que será requerido.
     * <i>
     * PagSeguroAuthorization::CREATE_CHECKOUTS
     * PagSeguroAuthorization::RECEIVE_TRANSACTION_NOTIFICATIONS
     * PagSeguroAuthorization::SEARCH_TRANSACTIONS
     * PagSeguroAuthorization::MANAGE_PAYMENT_PRE_APPROVALS
     * </i>
     * 
     * @param string $permission string com permissão válida.
     */
    public function removePermission($permission)
    {

        function myfunction($a, $b)
        {
            if ($a === $b)
            {
                return 0;
            }
            return ($a > $b) ? 1 : -1;
        }

        $this->permissions = array_udiff($this->permissions, array($permission), "myfunction");
    }

    /**
     * Array de Permissões que serão solicitadas
     * @return array Permissões
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    public function _prepareData()
    {
        
        //Definindo as configurações de autenticação.
        if ($this->settings['type']=='application')
        {
            $appId = $this->settings['appId'];
            $appKey = $this->settings['appKey'];
            $config = compact("appId","appKey");
        }
        else
        {
            throw new PagSeguroException("Authorization é somente para Aplicações.");
        }
        $this->URI['query'] = $this->_parsePostFieldsArray($config);
        
        $reference = $this->reference;
        $permissions = array('code' => $this->permissions);
        $redirectURL = $this->returnTo;
        $account = $this->account;

        
        $data = array('authorizationRequest' => array());
        $data['authorizationRequest'] = compact("reference", "permissions", "redirectURL", "account");

        $this->recursive_unset($data);


        $xmlArray = $data;
        $xmlObject = Xml::fromArray($xmlArray, array('format' => 'tags', 'pretty' => true)); // You can use Xml::build() too
        $data = $xmlObject->asXML();

        return $data;
    }

    /**
     * Função interna utilizada pelo PagSeguro::_sendData() para preprocessar
     * os dados recebidos da API do PagSeguro e certificar que os mesmos estão corretos.
     * Recebe o XML convertido para Array com os dados de redirecionamento ou erros.
     *
     * @param array $data
     * @return array
     */
    protected function _parseResponse($data)
    {
        if (!isset($data['authorizationRequest']))
        {
            throw new PagSeguroException("Resposta inválida do PagSeguro para Authorization.");
        }

        $data['redirectTo'] = sprintf($this->redirectTo, $data['authorizationRequest']['code']);

        return $data;
    }

    /**
     * Remove recursivamente os pares (key/value) que estiverem com valores vazios;
     * @param type $array
     * @return void
     */
    private function recursive_unset(&$array)
    {
        foreach ($array as $key => &$value)
        {
            if (is_array($value))
            {
                $this->recursive_unset($value);
            } else
            {
                if ($value == "")
                    unset($array[$key]);
            }
        }
    }
    /**
     * Identificador usado para fazer referência à autorização da sua requisição.
     * Opcional.
     * Tipo: Texto
     * Formato: Livre, com limite de 20 caracteres.
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * Identificador usado para fazer referência à autorização da sua requisição.
     * Opcional.
     * Tipo: Texto
     * Formato: Livre, com limite de 20 caracteres.
     * @param string 
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
        return $this;
    }

    /**
     * URL da API de Autorização do PagSeguro
     * @return string 
     */
    
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * URL da API de Autorização do PagSeguro
     * @param string 
     */
    
    public function setUrl(type $url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Endereço do ambiente de autorização do PagSeguro.
     * ps.: deve estar presente o codigo %s, o qual será substituído pelo código
     *  identificador de sessão do PagSeguro
     * eg.: https://pagseguro.uol.com.br/v2/authorization/request.jhtml?code=%s
     * @return string Description
     */
    
    public function getRedirectTo()
    {
        return $this->redirectTo;
    }

    /**
     * Endereço do ambiente de autorização do PagSeguro.
     * ps.: deve estar presente o codigo %s, o qual será substituído pelo código
     *  identificador de sessão do PagSeguro
     * eg.: https://pagseguro.uol.com.br/v2/authorization/request.jhtml?code=%s
     * @param string $name Description
     */
    
    public function setRedirectTo($redirectTo)
    {
        $this->redirectTo = $redirectTo;
        return $this;
    }

    /**
     * Sua URL que o PagSeguro deve retornar após ao final do
     *  fluxo de autorização no ambiente do PagSeguro.
     * 
     * Será enviado sob a tag: redirectURL
     * @return string URL bem formada eg.:http://www.seusite.com.br/retorno.php
     */
    
    public function getReturnTo()
    {
        return $this->returnTo;
    }

    /**
     * Sua URL que o PagSeguro deve retornar após ao final do
     *  fluxo de autorização no ambiente do PagSeguro.
     * 
     * Será enviado sob a tag: redirectURL
     * @param string URL bem formada eg.:http://www.seusite.com.br/retorno.php
     */
    
    public function setReturnTo($returnTo)
    {
        $this->returnTo = $returnTo;
        return $this;
    }

    /**
     * Os dados de Cadastro Vendedor/Empresarial que será solicitada autorização.
     * return array
     */
    
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Os dados de Cadastro Vendedor/Empresarial que será solicitada autorização.
     * @param array
     */
    
    public function setAccount($account)
    {
        $this->account = $account;
        return $this;
    }


}

?>
