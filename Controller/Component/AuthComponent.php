<?php

App::uses('Component', 'Controller');
App::uses('PagSeguroAuthorization', 'PagSeguro.Lib');

class AuthComponent extends Component
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
     *
     * Instancia do Controller
     * @var Controller
     */
    protected $Controller = null;

    /**
     * Instância da Lib PagSeguroAuthorization
     * que é responsável por toda a iteração
     * com a API do PagSeguro.
     *
     * @var PagSeguroAuthorization
     */
    protected $_PagSeguroAuthorization = null;

    /**
     * Construtor padrão
     *
     * @param ComponentCollection $collection
     * @param array $settings
     */
    public function __construct(ComponentCollection $collection, $settings = array())
    {
        parent::__construct($collection, $settings);

        $this->_PagSeguroAuthorization = new PagSeguroAuthorization($settings);
    }

    /**
     *
     * Methodo para setar as configurações defaults do pagseguro
     * @param Object $controller
     */
    public function startup(Controller $controller)
    {
        $this->Controller = $controller;
    }

    /**
     * Sobrescreve as configurações em tempo de execução.
     * Caso nenhum parâmetro seja passado, retorna as configurações
     * atuais.
     *
     * @param array $config
     * @return mixed Array com as configurações caso não seja
     * passado parâmetro, nada caso contrário.
     */
    public function config($config = null)
    {
        return $this->_PagSeguroAuthorization->config($config);
    }

    /**
     * Retorna o último erro da ultima transação enviada.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_PagSeguroAuthorization->lastError;
    }

    /**
     * Define uma referência para a transação com alguma
     * identificação interna da aplicação.
     *
     * @param string $id
     */
    public function setReference($id)
    {
        $this->_PagSeguroCheckout->setReference($id);

        return $this;
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
        $this->_PagSeguroAuthorization->addPermission($permission);
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
        $this->_PagSeguroAuthorization->removePermission($permission);
    }

    /**
     * Define o endereço que a API deve retornar após termino no ambiente
     *  de autorização do PagSeguro.
     * A aPI retornara passando o parametro notificationCode dia querystring.
     * eg.: http://www.seusite.com.br/retorno.php?notificationCode=BD1E4AA76CD36CD39FD3349F7FAEA300BBE1
     * @param string $url eg.: http://seusite.com.br/retorno.php
     */
    public function returnTo($url)
    {
        $this->_PagSeguroAuthorization->setReturnTo($url);
    }

    /**
     * array de dados da conta do cliente.
     * @param array $account
     */
    public function accountInformation($account)
    {
        $this->_PagSeguroAuthorization->setAccount($account);
    }

    /**
     * Finaliza o requerimento de autorização.
     * Recebe o codigo para redirecionamento ou erro.
     *
     * @param bool $autoRedirect Se o componente deve redirecionar
     * a aplicação automaticamente.
     * @return array Resposta do PagSeguro para redirecionamento
     * do usuário.
     */
    public function finalize($autoRedirect = true)
    {
        $response = $this->_PagSeguroAuthorization->finalize();

        if ($autoRedirect && isset($response['redirectTo']))
        {
            $this->Controller->redirect($response['redirectTo']);
            return;
        }

        return $response;
    }

}