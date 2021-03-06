<?php

App::uses('Component', 'Controller');
App::uses('PagSeguroCheckout', 'PagSeguro.Lib');

/**
 * Wrapper para a lib PagSeguroCheckout ser usada
 * junto à controllers.
 *
 * Extende um pouco as funcionalidades da lib adicionando
 * capacidade para auto-redirecionar o usuário para a página
 * de pagamentos do PagSeguro
 *
 * PHP versions 5+
 * Copyright 2010-2012, Felipe Theodoro Gonçalves, (http://ftgoncalves.com.br)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author      Felipe Theodoro Gonçalves
 * @author      Cauan Cabral
 * @link        https://github.com/ftgoncalves/pagseguro/
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @version     2.1
 */
class CheckoutComponent extends Component
{

    /**
     *
     * Instancia do Controller
     * @var Controller
     */
    protected $Controller = null;

    /**
     * Instância da Lib PagSeguroCheckout
     * que é responsável por toda a iteração
     * com a API do PagSeguro.
     *
     * @var PagSeguroCheckout
     */
    protected $_PagSeguroCheckout = null;

    /**
     * Construtor padrão
     *
     * @param ComponentCollection $collection
     * @param array $settings
     */
    public function __construct(ComponentCollection $collection, $settings = array())
    {
        parent::__construct($collection, $settings);

        $this->_PagSeguroCheckout = new PagSeguroCheckout($settings);
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
        return $this->_PagSeguroCheckout->config($config);
    }

    /**
     * Retorna o último erro na lib
     *
     * @return string
     */
    public function getErrors()
    {
        return $this->_PagSeguroCheckout->lastError;
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
     * Incluí item no carrinho de compras
     *
     * @param string  $id            Identificação do produto no seu sistema
     * @param string  $name          Nome do produto
     * @param string  $amount        Valor do item
     * @param integer $quantity      Quantidade
     * @param string  $weight        Peso do item
     * @param string  $shippingCost  Quantidade
     *
     * @return void
     */
    public function addItem($id, $name, $amount, $quantity = 1, $weight = 0, $shippingCost = null)
    {
        $this->_PagSeguroCheckout->addItem($id, $name, $amount, $quantity, $weight);

        return $this;
    }

    /**
     * Método alternativo para incluir um item como um array
     *
     * @param array $item
     */
    public function setItem($item)
    {
        $this->_PagSeguroCheckout->setItem($item);

        return $this;
    }

    /**
     * Define o endereço de entrega
     *
     * @param string $zip			CEP
     * @param string $address		Endereço (Rua, por exemplo)
     * @param string $number		Número
     * @param string $completion	Complemento
     * @param string $neighborhood	Bairro
     * @param string $city			Cidade
     * @param string $state			Estado
     * @param string $country		País
     */
    public function setShippingAddress($zip, $address, $number, $completion, $neighborhood, $city, $state, $country)
    {
        $this->_PagSeguroCheckout->setShippingAddress($zip, $address, $number, $completion, $neighborhood, $city, $state, $country);

        return $this;
    }

    /**
     * Define os dados do cliente
     *
     * @param string $email
     * @param string $name
     * @param string $areaCode
     * @param string $phoneNumber
     */
    public function setCustomer($email, $name, $areaCode = null, $phoneNumber = null)
    {
        $this->_PagSeguroCheckout->setCustomer($email, $name, $areaCode, $phoneNumber);

        return $this;
    }

    /**
     * Define o tipo de entrega
     *
     * @param string $type
     * @throws PagSeguroException
     */
    public function setShippingType($type)
    {
        $this->_PagSeguroCheckout->setShippingType($type);

        return $this;
    }

    /**
     * Define o código de autorização do cliente. caso seja uma aplicação.
     * @param string $code
     * @return CheckoutComponent
     */
    public function setAuthCode($code)
    {
        $this->_PagSeguroCheckout->setAuthorizationCode($code);
        return $this;
    }
    /**
     * Define o endereço que a API deve retornar após termino no ambiente
     *  de autorização do PagSeguro.
     * A aPI retornara passando o parametro notificationCode dia querystring.
     * eg.: http://www.seusite.com.br/retorno.php?notificationCode=BD1E4AA76CD36CD39FD3349F7FAEA300BBE1
     * @param string $url eg.: http://seusite.com.br/retorno.php
     * @return CheckoutComponent
     */
    public function returnTo($url)
    {
        $this->_PagSeguroCheckout->setReturnTo($url);
        return $this;
    }

    /**
     * Finaliza a compra.
     * Recebe o codigo para redirecionamento ou erro.
     *
     * @param bool $autoRedirect Se o componente deve redirecionar
     * a aplicação automaticamente.
     * @return array Resposta do PagSeguro para redirecionamento
     * do usuário.
     */
    public function finalize($autoRedirect = true)
    {
        $response = $this->_PagSeguroCheckout->finalize();
        if ($autoRedirect && isset($response['redirectTo']))
        {
            $this->Controller->redirect($response['redirectTo']);
            return;
        }

        return $response;
    }

}